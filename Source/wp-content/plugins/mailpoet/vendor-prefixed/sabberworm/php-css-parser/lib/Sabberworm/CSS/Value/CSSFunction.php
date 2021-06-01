<?php

namespace MailPoetVendor\Sabberworm\CSS\Value;

if (!defined('ABSPATH')) exit;


class CSSFunction extends \MailPoetVendor\Sabberworm\CSS\Value\ValueList
{
    protected $sName;
    public function __construct($sName, $aArguments, $sSeparator = ',', $iLineNo = 0)
    {
        if ($aArguments instanceof \MailPoetVendor\Sabberworm\CSS\Value\RuleValueList) {
            $sSeparator = $aArguments->getListSeparator();
            $aArguments = $aArguments->getListComponents();
        }
        $this->sName = $sName;
        $this->iLineNo = $iLineNo;
        parent::__construct($aArguments, $sSeparator, $iLineNo);
    }
    public function getName()
    {
        return $this->sName;
    }
    public function setName($sName)
    {
        $this->sName = $sName;
    }
    public function getArguments()
    {
        return $this->aComponents;
    }
    public function __toString()
    {
        return $this->render(new \MailPoetVendor\Sabberworm\CSS\OutputFormat());
    }
    public function render(\MailPoetVendor\Sabberworm\CSS\OutputFormat $oOutputFormat)
    {
        $aArguments = parent::render($oOutputFormat);
        return "{$this->sName}({$aArguments})";
    }
}
