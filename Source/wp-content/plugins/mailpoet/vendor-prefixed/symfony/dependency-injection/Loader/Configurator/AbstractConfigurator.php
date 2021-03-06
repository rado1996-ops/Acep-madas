<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace MailPoetVendor\Symfony\Component\DependencyInjection\Loader\Configurator;

if (!defined('ABSPATH')) exit;


use MailPoetVendor\Symfony\Component\DependencyInjection\Argument\ArgumentInterface;
use MailPoetVendor\Symfony\Component\DependencyInjection\Definition;
use MailPoetVendor\Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;
use MailPoetVendor\Symfony\Component\DependencyInjection\Parameter;
use MailPoetVendor\Symfony\Component\DependencyInjection\Reference;
use MailPoetVendor\Symfony\Component\ExpressionLanguage\Expression;
abstract class AbstractConfigurator
{
    const FACTORY = 'unknown';
    /** @internal */
    protected $definition;
    public function __call($method, $args)
    {
        if (\method_exists($this, 'set' . $method)) {
            return \call_user_func_array([$this, 'set' . $method], $args);
        }
        throw new \BadMethodCallException(\sprintf('Call to undefined method %s::%s()', \get_class($this), $method));
    }
    /**
     * Checks that a value is valid, optionally replacing Definition and Reference configurators by their configure value.
     *
     * @param mixed $value
     * @param bool  $allowServices whether Definition and Reference are allowed; by default, only scalars and arrays are
     *
     * @return mixed the value, optionally cast to a Definition/Reference
     */
    public static function processValue($value, $allowServices = \false)
    {
        if (\is_array($value)) {
            foreach ($value as $k => $v) {
                $value[$k] = static::processValue($v, $allowServices);
            }
            return $value;
        }
        if ($value instanceof \MailPoetVendor\Symfony\Component\DependencyInjection\Loader\Configurator\ReferenceConfigurator) {
            return new \MailPoetVendor\Symfony\Component\DependencyInjection\Reference($value->id, $value->invalidBehavior);
        }
        if ($value instanceof \MailPoetVendor\Symfony\Component\DependencyInjection\Loader\Configurator\InlineServiceConfigurator) {
            $def = $value->definition;
            $value->definition = null;
            return $def;
        }
        if ($value instanceof self) {
            throw new \MailPoetVendor\Symfony\Component\DependencyInjection\Exception\InvalidArgumentException(\sprintf('"%s()" can be used only at the root of service configuration files.', $value::FACTORY));
        }
        switch (\true) {
            case null === $value:
            case \is_scalar($value):
                return $value;
            case $value instanceof \MailPoetVendor\Symfony\Component\DependencyInjection\Argument\ArgumentInterface:
            case $value instanceof \MailPoetVendor\Symfony\Component\DependencyInjection\Definition:
            case $value instanceof \MailPoetVendor\Symfony\Component\ExpressionLanguage\Expression:
            case $value instanceof \MailPoetVendor\Symfony\Component\DependencyInjection\Parameter:
            case $value instanceof \MailPoetVendor\Symfony\Component\DependencyInjection\Reference:
                if ($allowServices) {
                    return $value;
                }
        }
        throw new \MailPoetVendor\Symfony\Component\DependencyInjection\Exception\InvalidArgumentException(\sprintf('Cannot use values of type "%s" in service configuration files.', \is_object($value) ? \get_class($value) : \gettype($value)));
    }
}
