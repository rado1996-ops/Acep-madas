<?php

namespace MailPoet\Cron\Workers;

if (!defined('ABSPATH')) exit;


use MailPoet\Cron\CronHelper;
use MailPoet\Logging\LoggerFactory;
use MailPoet\Models\Newsletter;
use MailPoet\Models\ScheduledTask;
use MailPoet\Models\Segment;
use MailPoet\Models\Subscriber;
use MailPoet\Models\SubscriberSegment;
use MailPoet\Newsletter\Scheduler\PostNotificationScheduler;
use MailPoet\Newsletter\Scheduler\Scheduler as NewsletterScheduler;
use MailPoet\Newsletter\Scheduler\WelcomeScheduler;
use MailPoet\Segments\SubscribersFinder;
use MailPoet\Tasks\Sending as SendingTask;
use MailPoetVendor\Carbon\Carbon;
use MailPoetVendor\Monolog\Logger;

class Scheduler {
  const TASK_BATCH_SIZE = 5;

  /** @var SubscribersFinder */
  private $subscribers_finder;

  /** @var LoggerFactory */
  private $logger_factory;

  /** @var CronHelper */
  private $cron_helper;

  function __construct(
    SubscribersFinder $subscribers_finder,
    LoggerFactory $logger_factory,
    CronHelper $cron_helper
  ) {
    $this->cron_helper = $cron_helper;
    $this->subscribers_finder = $subscribers_finder;
    $this->logger_factory = $logger_factory;
  }

  function process($timer = false) {
    $timer = $timer ?: microtime(true);

    // abort if execution limit is reached
    $this->cron_helper->enforceExecutionLimit($timer);

    $scheduled_queues = self::getScheduledQueues();
    if (!count($scheduled_queues)) return false;
    $this->updateTasks($scheduled_queues);
    foreach ($scheduled_queues as $i => $queue) {
      $newsletter = Newsletter::findOneWithOptions($queue->newsletter_id);
      if (!$newsletter || $newsletter->deleted_at !== null) {
        $queue->delete();
      } elseif ($newsletter->status !== Newsletter::STATUS_ACTIVE && $newsletter->status !== Newsletter::STATUS_SCHEDULED) {
        continue;
      } elseif ($newsletter->type === Newsletter::TYPE_WELCOME) {
        $this->processWelcomeNewsletter($newsletter, $queue);
      } elseif ($newsletter->type === Newsletter::TYPE_NOTIFICATION) {
        $this->processPostNotificationNewsletter($newsletter, $queue);
      } elseif ($newsletter->type === Newsletter::TYPE_STANDARD) {
        $this->processScheduledStandardNewsletter($newsletter, $queue);
      } elseif ($newsletter->type === Newsletter::TYPE_AUTOMATIC) {
        $this->processScheduledAutomaticEmail($newsletter, $queue);
      }
      $this->cron_helper->enforceExecutionLimit($timer);
    }
  }

  function processWelcomeNewsletter($newsletter, $queue) {
    $subscribers = $queue->getSubscribers();
    if (empty($subscribers[0])) {
      $queue->delete();
      return false;
    }
    $subscriber_id = (int)$subscribers[0];
    if ($newsletter->event === 'segment') {
      if ($this->verifyMailpoetSubscriber($subscriber_id, $newsletter, $queue) === false) {
        return false;
      }
    } else {
      if ($newsletter->event === 'user') {
        if ($this->verifyWPSubscriber($subscriber_id, $newsletter, $queue) === false) {
          return false;
        }
      }
    }
    $queue->status = null;
    $queue->save();
    return true;
  }

  function processPostNotificationNewsletter($newsletter, $queue) {
    $this->logger_factory->getLogger(LoggerFactory::TOPIC_POST_NOTIFICATIONS)->addInfo(
      'process post notification in scheduler',
      ['newsletter_id' => $newsletter->id, 'task_id' => $queue->task_id]
    );
    // ensure that segments exist
    $segments = $newsletter->segments()->findMany();
    if (empty($segments)) {
      $this->logger_factory->getLogger(LoggerFactory::TOPIC_POST_NOTIFICATIONS)->addInfo(
        'post notification no segments',
        ['newsletter_id' => $newsletter->id, 'task_id' => $queue->task_id]
      );
      return $this->deleteQueueOrUpdateNextRunDate($queue, $newsletter);
    }

    // ensure that subscribers are in segments

    $subscribers_count = $this->subscribers_finder->addSubscribersToTaskFromSegments($queue->task(), $segments);

    if (empty($subscribers_count)) {
      $this->logger_factory->getLogger(LoggerFactory::TOPIC_POST_NOTIFICATIONS)->addInfo(
        'post notification no subscribers',
        ['newsletter_id' => $newsletter->id, 'task_id' => $queue->task_id]
      );
      return $this->deleteQueueOrUpdateNextRunDate($queue, $newsletter);
    }

    // create a duplicate newsletter that acts as a history record
    $notification_history = $this->createNotificationHistory($newsletter->id);
    if (!$notification_history) return false;

    // queue newsletter for delivery
    $queue->newsletter_id = $notification_history->id;
    $queue->status = null;
    $queue->save();
    // update notification status
    $notification_history->setStatus(Newsletter::STATUS_SENDING);
    $this->logger_factory->getLogger(LoggerFactory::TOPIC_POST_NOTIFICATIONS)->addInfo(
      'post notification set status to sending',
      ['newsletter_id' => $newsletter->id, 'task_id' => $queue->task_id]
    );
    $this->reScheduleBounceTask();
    return true;
  }

  function processScheduledAutomaticEmail($newsletter, $queue) {
    if ($newsletter->sendTo === 'segment') {
      $segment = Segment::findOne($newsletter->segment);
      $result = $this->subscribers_finder->addSubscribersToTaskFromSegments($queue->task(), [$segment]);
      if (empty($result)) {
        $queue->delete();
        return false;
      }
    } else {
      $subscribers = $queue->getSubscribers();
      $subscriber = (!empty($subscribers) && is_array($subscribers)) ?
        Subscriber::findOne($subscribers[0]) :
        false;
      if (!$subscriber) {
        $queue->delete();
        return false;
      }
    }

    $queue->status = null;
    $queue->save();
    return true;
  }

  function processScheduledStandardNewsletter($newsletter, SendingTask $task) {
    $segments = $newsletter->segments()->findMany();
    $this->subscribers_finder->addSubscribersToTaskFromSegments($task->task(), $segments);
    // update current queue
    $task->updateCount();
    $task->status = null;
    $task->save();
    // update newsletter status
    $newsletter->setStatus(Newsletter::STATUS_SENDING);
    $this->reScheduleBounceTask();
    return true;
  }

  function verifyMailpoetSubscriber($subscriber_id, $newsletter, $queue) {
    $subscriber = Subscriber::findOne($subscriber_id);
    // check if subscriber is in proper segment
    $subscriber_in_segment =
      SubscriberSegment::where('subscriber_id', $subscriber_id)
        ->where('segment_id', $newsletter->segment)
        ->where('status', 'subscribed')
        ->findOne();
    if (!$subscriber || !$subscriber_in_segment) {
      $queue->delete();
      return false;
    }
    return $this->verifySubscriber($subscriber, $queue);
  }

  function verifyWPSubscriber($subscriber_id, $newsletter, $queue) {
    // check if user has the proper role
    $subscriber = Subscriber::findOne($subscriber_id);
    if (!$subscriber || $subscriber->isWPUser() === false) {
      $queue->delete();
      return false;
    }
    $wp_user = (array)get_userdata($subscriber->wp_user_id);
    if ($newsletter->role !== WelcomeScheduler::WORDPRESS_ALL_ROLES
      && !in_array($newsletter->role, $wp_user['roles'])
    ) {
      $queue->delete();
      return false;
    }
    return $this->verifySubscriber($subscriber, $queue);
  }

  function verifySubscriber($subscriber, $queue) {
    if ($subscriber->status === Subscriber::STATUS_UNCONFIRMED) {
      // reschedule delivery
      $queue->rescheduleProgressively();
      return false;
    } else if ($subscriber->status === Subscriber::STATUS_UNSUBSCRIBED) {
      $queue->delete();
      return false;
    }
    return true;
  }

  function deleteQueueOrUpdateNextRunDate($queue, $newsletter) {
    if ($newsletter->intervalType === PostNotificationScheduler::INTERVAL_IMMEDIATELY) {
      $queue->delete();
      return;
    } else {
      $next_run_date = NewsletterScheduler::getNextRunDate($newsletter->schedule);
      if (!$next_run_date) {
        $queue->delete();
        return;
      }
      $queue->scheduled_at = $next_run_date;
      $queue->save();
    }
  }

  function createNotificationHistory($newsletter_id) {
    $newsletter = Newsletter::findOne($newsletter_id);
    $notification_history = $newsletter->createNotificationHistory();
    return ($notification_history->getErrors() === false) ?
      $notification_history :
      false;
  }

  private function updateTasks(array $scheduled_queues) {
    $ids = array_map(function ($queue) {
      return $queue->task_id;
    }, $scheduled_queues);
    ScheduledTask::touchAllByIds($ids);
  }

  private function reScheduleBounceTask() {
    $bounce_tasks = ScheduledTask::findFutureScheduledByType(Bounce::TASK_TYPE);
    if (count($bounce_tasks)) {
      $bounce_task = reset($bounce_tasks);
      if (Carbon::createFromTimestamp((int)current_time('timestamp'))->addHour(42)->lessThan($bounce_task->scheduled_at)) {
        $random_offset = rand(-6 * 60 * 60, 6 * 60 * 60);
        $bounce_task->scheduled_at = Carbon::createFromTimestamp((int)current_time('timestamp'))->addSecond((36 * 60 * 60) + $random_offset);
        $bounce_task->save();
      }
    }
  }

  static function getScheduledQueues() {
    return SendingTask::getScheduledQueues(self::TASK_BATCH_SIZE);
  }
}
