<?php

namespace MailPoetVendor;

if (!defined('ABSPATH')) exit;


use MailPoetVendor\Twig\Error\LoaderError;
\class_exists('MailPoetVendor\\Twig\\Error\\LoaderError');
if (\false) {
    class Twig_Error_Loader extends \MailPoetVendor\Twig\Error\LoaderError
    {
    }
}
