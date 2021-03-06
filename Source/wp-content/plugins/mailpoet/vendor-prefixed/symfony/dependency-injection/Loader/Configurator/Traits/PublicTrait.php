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


/**
 * @method $this public()
 * @method $this private()
 */
trait PublicTrait
{
    /**
     * @return $this
     */
    protected final function setPublic()
    {
        $this->definition->setPublic(\true);
        return $this;
    }
    /**
     * @return $this
     */
    protected final function setPrivate()
    {
        $this->definition->setPublic(\false);
        return $this;
    }
}
