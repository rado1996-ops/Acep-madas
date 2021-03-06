<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace MailPoetVendor\Symfony\Component\Validator;

if (!defined('ABSPATH')) exit;


use MailPoetVendor\Doctrine\Common\Annotations\AnnotationReader;
use MailPoetVendor\Doctrine\Common\Annotations\CachedReader;
use MailPoetVendor\Doctrine\Common\Annotations\Reader;
use MailPoetVendor\Doctrine\Common\Cache\ArrayCache;
use MailPoetVendor\Symfony\Component\Translation\IdentityTranslator;
use MailPoetVendor\Symfony\Component\Translation\TranslatorInterface;
use MailPoetVendor\Symfony\Component\Validator\Context\ExecutionContextFactory;
use MailPoetVendor\Symfony\Component\Validator\Exception\ValidatorException;
use MailPoetVendor\Symfony\Component\Validator\Mapping\Cache\CacheInterface;
use MailPoetVendor\Symfony\Component\Validator\Mapping\Factory\LazyLoadingMetadataFactory;
use MailPoetVendor\Symfony\Component\Validator\Mapping\Factory\MetadataFactoryInterface;
use MailPoetVendor\Symfony\Component\Validator\Mapping\Loader\AnnotationLoader;
use MailPoetVendor\Symfony\Component\Validator\Mapping\Loader\LoaderChain;
use MailPoetVendor\Symfony\Component\Validator\Mapping\Loader\LoaderInterface;
use MailPoetVendor\Symfony\Component\Validator\Mapping\Loader\StaticMethodLoader;
use MailPoetVendor\Symfony\Component\Validator\Mapping\Loader\XmlFileLoader;
use MailPoetVendor\Symfony\Component\Validator\Mapping\Loader\YamlFileLoader;
use MailPoetVendor\Symfony\Component\Validator\Validator\RecursiveValidator;
/**
 * The default implementation of {@link ValidatorBuilderInterface}.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class ValidatorBuilder implements \MailPoetVendor\Symfony\Component\Validator\ValidatorBuilderInterface
{
    private $initializers = [];
    private $xmlMappings = [];
    private $yamlMappings = [];
    private $methodMappings = [];
    /**
     * @var Reader|null
     */
    private $annotationReader;
    /**
     * @var MetadataFactoryInterface|null
     */
    private $metadataFactory;
    /**
     * @var ConstraintValidatorFactoryInterface|null
     */
    private $validatorFactory;
    /**
     * @var CacheInterface|null
     */
    private $metadataCache;
    /**
     * @var TranslatorInterface|null
     */
    private $translator;
    /**
     * @var string|null
     */
    private $translationDomain;
    /**
     * {@inheritdoc}
     */
    public function addObjectInitializer(\MailPoetVendor\Symfony\Component\Validator\ObjectInitializerInterface $initializer)
    {
        $this->initializers[] = $initializer;
        return $this;
    }
    /**
     * {@inheritdoc}
     */
    public function addObjectInitializers(array $initializers)
    {
        $this->initializers = \array_merge($this->initializers, $initializers);
        return $this;
    }
    /**
     * {@inheritdoc}
     */
    public function addXmlMapping($path)
    {
        if (null !== $this->metadataFactory) {
            throw new \MailPoetVendor\Symfony\Component\Validator\Exception\ValidatorException('You cannot add custom mappings after setting a custom metadata factory. Configure your metadata factory instead.');
        }
        $this->xmlMappings[] = $path;
        return $this;
    }
    /**
     * {@inheritdoc}
     */
    public function addXmlMappings(array $paths)
    {
        if (null !== $this->metadataFactory) {
            throw new \MailPoetVendor\Symfony\Component\Validator\Exception\ValidatorException('You cannot add custom mappings after setting a custom metadata factory. Configure your metadata factory instead.');
        }
        $this->xmlMappings = \array_merge($this->xmlMappings, $paths);
        return $this;
    }
    /**
     * {@inheritdoc}
     */
    public function addYamlMapping($path)
    {
        if (null !== $this->metadataFactory) {
            throw new \MailPoetVendor\Symfony\Component\Validator\Exception\ValidatorException('You cannot add custom mappings after setting a custom metadata factory. Configure your metadata factory instead.');
        }
        $this->yamlMappings[] = $path;
        return $this;
    }
    /**
     * {@inheritdoc}
     */
    public function addYamlMappings(array $paths)
    {
        if (null !== $this->metadataFactory) {
            throw new \MailPoetVendor\Symfony\Component\Validator\Exception\ValidatorException('You cannot add custom mappings after setting a custom metadata factory. Configure your metadata factory instead.');
        }
        $this->yamlMappings = \array_merge($this->yamlMappings, $paths);
        return $this;
    }
    /**
     * {@inheritdoc}
     */
    public function addMethodMapping($methodName)
    {
        if (null !== $this->metadataFactory) {
            throw new \MailPoetVendor\Symfony\Component\Validator\Exception\ValidatorException('You cannot add custom mappings after setting a custom metadata factory. Configure your metadata factory instead.');
        }
        $this->methodMappings[] = $methodName;
        return $this;
    }
    /**
     * {@inheritdoc}
     */
    public function addMethodMappings(array $methodNames)
    {
        if (null !== $this->metadataFactory) {
            throw new \MailPoetVendor\Symfony\Component\Validator\Exception\ValidatorException('You cannot add custom mappings after setting a custom metadata factory. Configure your metadata factory instead.');
        }
        $this->methodMappings = \array_merge($this->methodMappings, $methodNames);
        return $this;
    }
    /**
     * {@inheritdoc}
     */
    public function enableAnnotationMapping(\MailPoetVendor\Doctrine\Common\Annotations\Reader $annotationReader = null)
    {
        if (null !== $this->metadataFactory) {
            throw new \MailPoetVendor\Symfony\Component\Validator\Exception\ValidatorException('You cannot enable annotation mapping after setting a custom metadata factory. Configure your metadata factory instead.');
        }
        if (null === $annotationReader) {
            if (!\class_exists('MailPoetVendor\\Doctrine\\Common\\Annotations\\AnnotationReader') || !\class_exists('MailPoetVendor\\Doctrine\\Common\\Cache\\ArrayCache')) {
                throw new \RuntimeException('Enabling annotation based constraint mapping requires the packages doctrine/annotations and doctrine/cache to be installed.');
            }
            $annotationReader = new \MailPoetVendor\Doctrine\Common\Annotations\CachedReader(new \MailPoetVendor\Doctrine\Common\Annotations\AnnotationReader(), new \MailPoetVendor\Doctrine\Common\Cache\ArrayCache());
        }
        $this->annotationReader = $annotationReader;
        return $this;
    }
    /**
     * {@inheritdoc}
     */
    public function disableAnnotationMapping()
    {
        $this->annotationReader = null;
        return $this;
    }
    /**
     * {@inheritdoc}
     */
    public function setMetadataFactory(\MailPoetVendor\Symfony\Component\Validator\Mapping\Factory\MetadataFactoryInterface $metadataFactory)
    {
        if (\count($this->xmlMappings) > 0 || \count($this->yamlMappings) > 0 || \count($this->methodMappings) > 0 || null !== $this->annotationReader) {
            throw new \MailPoetVendor\Symfony\Component\Validator\Exception\ValidatorException('You cannot set a custom metadata factory after adding custom mappings. You should do either of both.');
        }
        $this->metadataFactory = $metadataFactory;
        return $this;
    }
    /**
     * {@inheritdoc}
     */
    public function setMetadataCache(\MailPoetVendor\Symfony\Component\Validator\Mapping\Cache\CacheInterface $cache)
    {
        if (null !== $this->metadataFactory) {
            throw new \MailPoetVendor\Symfony\Component\Validator\Exception\ValidatorException('You cannot set a custom metadata cache after setting a custom metadata factory. Configure your metadata factory instead.');
        }
        $this->metadataCache = $cache;
        return $this;
    }
    /**
     * {@inheritdoc}
     */
    public function setConstraintValidatorFactory(\MailPoetVendor\Symfony\Component\Validator\ConstraintValidatorFactoryInterface $validatorFactory)
    {
        $this->validatorFactory = $validatorFactory;
        return $this;
    }
    /**
     * {@inheritdoc}
     */
    public function setTranslator(\MailPoetVendor\Symfony\Component\Translation\TranslatorInterface $translator)
    {
        $this->translator = $translator;
        return $this;
    }
    /**
     * {@inheritdoc}
     */
    public function setTranslationDomain($translationDomain)
    {
        $this->translationDomain = $translationDomain;
        return $this;
    }
    /**
     * @return LoaderInterface[]
     */
    public function getLoaders()
    {
        $loaders = [];
        foreach ($this->xmlMappings as $xmlMapping) {
            $loaders[] = new \MailPoetVendor\Symfony\Component\Validator\Mapping\Loader\XmlFileLoader($xmlMapping);
        }
        foreach ($this->yamlMappings as $yamlMappings) {
            $loaders[] = new \MailPoetVendor\Symfony\Component\Validator\Mapping\Loader\YamlFileLoader($yamlMappings);
        }
        foreach ($this->methodMappings as $methodName) {
            $loaders[] = new \MailPoetVendor\Symfony\Component\Validator\Mapping\Loader\StaticMethodLoader($methodName);
        }
        if ($this->annotationReader) {
            $loaders[] = new \MailPoetVendor\Symfony\Component\Validator\Mapping\Loader\AnnotationLoader($this->annotationReader);
        }
        return $loaders;
    }
    /**
     * {@inheritdoc}
     */
    public function getValidator()
    {
        $metadataFactory = $this->metadataFactory;
        if (!$metadataFactory) {
            $loaders = $this->getLoaders();
            $loader = null;
            if (\count($loaders) > 1) {
                $loader = new \MailPoetVendor\Symfony\Component\Validator\Mapping\Loader\LoaderChain($loaders);
            } elseif (1 === \count($loaders)) {
                $loader = $loaders[0];
            }
            $metadataFactory = new \MailPoetVendor\Symfony\Component\Validator\Mapping\Factory\LazyLoadingMetadataFactory($loader, $this->metadataCache);
        }
        $validatorFactory = $this->validatorFactory ?: new \MailPoetVendor\Symfony\Component\Validator\ConstraintValidatorFactory();
        $translator = $this->translator;
        if (null === $translator) {
            $translator = new \MailPoetVendor\Symfony\Component\Translation\IdentityTranslator();
            // Force the locale to be 'en' when no translator is provided rather than relying on the Intl default locale
            // This avoids depending on Intl or the stub implementation being available. It also ensures that Symfony
            // validation messages are pluralized properly even when the default locale gets changed because they are in
            // English.
            $translator->setLocale('en');
        }
        $contextFactory = new \MailPoetVendor\Symfony\Component\Validator\Context\ExecutionContextFactory($translator, $this->translationDomain);
        return new \MailPoetVendor\Symfony\Component\Validator\Validator\RecursiveValidator($contextFactory, $metadataFactory, $validatorFactory, $this->initializers);
    }
}
