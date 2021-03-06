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


use MailPoetVendor\Symfony\Component\ExpressionLanguage\ExpressionLanguage;
use MailPoetVendor\Symfony\Component\Validator\Constraint;
use MailPoetVendor\Symfony\Component\Validator\ConstraintValidator;
use MailPoetVendor\Symfony\Component\Validator\Exception\RuntimeException;
use MailPoetVendor\Symfony\Component\Validator\Exception\UnexpectedTypeException;
/**
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Bernhard Schussek <bschussek@symfony.com>
 */
class ExpressionValidator extends \MailPoetVendor\Symfony\Component\Validator\ConstraintValidator
{
    private $expressionLanguage;
    public function __construct($propertyAccessor = null, \MailPoetVendor\Symfony\Component\ExpressionLanguage\ExpressionLanguage $expressionLanguage = null)
    {
        $this->expressionLanguage = $expressionLanguage;
    }
    /**
     * {@inheritdoc}
     */
    public function validate($value, \MailPoetVendor\Symfony\Component\Validator\Constraint $constraint)
    {
        if (!$constraint instanceof \MailPoetVendor\Symfony\Component\Validator\Constraints\Expression) {
            throw new \MailPoetVendor\Symfony\Component\Validator\Exception\UnexpectedTypeException($constraint, __NAMESPACE__ . '\\Expression');
        }
        $variables = [];
        $variables['value'] = $value;
        $variables['this'] = $this->context->getObject();
        if (!$this->getExpressionLanguage()->evaluate($constraint->expression, $variables)) {
            $this->context->buildViolation($constraint->message)->setParameter('{{ value }}', $this->formatValue($value, self::OBJECT_TO_STRING))->setCode(\MailPoetVendor\Symfony\Component\Validator\Constraints\Expression::EXPRESSION_FAILED_ERROR)->addViolation();
        }
    }
    private function getExpressionLanguage()
    {
        if (null === $this->expressionLanguage) {
            if (!\class_exists('MailPoetVendor\\Symfony\\Component\\ExpressionLanguage\\ExpressionLanguage')) {
                throw new \MailPoetVendor\Symfony\Component\Validator\Exception\RuntimeException('Unable to use expressions as the Symfony ExpressionLanguage component is not installed.');
            }
            $this->expressionLanguage = new \MailPoetVendor\Symfony\Component\ExpressionLanguage\ExpressionLanguage();
        }
        return $this->expressionLanguage;
    }
}
