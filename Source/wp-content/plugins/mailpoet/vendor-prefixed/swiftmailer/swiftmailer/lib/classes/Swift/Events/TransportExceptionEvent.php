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
 * Generated when a TransportException is thrown from the Transport system.
 *
 * @author Chris Corbyn
 */
class Swift_Events_TransportExceptionEvent extends \MailPoetVendor\Swift_Events_EventObject
{
    /**
     * The Exception thrown.
     *
     * @var Swift_TransportException
     */
    private $_exception;
    /**
     * Create a new TransportExceptionEvent for $transport.
     *
     * @param Swift_Transport          $transport
     * @param Swift_TransportException $ex
     */
    public function __construct(\MailPoetVendor\Swift_Transport $transport, \MailPoetVendor\Swift_TransportException $ex)
    {
        parent::__construct($transport);
        $this->_exception = $ex;
    }
    /**
     * Get the TransportException thrown.
     *
     * @return Swift_TransportException
     */
    public function getException()
    {
        return $this->_exception;
    }
}
