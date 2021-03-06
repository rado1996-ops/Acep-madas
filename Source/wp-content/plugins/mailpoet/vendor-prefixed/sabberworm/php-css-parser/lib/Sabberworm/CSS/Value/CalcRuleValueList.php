<?php

namespace MailPoetVendor\Sabberworm\CSS\Value;

if (!defined('ABSPATH')) exit;


class CalcRuleValueList extends \MailPoetVendor\Sabberworm\CSS\Value\RuleValueList
{
    public function __construct($iLineNo = 0)
    {
        parent::__construct(array(), ',', $iLineNo);
    }
    public function render(\MailPoetVendor\Sabberworm\CSS\OutputFormat $oOutputFormat)
    {
        return $oOutputFormat->implode(' ', $this->aComponents);
    }
}
