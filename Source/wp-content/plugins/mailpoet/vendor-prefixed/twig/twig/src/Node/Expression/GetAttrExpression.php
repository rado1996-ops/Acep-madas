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
use MailPoetVendor\Twig\Template;
class GetAttrExpression extends \MailPoetVendor\Twig\Node\Expression\AbstractExpression
{
    public function __construct(\MailPoetVendor\Twig\Node\Expression\AbstractExpression $node, \MailPoetVendor\Twig\Node\Expression\AbstractExpression $attribute, \MailPoetVendor\Twig\Node\Expression\AbstractExpression $arguments = null, $type, $lineno)
    {
        $nodes = ['node' => $node, 'attribute' => $attribute];
        if (null !== $arguments) {
            $nodes['arguments'] = $arguments;
        }
        parent::__construct($nodes, ['type' => $type, 'is_defined_test' => \false, 'ignore_strict_check' => \false, 'disable_c_ext' => \false], $lineno);
    }
    public function compile(\MailPoetVendor\Twig\Compiler $compiler)
    {
        if ($this->getAttribute('disable_c_ext')) {
            @\trigger_error(\sprintf('Using the "disable_c_ext" attribute on %s is deprecated since version 1.30 and will be removed in 2.0.', __CLASS__), \E_USER_DEPRECATED);
        }
        if (\function_exists('MailPoetVendor\\twig_template_get_attributes') && !$this->getAttribute('disable_c_ext')) {
            $compiler->raw('twig_template_get_attributes($this, ');
        } else {
            $compiler->raw('$this->getAttribute(');
        }
        if ($this->getAttribute('ignore_strict_check')) {
            $this->getNode('node')->setAttribute('ignore_strict_check', \true);
        }
        $compiler->subcompile($this->getNode('node'));
        $compiler->raw(', ')->subcompile($this->getNode('attribute'));
        // only generate optional arguments when needed (to make generated code more readable)
        $needFourth = $this->getAttribute('ignore_strict_check');
        $needThird = $needFourth || $this->getAttribute('is_defined_test');
        $needSecond = $needThird || \MailPoetVendor\Twig\Template::ANY_CALL !== $this->getAttribute('type');
        $needFirst = $needSecond || $this->hasNode('arguments');
        if ($needFirst) {
            if ($this->hasNode('arguments')) {
                $compiler->raw(', ')->subcompile($this->getNode('arguments'));
            } else {
                $compiler->raw(', []');
            }
        }
        if ($needSecond) {
            $compiler->raw(', ')->repr($this->getAttribute('type'));
        }
        if ($needThird) {
            $compiler->raw(', ')->repr($this->getAttribute('is_defined_test'));
        }
        if ($needFourth) {
            $compiler->raw(', ')->repr($this->getAttribute('ignore_strict_check'));
        }
        $compiler->raw(')');
    }
}
\class_alias('MailPoetVendor\\Twig\\Node\\Expression\\GetAttrExpression', 'MailPoetVendor\\Twig_Node_Expression_GetAttr');
