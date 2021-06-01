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
 * Contains a list of redundant Transports so when one fails, the next is used.
 *
 * @author Chris Corbyn
 */
class Swift_FailoverTransport extends \MailPoetVendor\Swift_Transport_FailoverTransport
{
    /**
     * Creates a new FailoverTransport with $transports.
     *
     * @param Swift_Transport[] $transports
     */
    public function __construct($transports = array())
    {
        \call_user_func_array(array($this, 'MailPoetVendor\\Swift_Transport_FailoverTransport::__construct'), \MailPoetVendor\Swift_DependencyContainer::getInstance()->createDependenciesFor('transport.failover'));
        $this->setTransports($transports);
    }
    /**
     * Create a new FailoverTransport instance.
     *
     * @param Swift_Transport[] $transports
     *
     * @return self
     */
    public static function newInstance($transports = array())
    {
        return new self($transports);
    }
}
