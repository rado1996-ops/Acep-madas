<?php

namespace MailPoetVendor;

if (!defined('ABSPATH')) exit;


use MailPoetVendor\Twig\Loader\FilesystemLoader;
\class_exists('MailPoetVendor\\Twig\\Loader\\FilesystemLoader');
if (\false) {
    class Twig_Loader_Filesystem extends \MailPoetVendor\Twig\Loader\FilesystemLoader
    {
    }
}
