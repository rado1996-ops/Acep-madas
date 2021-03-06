<?php

namespace MailPoet\Cron\Workers\SendingQueue\Tasks;

if (!defined('ABSPATH')) exit;


use MailPoet\Newsletter\Shortcodes\Shortcodes as NewsletterShortcodes;

class Shortcodes {
  static function process($content, $content_source = null, $newsletter = null, $subscriber = null, $queue = null) {
    $shortcodes = new NewsletterShortcodes($newsletter, $subscriber, $queue);
    return $shortcodes->replace($content, $content_source);
  }
}
