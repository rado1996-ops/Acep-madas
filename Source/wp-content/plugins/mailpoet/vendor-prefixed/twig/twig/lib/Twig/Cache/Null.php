<?php

namespace MailPoetVendor;

if (!defined('ABSPATH')) exit;


use MailPoetVendor\Twig\Cache\NullCache;
\class_exists('MailPoetVendor\\Twig\\Cache\\NullCache');
if (\false) {
    class Twig_Cache_Null extends \MailPoetVendor\Twig\Cache\NullCache
    {
    }
}
