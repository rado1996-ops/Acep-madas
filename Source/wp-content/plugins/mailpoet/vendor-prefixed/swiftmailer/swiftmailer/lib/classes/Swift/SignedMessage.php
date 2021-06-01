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
 * Signed Message, message that can be signed using a signer.
 *
 * This class is only kept for compatibility
 *
 *
 * @author     Xavier De Cock <xdecock@gmail.com>
 *
 * @deprecated
 */
class Swift_SignedMessage extends \MailPoetVendor\Swift_Message
{
}
