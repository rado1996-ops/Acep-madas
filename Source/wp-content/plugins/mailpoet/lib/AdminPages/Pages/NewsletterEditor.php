<?php

namespace MailPoet\AdminPages\Pages;

if (!defined('ABSPATH')) exit;


use MailPoet\AdminPages\PageRenderer;
use MailPoet\Config\Menu;
use MailPoet\Models\Subscriber;
use MailPoet\Newsletter\Shortcodes\ShortcodesHelper;
use MailPoet\Services\Bridge;
use MailPoet\Settings\SettingsController;
use MailPoet\Settings\UserFlagsController;
use MailPoet\WooCommerce\Helper as WooCommerceHelper;
use MailPoet\WooCommerce\TransactionalEmails;
use MailPoet\WP\Functions as WPFunctions;

class NewsletterEditor {
  /** @var PageRenderer */
  private $page_renderer;

  /** @var SettingsController */
  private $settings;

  /** @var UserFlagsController */
  private $user_flags;

  /** @var WooCommerceHelper */
  private $woocommerce_helper;

  /** @var WPFunctions */
  private $wp;

  /** @var TransactionalEmails */
  private $wc_transactional_emails;

  function __construct(
    PageRenderer $page_renderer,
    SettingsController $settings,
    UserFlagsController $user_flags,
    WooCommerceHelper $woocommerce_helper,
    WPFunctions $wp,
    TransactionalEmails $wc_transactional_emails
  ) {
    $this->page_renderer = $page_renderer;
    $this->settings = $settings;
    $this->user_flags = $user_flags;
    $this->woocommerce_helper = $woocommerce_helper;
    $this->wp = $wp;
    $this->wc_transactional_emails = $wc_transactional_emails;
  }

  function render() {
    $newsletter_id = (isset($_GET['id']) ? (int)$_GET['id'] : 0);
    $woocommerce_template_id = (int)$this->settings->get(TransactionalEmails::SETTING_EMAIL_ID, null);
    if (
      $woocommerce_template_id
      && $newsletter_id === $woocommerce_template_id
      && !$this->woocommerce_helper->isWooCommerceActive()
    ) {
      $location = 'admin.php?page=mailpoet-settings&enable-customizer-notice#woocommerce';
      if (headers_sent()) {
        echo '<script>window.location = "' . $location . '";</script>';
      } else {
        header('Location: ' . $location, true, 302);
      }
      exit;
    }

    $subscriber = Subscriber::getCurrentWPUser();
    $subscriber_data = $subscriber ? $subscriber->asArray() : [];
    $woocommerce_data = [];
    if ($this->woocommerce_helper->isWooCommerceActive()) {
      $email_base_color = $this->wp->getOption('woocommerce_email_base_color', '#ffffff');
      $woocommerce_data = [
        'email_headings' => $this->wc_transactional_emails->getEmailHeadings(),
        'email_base_color' => $email_base_color,
        'email_base_text_color' => $this->woocommerce_helper->wcLightOrDark($email_base_color, '#202020', '#ffffff'),
        'email_text_color' => $this->wp->getOption('woocommerce_email_text_color', '#000000'),
        'customizer_enabled' => (bool)$this->settings->get('woocommerce.use_mailpoet_editor'),
      ];
    }
    $data = [
      'shortcodes' => ShortcodesHelper::getShortcodes(),
      'settings' => $this->settings->getAll(),
      'editor_tutorial_seen' => $this->user_flags->get('editor_tutorial_seen'),
      'current_wp_user' => array_merge($subscriber_data, $this->wp->wpGetCurrentUser()->to_array()),
      'sub_menu' => Menu::MAIN_PAGE_SLUG,
      'mss_active' => Bridge::isMPSendingServiceEnabled(),
      'woocommerce' => $woocommerce_data,
      'is_wc_transactional_email' => $newsletter_id === $woocommerce_template_id,
      'site_name' => $this->wp->wpSpecialcharsDecode($this->wp->getOption('blogname'), ENT_QUOTES),
      'site_address' => $this->wp->wpParseUrl($this->wp->homeUrl(), PHP_URL_HOST),
    ];
    $this->wp->wpEnqueueMedia();
    $this->wp->wpEnqueueStyle('editor', $this->wp->includesUrl('css/editor.css'));

    $this->page_renderer->displayPage('newsletter/editor.html', $data);
  }
}
