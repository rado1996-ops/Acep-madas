<?php

/*
 * This file is part of Twig.
 *
 * (c) Fabien Potencier
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace MailPoetVendor\Twig\Node\Expression\Binary;

if (!defined('ABSPATH')) exit;


use MailPoetVendor\Twig\Compiler;
class RangeBinary extends \MailPoetVendor\Twig\Node\Expression\Binary\AbstractBinary
{
    public function compile(\MailPoetVendor\Twig\Compiler $compiler)
    {
        $compiler->raw('range(')->subcompile($this->getNode('left'))->raw(', ')->subcompile($this->getNode('right'))->raw(')');
    }
    public function operator(\MailPoetVendor\Twig\Compiler $compiler)
    {
        return $compiler->raw('..');
    }
}
\class_alias('MailPoetVendor\\Twig\\Node\\Expression\\Binary\\RangeBinary', 'MailPoetVendor\\Twig_Node_Expression_Binary_Range');
