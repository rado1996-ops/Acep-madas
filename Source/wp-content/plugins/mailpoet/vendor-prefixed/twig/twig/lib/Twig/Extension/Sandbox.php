<?php

namespace MailPoetVendor;

if (!defined('ABSPATH')) exit;


use MailPoetVendor\Twig\Extension\SandboxExtension;
\class_exists('MailPoetVendor\\Twig\\Extension\\SandboxExtension');
if (\false) {
    class Twig_Extension_Sandbox extends \MailPoetVendor\Twig\Extension\SandboxExtension
    {
    }
}
