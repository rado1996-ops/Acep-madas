<?php

namespace MailPoet\AdminPages\Pages;

if (!defined('ABSPATH')) exit;


use MailPoet\AdminPages\PageRenderer;
use MailPoet\Config\Menu;
use MailPoet\Config\MP2Migrator;
use MailPoet\Features\FeaturesController;
use MailPoet\Models\Subscriber;
use MailPoet\Services\Bridge;
use MailPoet\Settings\SettingsController;
use MailPoet\WooCommerce\Helper as WooCommerceHelper;
use MailPoet\WP\Functions as WPFunctions;

class WelcomeWizard {
  /** @var PageRenderer */
  private $page_renderer;

  /** @var SettingsController */
  private $settings;

  /** @var WooCommerceHelper */
  private $woocommerce_helper;

  /** @var WPFunctions */
  private $wp;

  /** @var FeaturesController */
  private $features_controller;

  function __construct(
    PageRenderer $page_renderer,
    SettingsController $settings,
    WooCommerceHelper $woocommerce_helper,
    WPFunctions $wp,
    FeaturesController $features_controller
  ) {
    $this->page_renderer = $page_renderer;
    $this->settings = $settings;
    $this->woocommerce_helper = $woocommerce_helper;
    $this->wp = $wp;
    $this->features_controller = $features_controller;
  }

  function render() {
    if ((bool)(defined('DOING_AJAX') && DOING_AJAX)) return;
    $data = [
      'is_mp2_migration_complete' => (bool)$this->settings->get(MP2Migrator::MIGRATION_COMPLETE_SETTING_KEY),
      'is_woocommerce_active' => $this->woocommerce_helper->isWooCommerceActive(),
      'finish_wizard_url' => $this->wp->adminUrl('admin.php?page=' . Menu::MAIN_PAGE_SLUG),
      'sender' => $this->settings->get('sender'),
      'admin_email' => $this->wp->getOption('admin_email'),
      'subscribers_count' => Subscriber::getTotalSubscribers(),
      'has_mss_key_specified' => Bridge::isMSSKeySpecified(),
    ];
    $data['mailpoet_feature_flags'] = $this->features_controller->getAllFlags();
    $this->page_renderer->displayPage('welcome_wizard.html', $data);
  }
}
