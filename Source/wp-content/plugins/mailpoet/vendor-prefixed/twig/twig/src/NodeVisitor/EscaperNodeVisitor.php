<?php

/*
 * This file is part of Twig.
 *
 * (c) Fabien Potencier
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace MailPoetVendor\Twig\NodeVisitor;

if (!defined('ABSPATH')) exit;


use MailPoetVendor\Twig\Environment;
use MailPoetVendor\Twig\Node\AutoEscapeNode;
use MailPoetVendor\Twig\Node\BlockNode;
use MailPoetVendor\Twig\Node\BlockReferenceNode;
use MailPoetVendor\Twig\Node\DoNode;
use MailPoetVendor\Twig\Node\Expression\ConditionalExpression;
use MailPoetVendor\Twig\Node\Expression\ConstantExpression;
use MailPoetVendor\Twig\Node\Expression\FilterExpression;
use MailPoetVendor\Twig\Node\Expression\InlinePrint;
use MailPoetVendor\Twig\Node\ImportNode;
use MailPoetVendor\Twig\Node\ModuleNode;
use MailPoetVendor\Twig\Node\Node;
use MailPoetVendor\Twig\Node\PrintNode;
use MailPoetVendor\Twig\NodeTraverser;
/**
 * @final
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class EscaperNodeVisitor extends \MailPoetVendor\Twig\NodeVisitor\AbstractNodeVisitor
{
    protected $statusStack = [];
    protected $blocks = [];
    protected $safeAnalysis;
    protected $traverser;
    protected $defaultStrategy = \false;
    protected $safeVars = [];
    public function __construct()
    {
        $this->safeAnalysis = new \MailPoetVendor\Twig\NodeVisitor\SafeAnalysisNodeVisitor();
    }
    protected function doEnterNode(\MailPoetVendor\Twig\Node\Node $node, \MailPoetVendor\Twig\Environment $env)
    {
        if ($node instanceof \MailPoetVendor\Twig\Node\ModuleNode) {
            if ($env->hasExtension('MailPoetVendor\\Twig\\Extension\\EscaperExtension') && ($defaultStrategy = $env->getExtension('MailPoetVendor\\Twig\\Extension\\EscaperExtension')->getDefaultStrategy($node->getTemplateName()))) {
                $this->defaultStrategy = $defaultStrategy;
            }
            $this->safeVars = [];
            $this->blocks = [];
        } elseif ($node instanceof \MailPoetVendor\Twig\Node\AutoEscapeNode) {
            $this->statusStack[] = $node->getAttribute('value');
        } elseif ($node instanceof \MailPoetVendor\Twig\Node\BlockNode) {
            $this->statusStack[] = isset($this->blocks[$node->getAttribute('name')]) ? $this->blocks[$node->getAttribute('name')] : $this->needEscaping($env);
        } elseif ($node instanceof \MailPoetVendor\Twig\Node\ImportNode) {
            $this->safeVars[] = $node->getNode('var')->getAttribute('name');
        }
        return $node;
    }
    protected function doLeaveNode(\MailPoetVendor\Twig\Node\Node $node, \MailPoetVendor\Twig\Environment $env)
    {
        if ($node instanceof \MailPoetVendor\Twig\Node\ModuleNode) {
            $this->defaultStrategy = \false;
            $this->safeVars = [];
            $this->blocks = [];
        } elseif ($node instanceof \MailPoetVendor\Twig\Node\Expression\FilterExpression) {
            return $this->preEscapeFilterNode($node, $env);
        } elseif ($node instanceof \MailPoetVendor\Twig\Node\PrintNode && \false !== ($type = $this->needEscaping($env))) {
            $expression = $node->getNode('expr');
            if ($expression instanceof \MailPoetVendor\Twig\Node\Expression\ConditionalExpression && $this->shouldUnwrapConditional($expression, $env, $type)) {
                return new \MailPoetVendor\Twig\Node\DoNode($this->unwrapConditional($expression, $env, $type), $expression->getTemplateLine());
            }
            return $this->escapePrintNode($node, $env, $type);
        }
        if ($node instanceof \MailPoetVendor\Twig\Node\AutoEscapeNode || $node instanceof \MailPoetVendor\Twig\Node\BlockNode) {
            \array_pop($this->statusStack);
        } elseif ($node instanceof \MailPoetVendor\Twig\Node\BlockReferenceNode) {
            $this->blocks[$node->getAttribute('name')] = $this->needEscaping($env);
        }
        return $node;
    }
    private function shouldUnwrapConditional(\MailPoetVendor\Twig\Node\Expression\ConditionalExpression $expression, \MailPoetVendor\Twig\Environment $env, $type)
    {
        $expr2Safe = $this->isSafeFor($type, $expression->getNode('expr2'), $env);
        $expr3Safe = $this->isSafeFor($type, $expression->getNode('expr3'), $env);
        return $expr2Safe !== $expr3Safe;
    }
    private function unwrapConditional(\MailPoetVendor\Twig\Node\Expression\ConditionalExpression $expression, \MailPoetVendor\Twig\Environment $env, $type)
    {
        // convert "echo a ? b : c" to "a ? echo b : echo c" recursively
        $expr2 = $expression->getNode('expr2');
        if ($expr2 instanceof \MailPoetVendor\Twig\Node\Expression\ConditionalExpression && $this->shouldUnwrapConditional($expr2, $env, $type)) {
            $expr2 = $this->unwrapConditional($expr2, $env, $type);
        } else {
            $expr2 = $this->escapeInlinePrintNode(new \MailPoetVendor\Twig\Node\Expression\InlinePrint($expr2, $expr2->getTemplateLine()), $env, $type);
        }
        $expr3 = $expression->getNode('expr3');
        if ($expr3 instanceof \MailPoetVendor\Twig\Node\Expression\ConditionalExpression && $this->shouldUnwrapConditional($expr3, $env, $type)) {
            $expr3 = $this->unwrapConditional($expr3, $env, $type);
        } else {
            $expr3 = $this->escapeInlinePrintNode(new \MailPoetVendor\Twig\Node\Expression\InlinePrint($expr3, $expr3->getTemplateLine()), $env, $type);
        }
        return new \MailPoetVendor\Twig\Node\Expression\ConditionalExpression($expression->getNode('expr1'), $expr2, $expr3, $expression->getTemplateLine());
    }
    private function escapeInlinePrintNode(\MailPoetVendor\Twig\Node\Expression\InlinePrint $node, \MailPoetVendor\Twig\Environment $env, $type)
    {
        $expression = $node->getNode('node');
        if ($this->isSafeFor($type, $expression, $env)) {
            return $node;
        }
        return new \MailPoetVendor\Twig\Node\Expression\InlinePrint($this->getEscaperFilter($type, $expression), $node->getTemplateLine());
    }
    protected function escapePrintNode(\MailPoetVendor\Twig\Node\PrintNode $node, \MailPoetVendor\Twig\Environment $env, $type)
    {
        if (\false === $type) {
            return $node;
        }
        $expression = $node->getNode('expr');
        if ($this->isSafeFor($type, $expression, $env)) {
            return $node;
        }
        $class = \get_class($node);
        return new $class($this->getEscaperFilter($type, $expression), $node->getTemplateLine());
    }
    protected function preEscapeFilterNode(\MailPoetVendor\Twig\Node\Expression\FilterExpression $filter, \MailPoetVendor\Twig\Environment $env)
    {
        $name = $filter->getNode('filter')->getAttribute('value');
        $type = $env->getFilter($name)->getPreEscape();
        if (null === $type) {
            return $filter;
        }
        $node = $filter->getNode('node');
        if ($this->isSafeFor($type, $node, $env)) {
            return $filter;
        }
        $filter->setNode('node', $this->getEscaperFilter($type, $node));
        return $filter;
    }
    protected function isSafeFor($type, \MailPoetVendor\Twig_NodeInterface $expression, $env)
    {
        $safe = $this->safeAnalysis->getSafe($expression);
        if (null === $safe) {
            if (null === $this->traverser) {
                $this->traverser = new \MailPoetVendor\Twig\NodeTraverser($env, [$this->safeAnalysis]);
            }
            $this->safeAnalysis->setSafeVars($this->safeVars);
            $this->traverser->traverse($expression);
            $safe = $this->safeAnalysis->getSafe($expression);
        }
        return \in_array($type, $safe) || \in_array('all', $safe);
    }
    protected function needEscaping(\MailPoetVendor\Twig\Environment $env)
    {
        if (\count($this->statusStack)) {
            return $this->statusStack[\count($this->statusStack) - 1];
        }
        return $this->defaultStrategy ? $this->defaultStrategy : \false;
    }
    protected function getEscaperFilter($type, \MailPoetVendor\Twig_NodeInterface $node)
    {
        $line = $node->getTemplateLine();
        $name = new \MailPoetVendor\Twig\Node\Expression\ConstantExpression('escape', $line);
        $args = new \MailPoetVendor\Twig\Node\Node([new \MailPoetVendor\Twig\Node\Expression\ConstantExpression((string) $type, $line), new \MailPoetVendor\Twig\Node\Expression\ConstantExpression(null, $line), new \MailPoetVendor\Twig\Node\Expression\ConstantExpression(\true, $line)]);
        return new \MailPoetVendor\Twig\Node\Expression\FilterExpression($node, $name, $args, $line);
    }
    public function getPriority()
    {
        return 0;
    }
}
\class_alias('MailPoetVendor\\Twig\\NodeVisitor\\EscaperNodeVisitor', 'MailPoetVendor\\Twig_NodeVisitor_Escaper');
