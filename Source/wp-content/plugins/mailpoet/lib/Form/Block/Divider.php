<?php

namespace MailPoet\Form\Block;

if (!defined('ABSPATH')) exit;


class Divider {

  static function render() {
    return '<hr class="mailpoet_divider" />';
  }
}