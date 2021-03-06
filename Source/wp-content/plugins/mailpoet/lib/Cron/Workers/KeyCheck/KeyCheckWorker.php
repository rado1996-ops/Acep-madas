<?php

namespace MailPoet\Cron\Workers\KeyCheck;

if (!defined('ABSPATH')) exit;


use MailPoet\Cron\Workers\SimpleWorker;
use MailPoet\Models\ScheduledTask;
use MailPoet\Services\Bridge;

abstract class KeyCheckWorker extends SimpleWorker {
  public $bridge;

  function init() {
    if (!$this->bridge) {
      $this->bridge = new Bridge();
    }
  }

  function processTaskStrategy(ScheduledTask $task, $timer) {
    try {
      $result = $this->checkKey();
    } catch (\Exception $e) {
      $result = false;
    }

    if (empty($result['code']) || $result['code'] == Bridge::CHECK_ERROR_UNAVAILABLE) {
      $task->rescheduleProgressively();
      return false;
    }

    return true;
  }

  abstract function checkKey();
}
