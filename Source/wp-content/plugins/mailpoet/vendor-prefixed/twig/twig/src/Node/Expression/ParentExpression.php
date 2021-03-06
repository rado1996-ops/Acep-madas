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
/**
 * Represents a parent node.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class ParentExpression extends \MailPoetVendor\Twig\Node\Expression\AbstractExpression
{
    public function __construct($name, $lineno, $tag = null)
    {
        parent::__construct([], ['output' => \false, 'name' => $name], $lineno, $tag);
    }
    public function compile(\MailPoetVendor\Twig\Compiler $compiler)
    {
        if ($this->getAttribute('output')) {
            $compiler->addDebugInfo($this)->write('$this->displayParentBlock(')->string($this->getAttribute('name'))->raw(", \$context, \$blocks);\n");
        } else {
            $compiler->raw('$this->renderParentBlock(')->string($this->getAttribute('name'))->raw(', $context, $blocks)');
        }
    }
}
\class_alias('MailPoetVendor\\Twig\\Node\\Expression\\ParentExpression', 'MailPoetVendor\\Twig_Node_Expression_Parent');
