<?php

namespace MailPoetVendor\UpdateHelper;

if (!defined('ABSPATH')) exit;


use MailPoetVendor\Composer\Composer;
use MailPoetVendor\Composer\EventDispatcher\Event;
use MailPoetVendor\Composer\EventDispatcher\EventSubscriberInterface;
use MailPoetVendor\Composer\IO\IOInterface;
use MailPoetVendor\Composer\Plugin\PluginInterface;
class ComposerPlugin implements \MailPoetVendor\Composer\Plugin\PluginInterface, \MailPoetVendor\Composer\EventDispatcher\EventSubscriberInterface
{
    protected $io;
    public function activate(\MailPoetVendor\Composer\Composer $composer, \MailPoetVendor\Composer\IO\IOInterface $io)
    {
        $this->io = $io;
    }
    public static function getSubscribedEvents()
    {
        return array('post-autoload-dump' => array(array('onAutoloadDump', 0)));
    }
    public function onAutoloadDump(\MailPoetVendor\Composer\EventDispatcher\Event $event)
    {
        if (!\class_exists('MailPoetVendor\\UpdateHelper\\UpdateHelper')) {
            return;
        }
        \MailPoetVendor\UpdateHelper\UpdateHelper::check($event);
    }
}
