<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace MailPoetVendor\Symfony\Component\Validator\Exception;

if (!defined('ABSPATH')) exit;


/**
 * Base OutOfBoundsException for the Validator component.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class OutOfBoundsException extends \OutOfBoundsException implements \MailPoetVendor\Symfony\Component\Validator\Exception\ExceptionInterface
{
}
