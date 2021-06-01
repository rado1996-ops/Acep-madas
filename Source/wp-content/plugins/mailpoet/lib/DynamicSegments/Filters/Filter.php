<?php

namespace MailPoet\DynamicSegments\Filters;

if (!defined('ABSPATH')) exit;


use MailPoetVendor\Idiorm\ORM;

interface Filter {

  function toSql(ORM $orm);

  function toArray();

}
