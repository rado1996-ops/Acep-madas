<?php

namespace MailPoetVendor;

if (!defined('ABSPATH')) exit;


use MailPoetVendor\Twig\NodeVisitor\SandboxNodeVisitor;
\class_exists('MailPoetVendor\\Twig\\NodeVisitor\\SandboxNodeVisitor');
if (\false) {
    class Twig_NodeVisitor_Sandbox extends \MailPoetVendor\Twig\NodeVisitor\SandboxNodeVisitor
    {
    }
}
