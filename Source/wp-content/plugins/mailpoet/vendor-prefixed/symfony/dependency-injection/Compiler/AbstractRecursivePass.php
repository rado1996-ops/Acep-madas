<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace MailPoetVendor\Symfony\Component\DependencyInjection\Compiler;

if (!defined('ABSPATH')) exit;


use MailPoetVendor\Symfony\Component\DependencyInjection\Argument\ArgumentInterface;
use MailPoetVendor\Symfony\Component\DependencyInjection\ContainerBuilder;
use MailPoetVendor\Symfony\Component\DependencyInjection\Definition;
use MailPoetVendor\Symfony\Component\DependencyInjection\Exception\RuntimeException;
use MailPoetVendor\Symfony\Component\DependencyInjection\Reference;
/**
 * @author Nicolas Grekas <p@tchwork.com>
 */
abstract class AbstractRecursivePass implements \MailPoetVendor\Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface
{
    /**
     * @var ContainerBuilder
     */
    protected $container;
    protected $currentId;
    /**
     * {@inheritdoc}
     */
    public function process(\MailPoetVendor\Symfony\Component\DependencyInjection\ContainerBuilder $container)
    {
        $this->container = $container;
        try {
            $this->processValue($container->getDefinitions(), \true);
        } finally {
            $this->container = null;
        }
    }
    /**
     * Processes a value found in a definition tree.
     *
     * @param mixed $value
     * @param bool  $isRoot
     *
     * @return mixed The processed value
     */
    protected function processValue($value, $isRoot = \false)
    {
        if (\is_array($value)) {
            foreach ($value as $k => $v) {
                if ($isRoot) {
                    $this->currentId = $k;
                }
                if ($v !== ($processedValue = $this->processValue($v, $isRoot))) {
                    $value[$k] = $processedValue;
                }
            }
        } elseif ($value instanceof \MailPoetVendor\Symfony\Component\DependencyInjection\Argument\ArgumentInterface) {
            $value->setValues($this->processValue($value->getValues()));
        } elseif ($value instanceof \MailPoetVendor\Symfony\Component\DependencyInjection\Definition) {
            $value->setArguments($this->processValue($value->getArguments()));
            $value->setProperties($this->processValue($value->getProperties()));
            $value->setMethodCalls($this->processValue($value->getMethodCalls()));
            $changes = $value->getChanges();
            if (isset($changes['factory'])) {
                $value->setFactory($this->processValue($value->getFactory()));
            }
            if (isset($changes['configurator'])) {
                $value->setConfigurator($this->processValue($value->getConfigurator()));
            }
        }
        return $value;
    }
    /**
     * @param Definition $definition
     * @param bool       $required
     *
     * @return \ReflectionFunctionAbstract|null
     *
     * @throws RuntimeException
     */
    protected function getConstructor(\MailPoetVendor\Symfony\Component\DependencyInjection\Definition $definition, $required)
    {
        if (\is_string($factory = $definition->getFactory())) {
            if (!\function_exists($factory)) {
                throw new \MailPoetVendor\Symfony\Component\DependencyInjection\Exception\RuntimeException(\sprintf('Invalid service "%s": function "%s" does not exist.', $this->currentId, $factory));
            }
            $r = new \ReflectionFunction($factory);
            if (\false !== $r->getFileName() && \file_exists($r->getFileName())) {
                $this->container->fileExists($r->getFileName());
            }
            return $r;
        }
        if ($factory) {
            list($class, $method) = $factory;
            if ($class instanceof \MailPoetVendor\Symfony\Component\DependencyInjection\Reference) {
                $class = $this->container->findDefinition((string) $class)->getClass();
            } elseif (null === $class) {
                $class = $definition->getClass();
            }
            if ('__construct' === $method) {
                throw new \MailPoetVendor\Symfony\Component\DependencyInjection\Exception\RuntimeException(\sprintf('Invalid service "%s": "__construct()" cannot be used as a factory method.', $this->currentId));
            }
            return $this->getReflectionMethod(new \MailPoetVendor\Symfony\Component\DependencyInjection\Definition($class), $method);
        }
        $class = $definition->getClass();
        try {
            if (!($r = $this->container->getReflectionClass($class))) {
                throw new \MailPoetVendor\Symfony\Component\DependencyInjection\Exception\RuntimeException(\sprintf('Invalid service "%s": class "%s" does not exist.', $this->currentId, $class));
            }
        } catch (\ReflectionException $e) {
            throw new \MailPoetVendor\Symfony\Component\DependencyInjection\Exception\RuntimeException(\sprintf('Invalid service "%s": %s.', $this->currentId, \lcfirst(\rtrim($e->getMessage(), '.'))));
        }
        if (!($r = $r->getConstructor())) {
            if ($required) {
                throw new \MailPoetVendor\Symfony\Component\DependencyInjection\Exception\RuntimeException(\sprintf('Invalid service "%s": class%s has no constructor.', $this->currentId, \sprintf($class !== $this->currentId ? ' "%s"' : '', $class)));
            }
        } elseif (!$r->isPublic()) {
            throw new \MailPoetVendor\Symfony\Component\DependencyInjection\Exception\RuntimeException(\sprintf('Invalid service "%s": %s must be public.', $this->currentId, \sprintf($class !== $this->currentId ? 'constructor of class "%s"' : 'its constructor', $class)));
        }
        return $r;
    }
    /**
     * @param Definition $definition
     * @param string     $method
     *
     * @throws RuntimeException
     *
     * @return \ReflectionFunctionAbstract
     */
    protected function getReflectionMethod(\MailPoetVendor\Symfony\Component\DependencyInjection\Definition $definition, $method)
    {
        if ('__construct' === $method) {
            return $this->getConstructor($definition, \true);
        }
        if (!($class = $definition->getClass())) {
            throw new \MailPoetVendor\Symfony\Component\DependencyInjection\Exception\RuntimeException(\sprintf('Invalid service "%s": the class is not set.', $this->currentId));
        }
        if (!($r = $this->container->getReflectionClass($class))) {
            throw new \MailPoetVendor\Symfony\Component\DependencyInjection\Exception\RuntimeException(\sprintf('Invalid service "%s": class "%s" does not exist.', $this->currentId, $class));
        }
        if (!$r->hasMethod($method)) {
            throw new \MailPoetVendor\Symfony\Component\DependencyInjection\Exception\RuntimeException(\sprintf('Invalid service "%s": method "%s()" does not exist.', $this->currentId, $class !== $this->currentId ? $class . '::' . $method : $method));
        }
        $r = $r->getMethod($method);
        if (!$r->isPublic()) {
            throw new \MailPoetVendor\Symfony\Component\DependencyInjection\Exception\RuntimeException(\sprintf('Invalid service "%s": method "%s()" must be public.', $this->currentId, $class !== $this->currentId ? $class . '::' . $method : $method));
        }
        return $r;
    }
}
