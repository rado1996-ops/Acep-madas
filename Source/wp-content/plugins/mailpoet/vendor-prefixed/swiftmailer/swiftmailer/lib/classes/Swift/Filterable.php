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
 * Allows StreamFilters to operate on a stream.
 *
 * @author Chris Corbyn
 */
interface Swift_Filterable
{
    /**
     * Add a new StreamFilter, referenced by $key.
     *
     * @param Swift_StreamFilter $filter
     * @param string             $key
     */
    public function addFilter(\MailPoetVendor\Swift_StreamFilter $filter, $key);
    /**
     * Remove an existing filter using $key.
     *
     * @param string $key
     */
    public function removeFilter($key);
}
