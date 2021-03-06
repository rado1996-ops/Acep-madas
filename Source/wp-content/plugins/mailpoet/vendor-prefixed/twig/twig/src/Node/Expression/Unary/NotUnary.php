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
namespace MailPoetVendor\Twig\Node\Expression\Unary;

if (!defined('ABSPATH')) exit;


use MailPoetVendor\Twig\Compiler;
class NotUnary extends \MailPoetVendor\Twig\Node\Expression\Unary\AbstractUnary
{
    public function operator(\MailPoetVendor\Twig\Compiler $compiler)
    {
        $compiler->raw('!');
    }
}
\class_alias('MailPoetVendor\\Twig\\Node\\Expression\\Unary\\NotUnary', 'MailPoetVendor\\Twig_Node_Expression_Unary_Not');
