<?php

namespace MailPoet\Twig;

if (!defined('ABSPATH')) exit;


use MailPoet\Config\ServicesChecker;
use MailPoet\Referrals\UrlDecorator;
use MailPoet\Settings\SettingsController;
use MailPoet\Util\FreeDomains;
use MailPoet\WooCommerce\Helper as WooCommerceHelper;
use MailPoet\WP\Functions as WPFunctions;
use MailPoetVendor\Carbon\Carbon;
use MailPoetVendor\Twig\Extension\AbstractExtension;
use MailPoetVendor\Twig\TwigFunction;

class Functions extends AbstractExtension {

  /** @var SettingsController */
  private $settings;

  /** @var WooCommerceHelper */
  private $woocommerce_helper;

  /** @var WPFunctions */
  private $wp;

  /** @var UrlDecorator */
  private $referral_url_decorator;

  public function __construct() {
    $this->settings = SettingsController::getInstance();
    $this->woocommerce_helper = new WooCommerceHelper();
    $this->wp = WPFunctions::get();
    $this->referral_url_decorator = new UrlDecorator($this->wp, $this->settings);
  }

  function getFunctions() {
    return [
      new TwigFunction(
        'json_encode',
        'json_encode',
        ['is_safe' => ['all']]
      ),
      new TwigFunction(
        'json_decode',
        'json_decode',
        ['is_safe' => ['all']]
      ),
      new TwigFunction(
        'wp_nonce_field',
        'wp_nonce_field',
        ['is_safe' => ['all']]
      ),
      new TwigFunction(
        'params',
        [$this, 'params'],
        ['is_safe' => ['all']]
      ),
      new TwigFunction(
        'admin_url',
        'admin_url',
        ['is_safe' => ['all']]
      ),
      new TwigFunction(
        'get_option',
        'get_option',
        ['is_safe' => ['all']]
      ),
      new TwigFunction(
        'sending_frequency',
        [$this, 'getSendingFrequency'],
        ['is_safe' => ['all']]
      ),
      new TwigFunction(
        'wp_date_format',
        [$this, 'getWPDateFormat'],
        ['is_safe' => ['all']]
      ),
      new TwigFunction(
        'mailpoet_version',
        [$this, 'getMailPoetVersion'],
        ['is_safe' => ['all']]
      ),
      new TwigFunction(
        'mailpoet_premium_version',
        [$this, 'getMailPoetPremiumVersion'],
        ['is_safe' => ['all']]
      ),
      new TwigFunction(
        'wp_time_format',
        [$this, 'getWPTimeFormat'],
        ['is_safe' => ['all']]
      ),
      new TwigFunction(
        'wp_datetime_format',
        [$this, 'getWPDateTimeFormat'],
        ['is_safe' => ['all']]
      ),
      new TwigFunction(
        'do_action',
        'do_action',
        ['is_safe' => ['all']]
      ),
      new TwigFunction(
        'is_rtl',
        [$this, 'isRtl'],
        ['is_safe' => ['all']]
      ),
      new TwigFunction(
        'number_format_i18n',
        'number_format_i18n',
        ['is_safe' => ['all']]
      ),
      new TwigFunction(
        'mailpoet_locale',
        [$this, 'getTwoLettersLocale'],
        ['is_safe' => ['all']]
      ),
      new TwigFunction(
        'mailpoet_free_domains',
        [$this, 'getFreeDomains'],
        ['is_safe' => ['all']]
      ),
      new TwigFunction(
        'is_woocommerce_active',
        [$this, 'isWoocommerceActive'],
        ['is_safe' => ['all']]
      ),
      new TwigFunction(
        'wp_start_of_week',
        [$this, 'getWPStartOfWeek'],
        ['is_safe' => ['all']]
      ),
      new TwigFunction(
        'opened_stats_color',
        [$this, 'openedStatsColor'],
        ['is_safe' => ['all']]
      ),
      new TwigFunction(
        'clicked_stats_color',
        [$this, 'clickedStatsColor'],
        ['is_safe' => ['all']]
      ),
      new TwigFunction(
        'opened_stats_text',
        [$this, 'openedStatsText'],
        ['is_safe' => ['all']]
      ),
      new TwigFunction(
        'clicked_stats_text',
        [$this, 'clickedStatsText'],
        ['is_safe' => ['all']]
      ),
      new TwigFunction(
        'add_referral_id',
        [$this, 'addReferralId'],
        ['is_safe' => ['all']]
      ),
    ];
  }

  function getSendingFrequency() {
    $args = func_get_args();
    $value = (int)array_shift($args);

    $label = null;
    $labels = [
      'minute' => $this->wp->__('every minute', 'mailpoet'),
      'minutes' => $this->wp->__('every %1$d minutes', 'mailpoet'),
      'hour' => $this->wp->__('every hour', 'mailpoet'),
      'hours' => $this->wp->__('every %1$d hours', 'mailpoet'),
    ];

    if ($value >= 60) {
      // we're dealing with hours
      if ($value === 60) {
        $label = $labels['hour'];
      } else {
        $label = $labels['hours'];
      }
      $value /= 60;
    } else {
      // we're dealing with minutes
      if ($value === 1) {
        $label = $labels['minute'];
      } else {
        $label = $labels['minutes'];
      }
    }

    if ($label !== null) {
      return sprintf($label, $value);
    } else {
      return $value;
    }
  }

  function getWPDateFormat() {
    return $this->wp->getOption('date_format') ?: 'F j, Y';
  }

  function getWPStartOfWeek() {
    return $this->wp->getOption('start_of_week') ?: 0;
  }

  function getMailPoetVersion() {
    return MAILPOET_VERSION;
  }

  function getMailPoetPremiumVersion() {
    return (defined('MAILPOET_PREMIUM_VERSION')) ? MAILPOET_PREMIUM_VERSION : false;
  }

  function getWPTimeFormat() {
    return $this->wp->getOption('time_format') ?: 'g:i a';
  }

  function getWPDateTimeFormat() {
    return sprintf('%s %s', $this->getWPDateFormat(), $this->getWPTimeFormat());
  }

  function params($key = null) {
    $args = $this->wp->stripslashesDeep($_GET);
    if (array_key_exists($key, $args)) {
      return $args[$key];
    }
    return null;
  }

  function installedInLastTwoWeeks() {
    $max_number_of_weeks = 2;
    $installed_at = Carbon::createFromFormat('Y-m-d H:i:s', $this->settings->get('installed_at'));
    return $installed_at->diffInWeeks(Carbon::now()) < $max_number_of_weeks;
  }

  function isRtl() {
    return $this->wp->isRtl();
  }

  function getTwoLettersLocale() {
    return explode('_', $this->wp->getLocale())[0];
  }

  function getFreeDomains() {
    return FreeDomains::FREE_DOMAINS;
  }

  function isWoocommerceActive() {
    return $this->woocommerce_helper->isWooCommerceActive();
  }

  function openedStatsColor($opened) {
    if ($opened > 30) {
      return '#2993ab';
    } elseif ($opened > 10) {
      return '#f0b849';
    } else {
      return '#d54e21';
    }
  }

  function clickedStatsColor($clicked) {
    if ($clicked > 3) {
      return '#2993ab';
    } elseif ($clicked > 1) {
      return '#f0b849';
    } else {
      return '#d54e21';
    }
  }

  function openedStatsText($opened) {
    if ($opened > 30) {
      return __('EXCELLENT', 'mailpoet');
    } elseif ($opened > 10) {
      return __('GOOD', 'mailpoet');
    } else {
      return __('BAD', 'mailpoet');
    }
  }

  function clickedStatsText($clicked) {
    if ($clicked > 3) {
      return __('EXCELLENT', 'mailpoet');
    } elseif ($clicked > 1) {
      return __('GOOD', 'mailpoet');
    } else {
      return __('BAD', 'mailpoet');
    }
  }

  function addReferralId($url) {
    return $this->referral_url_decorator->decorate($url);
  }
}
