<?php

namespace MailPoet\Twig;

if (!defined('ABSPATH')) exit;


use MailPoet\Settings\SettingsController;
use MailPoetVendor\Twig\Extension\AbstractExtension;
use MailPoetVendor\Twig\TwigFunction;

class Polls extends AbstractExtension {

  /** @var SettingsController */
  private $settings;

  public function __construct() {
    $this->settings = SettingsController::getInstance();
  }

  public function getFunctions() {
    return [
      new TwigFunction(
        'get_polls_data',
        [$this, 'getPollsData'],
        ['is_safe' => ['all']]
      ),
      new TwigFunction(
        'get_polls_visiblity',
        [$this, 'getPollsVisibility'],
        ['is_safe' => ['all']]
      ),
    ];
  }

  function getPollsData() {
    return [
      'mta_method' => $this->settings->get('mta.method'),
    ];
  }

  function getPollsVisibility() {
    return [
      'show_poll_success_delivery_preview' => $this->settings->get('show_poll_success_delivery_preview'),
    ];
  }
}
