<?php

namespace MailPoetVendor;

if (!defined('ABSPATH')) exit;


require_once __DIR__ . '/../../../swift_init.php';

/*
 * This file is part of SwiftMailer.
 * (c) 2004-2009 Chris Corbyn
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
/**
 * Listens for responses from a remote SMTP server.
 *
 * @author Chris Corbyn
 */
interface Swift_Events_ResponseListener extends \MailPoetVendor\Swift_Events_EventListener
{
    /**
     * Invoked immediately following a response coming back.
     *
     * @param Swift_Events_ResponseEvent $evt
     */
    public function responseReceived(\MailPoetVendor\Swift_Events_ResponseEvent $evt);
}
