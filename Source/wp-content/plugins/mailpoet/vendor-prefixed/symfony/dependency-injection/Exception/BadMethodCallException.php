<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace MailPoetVendor\Symfony\Component\DependencyInjection\Exception;

if (!defined('ABSPATH')) exit;


/**
 * Base BadMethodCallException for Dependency Injection component.
 */
class BadMethodCallException extends \BadMethodCallException implements \MailPoetVendor\Symfony\Component\DependencyInjection\Exception\ExceptionInterface
{
}
