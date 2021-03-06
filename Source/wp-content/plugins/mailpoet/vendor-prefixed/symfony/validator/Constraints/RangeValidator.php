<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace MailPoetVendor\Symfony\Component\Validator\Constraints;

if (!defined('ABSPATH')) exit;


use MailPoetVendor\Symfony\Component\Validator\Constraint;
use MailPoetVendor\Symfony\Component\Validator\ConstraintValidator;
use MailPoetVendor\Symfony\Component\Validator\Exception\ConstraintDefinitionException;
use MailPoetVendor\Symfony\Component\Validator\Exception\UnexpectedTypeException;
/**
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class RangeValidator extends \MailPoetVendor\Symfony\Component\Validator\ConstraintValidator
{
    /**
     * {@inheritdoc}
     */
    public function validate($value, \MailPoetVendor\Symfony\Component\Validator\Constraint $constraint)
    {
        if (!$constraint instanceof \MailPoetVendor\Symfony\Component\Validator\Constraints\Range) {
            throw new \MailPoetVendor\Symfony\Component\Validator\Exception\UnexpectedTypeException($constraint, __NAMESPACE__ . '\\Range');
        }
        if (null === $value) {
            return;
        }
        if (!\is_numeric($value) && !$value instanceof \DateTimeInterface) {
            $this->context->buildViolation($constraint->invalidMessage)->setParameter('{{ value }}', $this->formatValue($value, self::PRETTY_DATE))->setCode(\MailPoetVendor\Symfony\Component\Validator\Constraints\Range::INVALID_CHARACTERS_ERROR)->addViolation();
            return;
        }
        $min = $constraint->min;
        $max = $constraint->max;
        // Convert strings to DateTimes if comparing another DateTime
        // This allows to compare with any date/time value supported by
        // the DateTime constructor:
        // https://php.net/datetime.formats
        if ($value instanceof \DateTimeInterface) {
            $dateTimeClass = null;
            if (\is_string($min)) {
                $dateTimeClass = $value instanceof \DateTimeImmutable ? \DateTimeImmutable::class : \DateTime::class;
                try {
                    $min = new $dateTimeClass($min);
                } catch (\Exception $e) {
                    throw new \MailPoetVendor\Symfony\Component\Validator\Exception\ConstraintDefinitionException(\sprintf('The min value "%s" could not be converted to a "%s" instance in the "%s" constraint.', $min, $dateTimeClass, \get_class($constraint)));
                }
            }
            if (\is_string($max)) {
                $dateTimeClass = $dateTimeClass ?: ($value instanceof \DateTimeImmutable ? \DateTimeImmutable::class : \DateTime::class);
                try {
                    $max = new $dateTimeClass($max);
                } catch (\Exception $e) {
                    throw new \MailPoetVendor\Symfony\Component\Validator\Exception\ConstraintDefinitionException(\sprintf('The max value "%s" could not be converted to a "%s" instance in the "%s" constraint.', $max, $dateTimeClass, \get_class($constraint)));
                }
            }
        }
        if (null !== $constraint->max && $value > $max) {
            $this->context->buildViolation($constraint->maxMessage)->setParameter('{{ value }}', $this->formatValue($value, self::PRETTY_DATE))->setParameter('{{ limit }}', $this->formatValue($max, self::PRETTY_DATE))->setCode(\MailPoetVendor\Symfony\Component\Validator\Constraints\Range::TOO_HIGH_ERROR)->addViolation();
            return;
        }
        if (null !== $constraint->min && $value < $min) {
            $this->context->buildViolation($constraint->minMessage)->setParameter('{{ value }}', $this->formatValue($value, self::PRETTY_DATE))->setParameter('{{ limit }}', $this->formatValue($min, self::PRETTY_DATE))->setCode(\MailPoetVendor\Symfony\Component\Validator\Constraints\Range::TOO_LOW_ERROR)->addViolation();
        }
    }
}
