<?php

namespace MailPoetVendor;

if (!defined('ABSPATH')) exit;


use MailPoetVendor\Twig\Cache\FilesystemCache;
\class_exists('MailPoetVendor\\Twig\\Cache\\FilesystemCache');
if (\false) {
    class Twig_Cache_Filesystem extends \MailPoetVendor\Twig\Cache\FilesystemCache
    {
    }
}
