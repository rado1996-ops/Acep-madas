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
class TypeValidator extends \MailPoetVendor\Symfony\Component\Validator\ConstraintValidator
{
    /**
     * {@inheritdoc}
     */
    public function validate($value, \MailPoetVendor\Symfony\Component\Validator\Constraint $constraint)
    {
        if (!$constraint instanceof \MailPoetVendor\Symfony\Component\Validator\Constraints\Type) {
            throw new \MailPoetVendor\Symfony\Component\Validator\Exception\UnexpectedTypeException($constraint, __NAMESPACE__ . '\\Type');
        }
        if (null === $value) {
            return;
        }
        $type = \strtolower($constraint->type);
        $type = 'boolean' == $type ? 'bool' : $constraint->type;
        $isFunction = 'is_' . $type;
        $ctypeFunction = 'ctype_' . $type;
        if (\function_exists($isFunction) && $isFunction($value)) {
            return;
        } elseif (\function_exists($ctypeFunction) && $ctypeFunction($value)) {
            return;
        } elseif ($value instanceof $constraint->type) {
            return;
        }
        $this->context->buildViolation($constraint->message)->setParameter('{{ value }}', $this->formatValue($value))->setParameter('{{ type }}', $constraint->type)->setCode(\MailPoetVendor\Symfony\Component\Validator\Constraints\Type::INVALID_TYPE_ERROR)->addViolation();
    }
}
