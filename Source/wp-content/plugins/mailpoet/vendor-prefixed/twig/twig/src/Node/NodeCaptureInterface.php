<?php

/*
 * This file is part of Twig.
 *
 * (c) Fabien Potencier
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace MailPoetVendor\Twig\Node;

if (!defined('ABSPATH')) exit;


/**
 * Represents a node that captures any nested displayable nodes.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
interface NodeCaptureInterface
{
}
\class_alias('MailPoetVendor\\Twig\\Node\\NodeCaptureInterface', 'MailPoetVendor\\Twig_NodeCaptureInterface');
