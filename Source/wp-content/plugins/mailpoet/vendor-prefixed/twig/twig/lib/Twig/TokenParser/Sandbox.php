<?php

namespace MailPoetVendor;

if (!defined('ABSPATH')) exit;


use MailPoetVendor\Twig\TokenParser\SandboxTokenParser;
\class_exists('MailPoetVendor\\Twig\\TokenParser\\SandboxTokenParser');
if (\false) {
    class Twig_TokenParser_Sandbox extends \MailPoetVendor\Twig\TokenParser\SandboxTokenParser
    {
    }
}
