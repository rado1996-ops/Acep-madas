<?php

namespace MailPoet\API\JSON;

if (!defined('ABSPATH')) exit;


use MailPoet\Config\AccessControl;
use MailPoet\WP\Functions as WPFunctions;

abstract class Endpoint {
  const TYPE_POST = 'POST';
  const TYPE_GET = 'GET';

  public $permissions = [
    'global' => AccessControl::PERMISSION_MANAGE_SETTINGS,
    'methods' => [],
  ];

  protected static $get_methods = [];

  function successResponse(
    $data = [], $meta = [], $status = Response::STATUS_OK
  ) {
    return new SuccessResponse($data, $meta, $status);
  }

  function errorResponse(
    $errors = [], $meta = [], $status = Response::STATUS_NOT_FOUND
  ) {
    if (empty($errors)) {
      $errors = [
        Error::UNKNOWN => WPFunctions::get()->__('An unknown error occurred.', 'mailpoet'),
      ];
    }
    return new ErrorResponse($errors, $meta, $status);
  }

  function badRequest($errors = [], $meta = []) {
    if (empty($errors)) {
      $errors = [
        Error::BAD_REQUEST => WPFunctions::get()->__('Invalid request parameters', 'mailpoet'),
      ];
    }
    return new ErrorResponse($errors, $meta, Response::STATUS_BAD_REQUEST);
  }

  public function isMethodAllowed($name, $type) {
    if ($type === self::TYPE_GET && !in_array($name, static::$get_methods)) {
      return false;
    }
    if ($type === self::TYPE_POST && in_array($name, static::$get_methods)) {
      return false;
    }
    return true;
  }
}
