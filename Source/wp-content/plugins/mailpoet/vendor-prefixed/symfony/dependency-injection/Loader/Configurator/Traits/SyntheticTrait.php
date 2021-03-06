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


trait SyntheticTrait
{
    /**
     * Sets whether this definition is synthetic, that is not constructed by the
     * container, but dynamically injected.
     *
     * @param bool $synthetic
     *
     * @return $this
     */
    public final function synthetic($synthetic = \true)
    {
        $this->definition->setSynthetic($synthetic);
        return $this;
    }
}
