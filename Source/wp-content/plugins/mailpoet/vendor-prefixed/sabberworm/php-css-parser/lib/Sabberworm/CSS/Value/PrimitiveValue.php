<?php

namespace MailPoetVendor\Sabberworm\CSS\Value;

if (!defined('ABSPATH')) exit;


abstract class PrimitiveValue extends \MailPoetVendor\Sabberworm\CSS\Value\Value
{
    public function __construct($iLineNo = 0)
    {
        parent::__construct($iLineNo);
    }
}
