<?php

namespace MailPoet\Models;

if (!defined('ABSPATH')) exit;


/**
 * @property int $newsletter_id
 * @property int $subscriber_id
 * @property int $queue_id
 */
class StatisticsUnsubscribes extends Model {
  public static $_table = MP_STATISTICS_UNSUBSCRIBES_TABLE;

  static function getOrCreate($subscriber_id, $newsletter_id, $queue_id) {
    $statistics = self::where('subscriber_id', $subscriber_id)
      ->where('newsletter_id', $newsletter_id)
      ->where('queue_id', $queue_id)
      ->findOne();
    if (!$statistics) {
      $statistics = self::create();
      $statistics->subscriber_id = $subscriber_id;
      $statistics->newsletter_id = $newsletter_id;
      $statistics->queue_id = $queue_id;
      $statistics->save();
    }
    return $statistics;
  }
}
