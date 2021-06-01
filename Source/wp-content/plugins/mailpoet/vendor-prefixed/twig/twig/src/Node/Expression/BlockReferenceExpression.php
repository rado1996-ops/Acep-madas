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
use MailPoetVendor\Twig\Node\Node;
/**
 * Represents a block call node.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class BlockReferenceExpression extends \MailPoetVendor\Twig\Node\Expression\AbstractExpression
{
    /**
     * @param Node|null $template
     */
    public function __construct(\MailPoetVendor\Twig_NodeInterface $name, $template = null, $lineno, $tag = null)
    {
        if (\is_bool($template)) {
            @\trigger_error(\sprintf('The %s method "$asString" argument is deprecated since version 1.28 and will be removed in 2.0.', __METHOD__), \E_USER_DEPRECATED);
            $template = null;
        }
        $nodes = ['name' => $name];
        if (null !== $template) {
            $nodes['template'] = $template;
        }
        parent::__construct($nodes, ['is_defined_test' => \false, 'output' => \false], $lineno, $tag);
    }
    public function compile(\MailPoetVendor\Twig\Compiler $compiler)
    {
        if ($this->getAttribute('is_defined_test')) {
            $this->compileTemplateCall($compiler, 'hasBlock');
        } else {
            if ($this->getAttribute('output')) {
                $compiler->addDebugInfo($this);
                $this->compileTemplateCall($compiler, 'displayBlock')->raw(";\n");
            } else {
                $this->compileTemplateCall($compiler, 'renderBlock');
            }
        }
    }
    private function compileTemplateCall(\MailPoetVendor\Twig\Compiler $compiler, $method)
    {
        if (!$this->hasNode('template')) {
            $compiler->write('$this');
        } else {
            $compiler->write('$this->loadTemplate(')->subcompile($this->getNode('template'))->raw(', ')->repr($this->getTemplateName())->raw(', ')->repr($this->getTemplateLine())->raw(')');
        }
        $compiler->raw(\sprintf('->%s', $method));
        $this->compileBlockArguments($compiler);
        return $compiler;
    }
    private function compileBlockArguments(\MailPoetVendor\Twig\Compiler $compiler)
    {
        $compiler->raw('(')->subcompile($this->getNode('name'))->raw(', $context');
        if (!$this->hasNode('template')) {
            $compiler->raw(', $blocks');
        }
        return $compiler->raw(')');
    }
}
\class_alias('MailPoetVendor\\Twig\\Node\\Expression\\BlockReferenceExpression', 'MailPoetVendor\\Twig_Node_Expression_BlockReference');
