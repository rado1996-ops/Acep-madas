<?php

namespace MailPoetVendor\Sabberworm\CSS;

if (!defined('ABSPATH')) exit;


interface Renderable
{
    public function __toString();
    public function render(\MailPoetVendor\Sabberworm\CSS\OutputFormat $oOutputFormat);
    public function getLineNo();
}
