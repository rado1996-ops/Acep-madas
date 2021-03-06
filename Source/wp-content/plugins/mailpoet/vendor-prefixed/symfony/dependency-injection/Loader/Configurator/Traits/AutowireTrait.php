<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace MailPoetVendor\Symfony\Component\DependencyInjection\Loader\Configurator\Traits;

if (!defined('ABSPATH')) exit;


trait AutowireTrait
{
    /**
     * Enables/disables autowiring.
     *
     * @param bool $autowired
     *
     * @return $this
     */
    public final function autowire($autowired = \true)
    {
        $this->definition->setAutowired($autowired);
        return $this;
    }
}
