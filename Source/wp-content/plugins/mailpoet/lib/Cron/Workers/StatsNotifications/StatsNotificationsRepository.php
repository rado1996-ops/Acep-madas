<?php

namespace MailPoet\Cron\Workers\StatsNotifications;

if (!defined('ABSPATH')) exit;


use MailPoet\Doctrine\Repository;
use MailPoet\Entities\NewsletterEntity;
use MailPoet\Entities\ScheduledTaskEntity;
use MailPoet\Entities\StatsNotificationEntity;
use MailPoetVendor\Carbon\Carbon;

class StatsNotificationsRepository extends Repository {
  protected function getEntityClassName() {
    return StatsNotificationEntity::class;
  }

  /**
   * @param int $newsletter_id
   * @return StatsNotificationEntity|null
   */
  public function findOneByNewsletterId($newsletter_id) {
    return $this->doctrine_repository
      ->createQueryBuilder('sn')
      ->andWhere('sn.newsletter = :newsletterId')
      ->setParameter('newsletterId', $newsletter_id)
      ->setMaxResults(1)
      ->getQuery()
      ->getOneOrNullResult();
  }

  /**
   * @param int|null $limit
   * @return StatsNotificationEntity[]
   */
  public function findScheduled($limit = null) {
    $date = new Carbon();
    $query = $this->doctrine_repository
      ->createQueryBuilder('sn')
      ->join('sn.task', 'tasks')
      ->join('sn.newsletter', 'n')
      ->addSelect('tasks')
      ->addSelect('n')
      ->addOrderBy('tasks.priority')
      ->addOrderBy('tasks.updated_at')
      ->where('tasks.deleted_at IS NULL')
      ->andWhere('tasks.status = :status')
      ->setParameter('status', ScheduledTaskEntity::STATUS_SCHEDULED)
      ->andWhere('tasks.scheduled_at < :date')
      ->setParameter('date', $date)
      ->andWhere('tasks.type = :workerType')
      ->setParameter('workerType', Worker::TASK_TYPE);
    if (is_int($limit)) {
      $query->setMaxResults($limit);
    }
    return $query->getQuery()->getResult();
  }

}

