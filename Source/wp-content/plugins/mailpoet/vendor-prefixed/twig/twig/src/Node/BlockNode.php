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
namespace MailPoetVendor\Twig\Node;

if (!defined('ABSPATH')) exit;


use MailPoetVendor\Twig\Compiler;
/**
 * Represents a block node.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class BlockNode extends \MailPoetVendor\Twig\Node\Node
{
    public function __construct($name, \MailPoetVendor\Twig_NodeInterface $body, $lineno, $tag = null)
    {
        parent::__construct(['body' => $body], ['name' => $name], $lineno, $tag);
    }
    public function compile(\MailPoetVendor\Twig\Compiler $compiler)
    {
        $compiler->addDebugInfo($this)->write(\sprintf("public function block_%s(\$context, array \$blocks = [])\n", $this->getAttribute('name')), "{\n")->indent();
        $compiler->subcompile($this->getNode('body'))->outdent()->write("}\n\n");
    }
}
\class_alias('MailPoetVendor\\Twig\\Node\\BlockNode', 'MailPoetVendor\\Twig_Node_Block');
