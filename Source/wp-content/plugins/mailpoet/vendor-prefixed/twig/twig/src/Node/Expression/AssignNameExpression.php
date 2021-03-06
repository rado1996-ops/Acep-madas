<?php

/*
 * This file is part of Twig.
 *
 * (c) Fabien Potencier
 * (c) Armin Ronacher
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace MailPoetVendor\Twig\Node\Expression;

if (!defined('ABSPATH')) exit;


use MailPoetVendor\Twig\Compiler;
class AssignNameExpression extends \MailPoetVendor\Twig\Node\Expression\NameExpression
{
    public function compile(\MailPoetVendor\Twig\Compiler $compiler)
    {
        $compiler->raw('$context[')->string($this->getAttribute('name'))->raw(']');
    }
}
\class_alias('MailPoetVendor\\Twig\\Node\\Expression\\AssignNameExpression', 'MailPoetVendor\\Twig_Node_Expression_AssignName');
