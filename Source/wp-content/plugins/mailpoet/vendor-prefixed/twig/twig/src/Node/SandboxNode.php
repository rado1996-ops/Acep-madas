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
 * Represents a sandbox node.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class SandboxNode extends \MailPoetVendor\Twig\Node\Node
{
    public function __construct(\MailPoetVendor\Twig_NodeInterface $body, $lineno, $tag = null)
    {
        parent::__construct(['body' => $body], [], $lineno, $tag);
    }
    public function compile(\MailPoetVendor\Twig\Compiler $compiler)
    {
        $compiler->addDebugInfo($this)->write("if (!\$alreadySandboxed = \$this->sandbox->isSandboxed()) {\n")->indent()->write("\$this->sandbox->enableSandbox();\n")->outdent()->write("}\n")->subcompile($this->getNode('body'))->write("if (!\$alreadySandboxed) {\n")->indent()->write("\$this->sandbox->disableSandbox();\n")->outdent()->write("}\n");
    }
}
\class_alias('MailPoetVendor\\Twig\\Node\\SandboxNode', 'MailPoetVendor\\Twig_Node_Sandbox');
