<?php

/*
 * This file is part of Twig.
 *
 * (c) Fabien Potencier
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace MailPoetVendor\Twig\Node;

if (!defined('ABSPATH')) exit;


use MailPoetVendor\Twig\Compiler;
/**
 * Represents an autoescape node.
 *
 * The value is the escaping strategy (can be html, js, ...)
 *
 * The true value is equivalent to html.
 *
 * If autoescaping is disabled, then the value is false.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class AutoEscapeNode extends \MailPoetVendor\Twig\Node\Node
{
    public function __construct($value, \MailPoetVendor\Twig_NodeInterface $body, $lineno, $tag = 'autoescape')
    {
        parent::__construct(['body' => $body], ['value' => $value], $lineno, $tag);
    }
    public function compile(\MailPoetVendor\Twig\Compiler $compiler)
    {
        $compiler->subcompile($this->getNode('body'));
    }
}
\class_alias('MailPoetVendor\\Twig\\Node\\AutoEscapeNode', 'MailPoetVendor\\Twig_Node_AutoEscape');
