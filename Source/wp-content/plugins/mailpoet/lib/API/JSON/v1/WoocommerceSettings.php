<?php

namespace MailPoet\API\JSON\v1;

if (!defined('ABSPATH')) exit;


use MailPoet\API\JSON\Endpoint as APIEndpoint;
use MailPoet\Config\AccessControl;
use MailPoet\WP\Functions as WPFunctions;

class WoocommerceSettings extends APIEndpoint {
  public $permissions = [
    'global' => AccessControl::PERMISSION_MANAGE_EMAILS,
  ];

  private $allowed_settings = [
    'woocommerce_email_base_color',
  ];

  /** @var WPFunctions */
  private $wp;

  function __construct(WPFunctions $wp) {
    $this->wp = $wp;
  }

  function set($data = []) {
    foreach ($data as $option => $value) {
      if (in_array($option, $this->allowed_settings)) {
        $this->wp->updateOption($option, $value);
      }
    }
    return $this->successResponse([]);
  }
}
