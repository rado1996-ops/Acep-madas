<?php

/*
 * This file is part of Twig.
 *
 * (c) Fabien Potencier
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace MailPoetVendor\Twig\Profiler\NodeVisitor;

if (!defined('ABSPATH')) exit;


use MailPoetVendor\Twig\Environment;
use MailPoetVendor\Twig\Node\BlockNode;
use MailPoetVendor\Twig\Node\BodyNode;
use MailPoetVendor\Twig\Node\MacroNode;
use MailPoetVendor\Twig\Node\ModuleNode;
use MailPoetVendor\Twig\Node\Node;
use MailPoetVendor\Twig\NodeVisitor\AbstractNodeVisitor;
use MailPoetVendor\Twig\Profiler\Node\EnterProfileNode;
use MailPoetVendor\Twig\Profiler\Node\LeaveProfileNode;
use MailPoetVendor\Twig\Profiler\Profile;
/**
 * @author Fabien Potencier <fabien@symfony.com>
 *
 * @final
 */
class ProfilerNodeVisitor extends \MailPoetVendor\Twig\NodeVisitor\AbstractNodeVisitor
{
    private $extensionName;
    public function __construct($extensionName)
    {
        $this->extensionName = $extensionName;
    }
    protected function doEnterNode(\MailPoetVendor\Twig\Node\Node $node, \MailPoetVendor\Twig\Environment $env)
    {
        return $node;
    }
    protected function doLeaveNode(\MailPoetVendor\Twig\Node\Node $node, \MailPoetVendor\Twig\Environment $env)
    {
        if ($node instanceof \MailPoetVendor\Twig\Node\ModuleNode) {
            $varName = $this->getVarName();
            $node->setNode('display_start', new \MailPoetVendor\Twig\Node\Node([new \MailPoetVendor\Twig\Profiler\Node\EnterProfileNode($this->extensionName, \MailPoetVendor\Twig\Profiler\Profile::TEMPLATE, $node->getTemplateName(), $varName), $node->getNode('display_start')]));
            $node->setNode('display_end', new \MailPoetVendor\Twig\Node\Node([new \MailPoetVendor\Twig\Profiler\Node\LeaveProfileNode($varName), $node->getNode('display_end')]));
        } elseif ($node instanceof \MailPoetVendor\Twig\Node\BlockNode) {
            $varName = $this->getVarName();
            $node->setNode('body', new \MailPoetVendor\Twig\Node\BodyNode([new \MailPoetVendor\Twig\Profiler\Node\EnterProfileNode($this->extensionName, \MailPoetVendor\Twig\Profiler\Profile::BLOCK, $node->getAttribute('name'), $varName), $node->getNode('body'), new \MailPoetVendor\Twig\Profiler\Node\LeaveProfileNode($varName)]));
        } elseif ($node instanceof \MailPoetVendor\Twig\Node\MacroNode) {
            $varName = $this->getVarName();
            $node->setNode('body', new \MailPoetVendor\Twig\Node\BodyNode([new \MailPoetVendor\Twig\Profiler\Node\EnterProfileNode($this->extensionName, \MailPoetVendor\Twig\Profiler\Profile::MACRO, $node->getAttribute('name'), $varName), $node->getNode('body'), new \MailPoetVendor\Twig\Profiler\Node\LeaveProfileNode($varName)]));
        }
        return $node;
    }
    private function getVarName()
    {
        return \sprintf('__internal_%s', \hash('sha256', $this->extensionName));
    }
    public function getPriority()
    {
        return 0;
    }
}
\class_alias('MailPoetVendor\\Twig\\Profiler\\NodeVisitor\\ProfilerNodeVisitor', 'MailPoetVendor\\Twig_Profiler_NodeVisitor_Profiler');
