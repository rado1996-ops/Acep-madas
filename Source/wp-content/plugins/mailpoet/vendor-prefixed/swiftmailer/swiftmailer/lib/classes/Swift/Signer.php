<?php

namespace MailPoetVendor;

if (!defined('ABSPATH')) exit;


require_once __DIR__ . '/../../swift_init.php';

/*
 * This file is part of SwiftMailer.
 * (c) 2004-2009 Chris Corbyn
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
/**
 * Base Class of Signer Infrastructure.
 *
 *
 * @author Xavier De Cock <xdecock@gmail.com>
 */
interface Swift_Signer
{
    public function reset();
}
