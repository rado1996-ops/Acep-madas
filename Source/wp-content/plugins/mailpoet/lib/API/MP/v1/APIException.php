<?php

namespace MailPoet\API\MP\v1;

if (!defined('ABSPATH')) exit;


class APIException extends \Exception {
  const FAILED_TO_SAVE_SUBSCRIBER_FIELD = 1;
  const SEGMENT_REQUIRED = 3;
  const SUBSCRIBER_NOT_EXISTS = 4;
  const LIST_NOT_EXISTS = 5;
  const SUBSCRIBING_TO_WP_LIST_NOT_ALLOWED = 6;
  const SUBSCRIBING_TO_WC_LIST_NOT_ALLOWED = 7;
  const SUBSCRIBING_TO_LIST_NOT_ALLOWED = 8;
  const CONFIRMATION_FAILED_TO_SEND = 10;
  const EMAIL_ADDRESS_REQUIRED = 11;
  const SUBSCRIBER_EXISTS = 12;
  const FAILED_TO_SAVE_SUBSCRIBER = 13;
  const LIST_NAME_REQUIRED = 14;
  const LIST_EXISTS = 15;
  const FAILED_TO_SAVE_LIST = 16;
  const WELCOME_FAILED_TO_SEND = 17;
}
