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
namespace MailPoetVendor\Twig\Node\Expression\Binary;

if (!defined('ABSPATH')) exit;


use MailPoetVendor\Twig\Compiler;
class BitwiseOrBinary extends \MailPoetVendor\Twig\Node\Expression\Binary\AbstractBinary
{
    public function operator(\MailPoetVendor\Twig\Compiler $compiler)
    {
        return $compiler->raw('|');
    }
}
\class_alias('MailPoetVendor\\Twig\\Node\\Expression\\Binary\\BitwiseOrBinary', 'MailPoetVendor\\Twig_Node_Expression_Binary_BitwiseOr');
