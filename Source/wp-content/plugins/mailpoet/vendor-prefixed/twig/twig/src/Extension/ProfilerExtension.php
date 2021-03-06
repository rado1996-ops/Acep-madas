<?php

/*
 * This file is part of Twig.
 *
 * (c) Fabien Potencier
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace MailPoetVendor\Twig\Extension;

if (!defined('ABSPATH')) exit;


use MailPoetVendor\Twig\Profiler\NodeVisitor\ProfilerNodeVisitor;
use MailPoetVendor\Twig\Profiler\Profile;
class ProfilerExtension extends \MailPoetVendor\Twig\Extension\AbstractExtension
{
    private $actives = [];
    public function __construct(\MailPoetVendor\Twig\Profiler\Profile $profile)
    {
        $this->actives[] = $profile;
    }
    public function enter(\MailPoetVendor\Twig\Profiler\Profile $profile)
    {
        $this->actives[0]->addProfile($profile);
        \array_unshift($this->actives, $profile);
    }
    public function leave(\MailPoetVendor\Twig\Profiler\Profile $profile)
    {
        $profile->leave();
        \array_shift($this->actives);
        if (1 === \count($this->actives)) {
            $this->actives[0]->leave();
        }
    }
    public function getNodeVisitors()
    {
        return [new \MailPoetVendor\Twig\Profiler\NodeVisitor\ProfilerNodeVisitor(\get_class($this))];
    }
    public function getName()
    {
        return 'profiler';
    }
}
\class_alias('MailPoetVendor\\Twig\\Extension\\ProfilerExtension', 'MailPoetVendor\\Twig_Extension_Profiler');
