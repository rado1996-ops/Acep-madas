<?php

namespace MailPoet\Models;

if (!defined('ABSPATH')) exit;


use MailPoet\Entities\ScheduledTaskEntity;
use MailPoet\Util\Helpers;
use MailPoet\WP\Functions as WPFunctions;
use MailPoetVendor\Carbon\Carbon;
use MailPoetVendor\Idiorm\ORM;

/**
 * @property int $id
 * @property string $processed_at
 * @property string|null $status
 * @property string|null $type
 * @property int $priority
 * @property string|null $scheduled_at
 * @property boolean|null $in_progress
 * @property int $reschedule_count
 * @property string|array|null $meta
 */
class ScheduledTask extends Model {
  public static $_table = MP_SCHEDULED_TASKS_TABLE;
  const STATUS_COMPLETED = ScheduledTaskEntity::STATUS_COMPLETED;
  const STATUS_SCHEDULED = ScheduledTaskEntity::STATUS_SCHEDULED;
  const STATUS_PAUSED = ScheduledTaskEntity::STATUS_PAUSED;
  const VIRTUAL_STATUS_RUNNING = ScheduledTaskEntity::VIRTUAL_STATUS_RUNNING; // For historical reasons this is stored as null in DB
  const PRIORITY_HIGH = ScheduledTaskEntity::PRIORITY_HIGH;
  const PRIORITY_MEDIUM = ScheduledTaskEntity::PRIORITY_MEDIUM;
  const PRIORITY_LOW = ScheduledTaskEntity::PRIORITY_LOW;

  const BASIC_RESCHEDULE_TIMEOUT = 5; //minutes
  const MAX_RESCHEDULE_TIMEOUT = 1440; //minutes

  private $wp;

  function __construct() {
    parent::__construct();
    $this->wp = new WPFunctions();
  }

  function subscribers() {
    return $this->hasManyThrough(
      __NAMESPACE__ . '\Subscriber',
      __NAMESPACE__ . '\ScheduledTaskSubscriber',
      'task_id',
      'subscriber_id'
    );
  }

  function pause() {
    $this->set('status', self::STATUS_PAUSED);
    $this->save();
    return ($this->getErrors() === false && $this->id() > 0);
  }

  static function pauseAllByNewsletter(Newsletter $newsletter) {
    ScheduledTask::rawExecute(
      'UPDATE `' . ScheduledTask::$_table . '` t ' .
      'JOIN `' . SendingQueue::$_table . '` q ON t.`id` = q.`task_id` ' .
      'SET t.`status` = "' . self::STATUS_PAUSED . '" ' .
      'WHERE ' .
      'q.`newsletter_id` = ' . $newsletter->id() .
      ' AND t.`status` = "' . self::STATUS_SCHEDULED . '" '
    );
  }

  function resume() {
    $this->setExpr('status', 'NULL');
    $this->save();
    return ($this->getErrors() === false && $this->id() > 0);
  }

  static function setScheduledAllByNewsletter(Newsletter $newsletter) {
    ScheduledTask::rawExecute(
      'UPDATE `' . ScheduledTask::$_table . '` t ' .
      'JOIN `' . SendingQueue::$_table . '` q ON t.`id` = q.`task_id` ' .
      'SET t.`status` = "' . self::STATUS_SCHEDULED . '" ' .
      'WHERE ' .
      'q.`newsletter_id` = ' . $newsletter->id() .
      ' AND t.`status` = "' . self::STATUS_PAUSED . '" ' .
      ' AND t.`scheduled_at` > NOW()'
    );
  }

  function complete() {
    $this->processed_at = $this->wp->currentTime('mysql');
    $this->set('status', self::STATUS_COMPLETED);
    $this->save();
    return ($this->getErrors() === false && $this->id() > 0);
  }

  function save() {
    // set the default priority to medium
    if (!$this->priority) {
      $this->priority = self::PRIORITY_MEDIUM;
    }
    if (!is_null($this->meta) && !Helpers::isJson($this->meta)) {
      $this->set(
        'meta',
        json_encode($this->meta)
      );
    }
    parent::save();
    return $this;
  }

  function asArray() {
    $model = parent::asArray();
    $model['meta'] = $this->getMeta();
    return $model;
  }

  function getMeta() {
    $meta = (Helpers::isJson($this->meta)) ? json_decode($this->meta, true) : $this->meta;
    return !empty($meta) ? (array)$meta : [];
  }

  function delete() {
    try {
      ORM::get_db()->beginTransaction();
      ScheduledTaskSubscriber::where('task_id', $this->id)->deleteMany();
      parent::delete();
      ORM::get_db()->commit();
    } catch (\Exception $error) {
      ORM::get_db()->rollBack();
      throw $error;
    }
  }

  function rescheduleProgressively() {
    $scheduled_at = Carbon::createFromTimestamp($this->wp->currentTime('timestamp'));
    $timeout = (int)min(self::BASIC_RESCHEDULE_TIMEOUT * pow(2, $this->reschedule_count), self::MAX_RESCHEDULE_TIMEOUT);
    $this->scheduled_at = $scheduled_at->addMinutes($timeout);
    $this->reschedule_count++;
    $this->status = ScheduledTask::STATUS_SCHEDULED;
    $this->save();
    return $timeout;
  }

  static function touchAllByIds(array $ids) {
    ScheduledTask::rawExecute(
      'UPDATE `' . ScheduledTask::$_table . '`' .
      'SET `updated_at` = NOW() ' .
      'WHERE `id` IN (' . join(',', $ids) . ')'
    );
  }

  /**
   * @return ScheduledTask|null
   */
  static function findOneScheduledByNewsletterIdAndSubscriberId($newsletter_id, $subscriber_id) {
    return ScheduledTask::tableAlias('tasks')
      ->select('tasks.*')
      ->innerJoin(SendingQueue::$_table, 'queues.task_id = tasks.id', 'queues')
      ->innerJoin(ScheduledTaskSubscriber::$_table, 'task_subscribers.task_id = tasks.id', 'task_subscribers')
      ->where('queues.newsletter_id', $newsletter_id)
      ->where('tasks.status', ScheduledTask::STATUS_SCHEDULED)
      ->where('task_subscribers.subscriber_id', $subscriber_id)
      ->whereNull('queues.deleted_at')
      ->whereNull('tasks.deleted_at')
      ->findOne() ?: null;
  }

  static function findDueByType($type, $limit = null) {
    return self::findByTypeAndStatus($type, ScheduledTask::STATUS_SCHEDULED, $limit);
  }

  static function findRunningByType($type, $limit = null) {
    return self::findByTypeAndStatus($type, null, $limit);
  }

  static function findFutureScheduledByType($type, $limit = null) {
    return self::findByTypeAndStatus($type, ScheduledTask::STATUS_SCHEDULED, $limit, true);
  }

  static function findCompletedByType($type, $limit = null) {
    return self::findByTypeAndStatus($type, ScheduledTask::STATUS_COMPLETED, $limit);
  }

  private static function findByTypeAndStatus($type, $status, $limit = null, $future = false) {
    $query = ScheduledTask::where('type', $type)
      ->whereNull('deleted_at');

    $now = Carbon::createFromTimestamp(WPFunctions::get()->currentTime('timestamp'));
    if ($future) {
      $query->whereGt('scheduled_at', $now);
    } else {
      $query->whereLte('scheduled_at', $now);
    }

    if ($status === null) {
      $query->whereNull('status');
    } else {
      $query->where('status', $status);
    }

    if ($limit !== null) {
      $query->limit($limit);
    }

    return $query->findMany();
  }
}
