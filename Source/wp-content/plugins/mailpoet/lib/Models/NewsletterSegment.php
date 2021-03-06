<?php

namespace MailPoet\Models;

if (!defined('ABSPATH')) exit;


/**
 * @property int $newsletter_id
 * @property int $segment_id
 * @property string $updated_at
 */
class NewsletterSegment extends Model {
  public static $_table = MP_NEWSLETTER_SEGMENT_TABLE;
}
