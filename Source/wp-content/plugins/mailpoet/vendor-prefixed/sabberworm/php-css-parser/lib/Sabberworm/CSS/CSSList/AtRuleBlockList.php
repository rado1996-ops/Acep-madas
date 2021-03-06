<?php

namespace MailPoetVendor\Sabberworm\CSS\CSSList;

if (!defined('ABSPATH')) exit;


use MailPoetVendor\Sabberworm\CSS\Property\AtRule;
/**
 * A BlockList constructed by an unknown @-rule. @media rules are rendered into AtRuleBlockList objects.
 */
class AtRuleBlockList extends \MailPoetVendor\Sabberworm\CSS\CSSList\CSSBlockList implements \MailPoetVendor\Sabberworm\CSS\Property\AtRule
{
    private $sType;
    private $sArgs;
    public function __construct($sType, $sArgs = '', $iLineNo = 0)
    {
        parent::__construct($iLineNo);
        $this->sType = $sType;
        $this->sArgs = $sArgs;
    }
    public function atRuleName()
    {
        return $this->sType;
    }
    public function atRuleArgs()
    {
        return $this->sArgs;
    }
    public function __toString()
    {
        return $this->render(new \MailPoetVendor\Sabberworm\CSS\OutputFormat());
    }
    public function render(\MailPoetVendor\Sabberworm\CSS\OutputFormat $oOutputFormat)
    {
        $sArgs = $this->sArgs;
        if ($sArgs) {
            $sArgs = ' ' . $sArgs;
        }
        $sResult = "@{$this->sType}{$sArgs}{$oOutputFormat->spaceBeforeOpeningBrace()}{";
        $sResult .= parent::render($oOutputFormat);
        $sResult .= '}';
        return $sResult;
    }
    public function isRootList()
    {
        return \false;
    }
}
