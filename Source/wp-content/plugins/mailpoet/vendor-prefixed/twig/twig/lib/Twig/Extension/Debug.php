<?php

namespace MailPoetVendor;

if (!defined('ABSPATH')) exit;


use MailPoetVendor\Twig\Extension\DebugExtension;
\class_exists('MailPoetVendor\\Twig\\Extension\\DebugExtension');
if (\false) {
    class Twig_Extension_Debug extends \MailPoetVendor\Twig\Extension\DebugExtension
    {
    }
}
