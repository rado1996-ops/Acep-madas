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
namespace MailPoetVendor\Twig\TokenParser;

if (!defined('ABSPATH')) exit;


use MailPoetVendor\Twig\Error\SyntaxError;
use MailPoetVendor\Twig\Node\BlockNode;
use MailPoetVendor\Twig\Node\BlockReferenceNode;
use MailPoetVendor\Twig\Node\Node;
use MailPoetVendor\Twig\Node\PrintNode;
use MailPoetVendor\Twig\Token;
/**
 * Marks a section of a template as being reusable.
 *
 *  {% block head %}
 *    <link rel="stylesheet" href="style.css" />
 *    <title>{% block title %}{% endblock %} - My Webpage</title>
 *  {% endblock %}
 *
 * @final
 */
class BlockTokenParser extends \MailPoetVendor\Twig\TokenParser\AbstractTokenParser
{
    public function parse(\MailPoetVendor\Twig\Token $token)
    {
        $lineno = $token->getLine();
        $stream = $this->parser->getStream();
        $name = $stream->expect(\MailPoetVendor\Twig\Token::NAME_TYPE)->getValue();
        if ($this->parser->hasBlock($name)) {
            throw new \MailPoetVendor\Twig\Error\SyntaxError(\sprintf("The block '%s' has already been defined line %d.", $name, $this->parser->getBlock($name)->getTemplateLine()), $stream->getCurrent()->getLine(), $stream->getSourceContext());
        }
        $this->parser->setBlock($name, $block = new \MailPoetVendor\Twig\Node\BlockNode($name, new \MailPoetVendor\Twig\Node\Node([]), $lineno));
        $this->parser->pushLocalScope();
        $this->parser->pushBlockStack($name);
        if ($stream->nextIf(\MailPoetVendor\Twig\Token::BLOCK_END_TYPE)) {
            $body = $this->parser->subparse([$this, 'decideBlockEnd'], \true);
            if ($token = $stream->nextIf(\MailPoetVendor\Twig\Token::NAME_TYPE)) {
                $value = $token->getValue();
                if ($value != $name) {
                    throw new \MailPoetVendor\Twig\Error\SyntaxError(\sprintf('Expected endblock for block "%s" (but "%s" given).', $name, $value), $stream->getCurrent()->getLine(), $stream->getSourceContext());
                }
            }
        } else {
            $body = new \MailPoetVendor\Twig\Node\Node([new \MailPoetVendor\Twig\Node\PrintNode($this->parser->getExpressionParser()->parseExpression(), $lineno)]);
        }
        $stream->expect(\MailPoetVendor\Twig\Token::BLOCK_END_TYPE);
        $block->setNode('body', $body);
        $this->parser->popBlockStack();
        $this->parser->popLocalScope();
        return new \MailPoetVendor\Twig\Node\BlockReferenceNode($name, $lineno, $this->getTag());
    }
    public function decideBlockEnd(\MailPoetVendor\Twig\Token $token)
    {
        return $token->test('endblock');
    }
    public function getTag()
    {
        return 'block';
    }
}
\class_alias('MailPoetVendor\\Twig\\TokenParser\\BlockTokenParser', 'MailPoetVendor\\Twig_TokenParser_Block');
