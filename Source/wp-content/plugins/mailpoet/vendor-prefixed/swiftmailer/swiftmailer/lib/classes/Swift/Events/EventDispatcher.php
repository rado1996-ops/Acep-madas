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
 * Interface for the EventDispatcher which handles the event dispatching layer.
 *
 * @author Chris Corbyn
 */
interface Swift_Events_EventDispatcher
{
    /**
     * Create a new SendEvent for $source and $message.
     *
     * @param Swift_Transport $source
     * @param Swift_Mime_Message
     *
     * @return Swift_Events_SendEvent
     */
    public function createSendEvent(\MailPoetVendor\Swift_Transport $source, \MailPoetVendor\Swift_Mime_Message $message);
    /**
     * Create a new CommandEvent for $source and $command.
     *
     * @param Swift_Transport $source
     * @param string          $command      That will be executed
     * @param array           $successCodes That are needed
     *
     * @return Swift_Events_CommandEvent
     */
    public function createCommandEvent(\MailPoetVendor\Swift_Transport $source, $command, $successCodes = array());
    /**
     * Create a new ResponseEvent for $source and $response.
     *
     * @param Swift_Transport $source
     * @param string          $response
     * @param bool            $valid    If the response is valid
     *
     * @return Swift_Events_ResponseEvent
     */
    public function createResponseEvent(\MailPoetVendor\Swift_Transport $source, $response, $valid);
    /**
     * Create a new TransportChangeEvent for $source.
     *
     * @param Swift_Transport $source
     *
     * @return Swift_Events_TransportChangeEvent
     */
    public function createTransportChangeEvent(\MailPoetVendor\Swift_Transport $source);
    /**
     * Create a new TransportExceptionEvent for $source.
     *
     * @param Swift_Transport          $source
     * @param Swift_TransportException $ex
     *
     * @return Swift_Events_TransportExceptionEvent
     */
    public function createTransportExceptionEvent(\MailPoetVendor\Swift_Transport $source, \MailPoetVendor\Swift_TransportException $ex);
    /**
     * Bind an event listener to this dispatcher.
     *
     * @param Swift_Events_EventListener $listener
     */
    public function bindEventListener(\MailPoetVendor\Swift_Events_EventListener $listener);
    /**
     * Dispatch the given Event to all suitable listeners.
     *
     * @param Swift_Events_EventObject $evt
     * @param string                   $target method
     */
    public function dispatchEvent(\MailPoetVendor\Swift_Events_EventObject $evt, $target);
}
