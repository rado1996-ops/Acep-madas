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
 * Attachment class for attaching files to a {@link Swift_Mime_Message}.
 *
 * @author Chris Corbyn
 */
class Swift_Attachment extends \MailPoetVendor\Swift_Mime_Attachment
{
    /**
     * Create a new Attachment.
     *
     * Details may be optionally provided to the constructor.
     *
     * @param string|Swift_OutputByteStream $data
     * @param string                        $filename
     * @param string                        $contentType
     */
    public function __construct($data = null, $filename = null, $contentType = null)
    {
        \call_user_func_array(array($this, 'MailPoetVendor\\Swift_Mime_Attachment::__construct'), \MailPoetVendor\Swift_DependencyContainer::getInstance()->createDependenciesFor('mime.attachment'));
        $this->setBody($data);
        $this->setFilename($filename);
        if ($contentType) {
            $this->setContentType($contentType);
        }
    }
    /**
     * Create a new Attachment.
     *
     * @param string|Swift_OutputByteStream $data
     * @param string                        $filename
     * @param string                        $contentType
     *
     * @return Swift_Mime_Attachment
     */
    public static function newInstance($data = null, $filename = null, $contentType = null)
    {
        return new self($data, $filename, $contentType);
    }
    /**
     * Create a new Attachment from a filesystem path.
     *
     * @param string $path
     * @param string $contentType optional
     *
     * @return Swift_Mime_Attachment
     */
    public static function fromPath($path, $contentType = null)
    {
        return self::newInstance()->setFile(new \MailPoetVendor\Swift_ByteStream_FileByteStream($path), $contentType);
    }
}
