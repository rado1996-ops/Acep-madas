<?php

namespace MailPoetVendor;

if (!defined('ABSPATH')) exit;


use MailPoetVendor\Twig\Error\SyntaxError;
\class_exists('MailPoetVendor\\Twig\\Error\\SyntaxError');
if (\false) {
    class Twig_Error_Syntax extends \MailPoetVendor\Twig\Error\SyntaxError
    {
    }
}
