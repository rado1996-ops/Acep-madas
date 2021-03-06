<?php

namespace MailPoet\Subscribers;

if (!defined('ABSPATH')) exit;


use MailPoet\Config\MP2Migrator;
use MailPoet\Models\ScheduledTask;
use MailPoet\Models\ScheduledTaskSubscriber;
use MailPoet\Models\SendingQueue;
use MailPoet\Models\StatisticsOpens;
use MailPoet\Models\Subscriber;
use MailPoet\Settings\SettingsRepository;
use MailPoetVendor\Carbon\Carbon;
use MailPoetVendor\Idiorm\ORM;

class InactiveSubscribersController {

  private $inactives_task_ids_table_created = false;

  /** @var SettingsRepository */
  private $settings_repository;

  function __construct(SettingsRepository $settings_repository) {
    $this->settings_repository = $settings_repository;
  }

  /**
   * @param int $days_to_inactive
   * @param int $batch_size
   * @return int|boolean
   */
  function markInactiveSubscribers($days_to_inactive, $batch_size, $start_id = null) {
    $threshold_date = $this->getThresholdDate($days_to_inactive);
    return $this->deactivateSubscribers($threshold_date, $batch_size, $start_id);
  }

  /**
   * @param int $days_to_inactive
   * @param int $batch_size
   * @return int
   */
  function markActiveSubscribers($days_to_inactive, $batch_size) {
    $threshold_date = $this->getThresholdDate($days_to_inactive);
    return $this->activateSubscribers($threshold_date, $batch_size);
  }

  /**
   * @return void
   */
  function reactivateInactiveSubscribers() {
    $reactivate_all_inactive_query = sprintf(
      "UPDATE %s SET status = '%s' WHERE status = '%s';",
      Subscriber::$_table, Subscriber::STATUS_SUBSCRIBED, Subscriber::STATUS_INACTIVE
    );
    ORM::rawExecute($reactivate_all_inactive_query);
  }

  /**
   * @param int $days_to_inactive
   * @return Carbon
   */
  private function getThresholdDate($days_to_inactive) {
    $now = new Carbon();
    return $now->subDays($days_to_inactive);
  }

  /**
   * @param Carbon $threshold_date
   * @param int $batch_size
   * @return int|boolean
   */
  private function deactivateSubscribers(Carbon $threshold_date, $batch_size, $start_id = null) {
    $subscribers_table = Subscriber::$_table;
    $scheduled_tasks_table = ScheduledTask::$_table;
    $scheduled_task_subcribres_table = ScheduledTaskSubscriber::$_table;
    $statistics_opens_table = StatisticsOpens::$_table;
    $sending_queues_table = SendingQueue::$_table;

    $threshold_date_iso = $threshold_date->toDateTimeString();
    $day_ago = new Carbon();
    $day_ago_iso = $day_ago->subDay()->toDateTimeString();

    // If MP2 migration occurred during detection interval we can't deactivate subscribers
    // because they are imported with original subscription date but they were not present in a list for whole period
    $mp2_migration_date = $this->getMP2MigrationDate();
    if ($mp2_migration_date && $mp2_migration_date > $threshold_date) {
      return false;
    }

    // We take into account only emails which have at least one opening tracked
    // to ensure that tracking was enabled for the particular email
    if (!$this->inactives_task_ids_table_created) {
      $inactives_task_ids_table = sprintf("
      CREATE TEMPORARY TABLE IF NOT EXISTS inactives_task_ids
      (INDEX task_id_ids (id))
      SELECT DISTINCT task_id as id FROM $sending_queues_table as sq
        JOIN $scheduled_tasks_table as st ON sq.task_id = st.id
        WHERE st.processed_at > '%s'
        AND st.processed_at < '%s'
        AND EXISTS (
          SELECT 1
          FROM $statistics_opens_table as so
          WHERE so.created_at > '%s'
          AND so.newsletter_id = sq.newsletter_id
        )",
        $threshold_date_iso, $day_ago_iso, $threshold_date_iso
      );
      ORM::rawExecute($inactives_task_ids_table);
      $this->inactives_task_ids_table_created = true;
    }

    // Select subscribers who received a recent tracked email but didn't open it
    $start_id = (int)$start_id;
    $end_id = $start_id + $batch_size;
    $inactive_subscriber_ids_tmp_table = 'inactive_subscriber_ids';
    ORM::rawExecute("
      CREATE TEMPORARY TABLE IF NOT EXISTS $inactive_subscriber_ids_tmp_table
      (UNIQUE subscriber_id (id))
      SELECT DISTINCT s.id FROM $subscribers_table as s
        JOIN $scheduled_task_subcribres_table as sts USE INDEX (subscriber_id) ON s.id = sts.subscriber_id
        JOIN inactives_task_ids task_ids ON task_ids.id = sts.task_id
      WHERE s.last_subscribed_at < ? AND s.status = ? AND s.id >= ? AND s.id < ?",
      [$threshold_date_iso, Subscriber::STATUS_SUBSCRIBED, $start_id, $end_id]
    );

    $ids_to_deactivate = ORM::forTable($inactive_subscriber_ids_tmp_table)->rawQuery("
      SELECT s.id FROM $inactive_subscriber_ids_tmp_table s
        LEFT OUTER JOIN $statistics_opens_table as so ON s.id = so.subscriber_id AND so.created_at > ?
        WHERE so.id IS NULL",
      [$threshold_date_iso]
    )->findArray();

    ORM::rawExecute("DROP TABLE $inactive_subscriber_ids_tmp_table");

    $ids_to_deactivate = array_map(
      function ($id) {
        return (int)$id['id'];
      },
      $ids_to_deactivate
    );
    if (!count($ids_to_deactivate)) {
      return 0;
    }
    ORM::rawExecute(sprintf(
      "UPDATE %s SET status='" . Subscriber::STATUS_INACTIVE . "' WHERE id IN (%s);",
      $subscribers_table,
      implode(',', $ids_to_deactivate)
    ));
    return count($ids_to_deactivate);
  }

  /**
   * @param Carbon $threshold_date
   * @param int $batch_size
   * @return int
   */
  private function activateSubscribers(Carbon $threshold_date, $batch_size) {
    $subscribers_table = Subscriber::$_table;
    $stats_opens_table = StatisticsOpens::$_table;

    $mp2_migration_date = $this->getMP2MigrationDate();
    if ($mp2_migration_date && $mp2_migration_date > $threshold_date) {
      // If MP2 migration occurred during detection interval re-activate all subscribers created before migration
      $ids_to_activate = ORM::forTable($subscribers_table)->select("$subscribers_table.id")
        ->whereLt("$subscribers_table.created_at", $mp2_migration_date)
        ->where("$subscribers_table.status", Subscriber::STATUS_INACTIVE)
        ->limit($batch_size)
        ->findArray();
    } else {
      $ids_to_activate = ORM::forTable($subscribers_table)->select("$subscribers_table.id")
        ->leftOuterJoin($stats_opens_table, "$subscribers_table.id = $stats_opens_table.subscriber_id AND $stats_opens_table.created_at > '$threshold_date'")
        ->whereLt("$subscribers_table.last_subscribed_at", $threshold_date)
        ->where("$subscribers_table.status", Subscriber::STATUS_INACTIVE)
        ->whereRaw("$stats_opens_table.id IS NOT NULL")
        ->limit($batch_size)
        ->groupByExpr("$subscribers_table.id")
        ->findArray();
    }

    $ids_to_activate = array_map(
      function($id) {
        return (int)$id['id'];
      }, $ids_to_activate
    );
    if (!count($ids_to_activate)) {
      return 0;
    }
    ORM::rawExecute(sprintf(
      "UPDATE %s SET status='" . Subscriber::STATUS_SUBSCRIBED . "' WHERE id IN (%s);",
      $subscribers_table,
      implode(',', $ids_to_activate)
    ));
    return count($ids_to_activate);
  }

  private function getMP2MigrationDate() {
    $setting = $this->settings_repository->findOneByName(MP2Migrator::MIGRATION_COMPLETE_SETTING_KEY);
    return $setting ? Carbon::instance($setting->getCreatedAt()) : null;
  }
}
