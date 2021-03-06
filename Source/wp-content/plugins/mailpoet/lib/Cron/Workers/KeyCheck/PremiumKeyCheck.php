<?php

namespace MailPoet\Cron\Workers\KeyCheck;

if (!defined('ABSPATH')) exit;


use MailPoet\Services\Bridge;
use MailPoet\Settings\SettingsController;

class PremiumKeyCheck extends KeyCheckWorker {
  const TASK_TYPE = 'premium_key_check';

  /** @var SettingsController */
  private $settings;

  function __construct(SettingsController $settings) {
    $this->settings = $settings;
    parent::__construct();
  }


  function checkProcessingRequirements() {
    return Bridge::isPremiumKeySpecified();
  }

  function checkKey() {
    $premium_key = $this->settings->get(Bridge::PREMIUM_KEY_SETTING_NAME);
    $result = $this->bridge->checkPremiumKey($premium_key);
    $this->bridge->storePremiumKeyAndState($premium_key, $result);
    return $result;
  }
}
