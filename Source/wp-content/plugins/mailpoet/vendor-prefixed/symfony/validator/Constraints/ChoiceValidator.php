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
 * ChoiceValidator validates that the value is one of the expected values.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Florian Eckerstorfer <florian@eckerstorfer.org>
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class ChoiceValidator extends \MailPoetVendor\Symfony\Component\Validator\ConstraintValidator
{
    /**
     * {@inheritdoc}
     */
    public function validate($value, \MailPoetVendor\Symfony\Component\Validator\Constraint $constraint)
    {
        if (!$constraint instanceof \MailPoetVendor\Symfony\Component\Validator\Constraints\Choice) {
            throw new \MailPoetVendor\Symfony\Component\Validator\Exception\UnexpectedTypeException($constraint, __NAMESPACE__ . '\\Choice');
        }
        if (!\is_array($constraint->choices) && !$constraint->callback) {
            throw new \MailPoetVendor\Symfony\Component\Validator\Exception\ConstraintDefinitionException('Either "choices" or "callback" must be specified on constraint Choice');
        }
        if (null === $value) {
            return;
        }
        if ($constraint->multiple && !\is_array($value)) {
            throw new \MailPoetVendor\Symfony\Component\Validator\Exception\UnexpectedTypeException($value, 'array');
        }
        if ($constraint->callback) {
            if (!\is_callable($choices = [$this->context->getObject(), $constraint->callback]) && !\is_callable($choices = [$this->context->getClassName(), $constraint->callback]) && !\is_callable($choices = $constraint->callback)) {
                throw new \MailPoetVendor\Symfony\Component\Validator\Exception\ConstraintDefinitionException('The Choice constraint expects a valid callback');
            }
            $choices = \call_user_func($choices);
        } else {
            $choices = $constraint->choices;
        }
        if (\true !== $constraint->strict) {
            @\trigger_error('Not setting the strict option of the Choice constraint to true is deprecated since Symfony 3.4 and will throw an exception in 4.0.', \E_USER_DEPRECATED);
        }
        if ($constraint->multiple) {
            foreach ($value as $_value) {
                if (!\in_array($_value, $choices, $constraint->strict)) {
                    $this->context->buildViolation($constraint->multipleMessage)->setParameter('{{ value }}', $this->formatValue($_value))->setCode(\MailPoetVendor\Symfony\Component\Validator\Constraints\Choice::NO_SUCH_CHOICE_ERROR)->setInvalidValue($_value)->addViolation();
                    return;
                }
            }
            $count = \count($value);
            if (null !== $constraint->min && $count < $constraint->min) {
                $this->context->buildViolation($constraint->minMessage)->setParameter('{{ limit }}', $constraint->min)->setPlural((int) $constraint->min)->setCode(\MailPoetVendor\Symfony\Component\Validator\Constraints\Choice::TOO_FEW_ERROR)->addViolation();
                return;
            }
            if (null !== $constraint->max && $count > $constraint->max) {
                $this->context->buildViolation($constraint->maxMessage)->setParameter('{{ limit }}', $constraint->max)->setPlural((int) $constraint->max)->setCode(\MailPoetVendor\Symfony\Component\Validator\Constraints\Choice::TOO_MANY_ERROR)->addViolation();
                return;
            }
        } elseif (!\in_array($value, $choices, $constraint->strict)) {
            $this->context->buildViolation($constraint->message)->setParameter('{{ value }}', $this->formatValue($value))->setCode(\MailPoetVendor\Symfony\Component\Validator\Constraints\Choice::NO_SUCH_CHOICE_ERROR)->addViolation();
        }
    }
}
