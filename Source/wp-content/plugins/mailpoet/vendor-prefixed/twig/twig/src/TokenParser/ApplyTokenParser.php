<?php

/*
 * This file is part of Twig.
 *
 * (c) Fabien Potencier
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace MailPoetVendor\Twig\TokenParser;

if (!defined('ABSPATH')) exit;


use MailPoetVendor\Twig\Node\Expression\TempNameExpression;
use MailPoetVendor\Twig\Node\Node;
use MailPoetVendor\Twig\Node\PrintNode;
use MailPoetVendor\Twig\Node\SetNode;
use MailPoetVendor\Twig\Token;
/**
 * Applies filters on a section of a template.
 *
 *   {% apply upper %}
 *      This text becomes uppercase
 *   {% endapplys %}
 */
final class ApplyTokenParser extends \MailPoetVendor\Twig\TokenParser\AbstractTokenParser
{
    public function parse(\MailPoetVendor\Twig\Token $token)
    {
        $lineno = $token->getLine();
        $name = $this->parser->getVarName();
        $ref = new \MailPoetVendor\Twig\Node\Expression\TempNameExpression($name, $lineno);
        $ref->setAttribute('always_defined', \true);
        $filter = $this->parser->getExpressionParser()->parseFilterExpressionRaw($ref, $this->getTag());
        $this->parser->getStream()->expect(\MailPoetVendor\Twig\Token::BLOCK_END_TYPE);
        $body = $this->parser->subparse([$this, 'decideApplyEnd'], \true);
        $this->parser->getStream()->expect(\MailPoetVendor\Twig\Token::BLOCK_END_TYPE);
        return new \MailPoetVendor\Twig\Node\Node([new \MailPoetVendor\Twig\Node\SetNode(\true, $ref, $body, $lineno, $this->getTag()), new \MailPoetVendor\Twig\Node\PrintNode($filter, $lineno, $this->getTag())]);
    }
    public function decideApplyEnd(\MailPoetVendor\Twig\Token $token)
    {
        return $token->test('endapply');
    }
    public function getTag()
    {
        return 'apply';
    }
}
