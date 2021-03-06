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
use MailPoetVendor\Symfony\Component\Validator\Exception\UnexpectedTypeException;
/**
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class LengthValidator extends \MailPoetVendor\Symfony\Component\Validator\ConstraintValidator
{
    /**
     * {@inheritdoc}
     */
    public function validate($value, \MailPoetVendor\Symfony\Component\Validator\Constraint $constraint)
    {
        if (!$constraint instanceof \MailPoetVendor\Symfony\Component\Validator\Constraints\Length) {
            throw new \MailPoetVendor\Symfony\Component\Validator\Exception\UnexpectedTypeException($constraint, __NAMESPACE__ . '\\Length');
        }
        if (null === $value || '' === $value) {
            return;
        }
        if (!\is_scalar($value) && !(\is_object($value) && \method_exists($value, '__toString'))) {
            throw new \MailPoetVendor\Symfony\Component\Validator\Exception\UnexpectedTypeException($value, 'string');
        }
        $stringValue = (string) $value;
        if (!($invalidCharset = !@\mb_check_encoding($stringValue, $constraint->charset))) {
            $length = \mb_strlen($stringValue, $constraint->charset);
        }
        if ($invalidCharset) {
            $this->context->buildViolation($constraint->charsetMessage)->setParameter('{{ value }}', $this->formatValue($stringValue))->setParameter('{{ charset }}', $constraint->charset)->setInvalidValue($value)->setCode(\MailPoetVendor\Symfony\Component\Validator\Constraints\Length::INVALID_CHARACTERS_ERROR)->addViolation();
            return;
        }
        if (null !== $constraint->max && $length > $constraint->max) {
            $this->context->buildViolation($constraint->min == $constraint->max ? $constraint->exactMessage : $constraint->maxMessage)->setParameter('{{ value }}', $this->formatValue($stringValue))->setParameter('{{ limit }}', $constraint->max)->setInvalidValue($value)->setPlural((int) $constraint->max)->setCode(\MailPoetVendor\Symfony\Component\Validator\Constraints\Length::TOO_LONG_ERROR)->addViolation();
            return;
        }
        if (null !== $constraint->min && $length < $constraint->min) {
            $this->context->buildViolation($constraint->min == $constraint->max ? $constraint->exactMessage : $constraint->minMessage)->setParameter('{{ value }}', $this->formatValue($stringValue))->setParameter('{{ limit }}', $constraint->min)->setInvalidValue($value)->setPlural((int) $constraint->min)->setCode(\MailPoetVendor\Symfony\Component\Validator\Constraints\Length::TOO_SHORT_ERROR)->addViolation();
        }
    }
}
