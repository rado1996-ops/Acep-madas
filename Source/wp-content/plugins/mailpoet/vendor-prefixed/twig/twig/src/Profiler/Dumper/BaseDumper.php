<?php

/*
 * This file is part of Twig.
 *
 * (c) Fabien Potencier
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace MailPoetVendor\Twig\Profiler\Dumper;

if (!defined('ABSPATH')) exit;


use MailPoetVendor\Twig\Profiler\Profile;
/**
 * @author Fabien Potencier <fabien@symfony.com>
 */
abstract class BaseDumper
{
    private $root;
    public function dump(\MailPoetVendor\Twig\Profiler\Profile $profile)
    {
        return $this->dumpProfile($profile);
    }
    protected abstract function formatTemplate(\MailPoetVendor\Twig\Profiler\Profile $profile, $prefix);
    protected abstract function formatNonTemplate(\MailPoetVendor\Twig\Profiler\Profile $profile, $prefix);
    protected abstract function formatTime(\MailPoetVendor\Twig\Profiler\Profile $profile, $percent);
    private function dumpProfile(\MailPoetVendor\Twig\Profiler\Profile $profile, $prefix = '', $sibling = \false)
    {
        if ($profile->isRoot()) {
            $this->root = $profile->getDuration();
            $start = $profile->getName();
        } else {
            if ($profile->isTemplate()) {
                $start = $this->formatTemplate($profile, $prefix);
            } else {
                $start = $this->formatNonTemplate($profile, $prefix);
            }
            $prefix .= $sibling ? '│ ' : '  ';
        }
        $percent = $this->root ? $profile->getDuration() / $this->root * 100 : 0;
        if ($profile->getDuration() * 1000 < 1) {
            $str = $start . "\n";
        } else {
            $str = \sprintf("%s %s\n", $start, $this->formatTime($profile, $percent));
        }
        $nCount = \count($profile->getProfiles());
        foreach ($profile as $i => $p) {
            $str .= $this->dumpProfile($p, $prefix, $i + 1 !== $nCount);
        }
        return $str;
    }
}
\class_alias('MailPoetVendor\\Twig\\Profiler\\Dumper\\BaseDumper', 'MailPoetVendor\\Twig_Profiler_Dumper_Base');
