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
 * RFC Compliance Exception class.
 *
 * @author Chris Corbyn
 */
class Swift_RfcComplianceException extends \MailPoetVendor\Swift_SwiftException
{
    /**
     * Create a new RfcComplianceException with $message.
     *
     * @param string $message
     */
    public function __construct($message)
    {
        parent::__construct($message);
    }
}
