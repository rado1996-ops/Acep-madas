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
use MailPoetVendor\Symfony\Component\Validator\Exception\InvalidOptionsException;
use MailPoetVendor\Symfony\Component\Validator\Exception\UnexpectedTypeException;
/**
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class UrlValidator extends \MailPoetVendor\Symfony\Component\Validator\ConstraintValidator
{
    const PATTERN = '~^
            (%s)://                                 # protocol
            (([\\.\\pL\\pN-]+:)?([\\.\\pL\\pN-]+)@)?      # basic auth
            (
                ([\\pL\\pN\\pS\\-\\_\\.])+(\\.?([\\pL\\pN]|xn\\-\\-[\\pL\\pN-]+)+\\.?) # a domain name
                    |                                                 # or
                \\d{1,3}\\.\\d{1,3}\\.\\d{1,3}\\.\\d{1,3}                    # an IP address
                    |                                                 # or
                \\[
                    (?:(?:(?:(?:(?:(?:(?:[0-9a-f]{1,4})):){6})(?:(?:(?:(?:(?:[0-9a-f]{1,4})):(?:(?:[0-9a-f]{1,4})))|(?:(?:(?:(?:(?:25[0-5]|(?:[1-9]|1[0-9]|2[0-4])?[0-9]))\\.){3}(?:(?:25[0-5]|(?:[1-9]|1[0-9]|2[0-4])?[0-9])))))))|(?:(?:::(?:(?:(?:[0-9a-f]{1,4})):){5})(?:(?:(?:(?:(?:[0-9a-f]{1,4})):(?:(?:[0-9a-f]{1,4})))|(?:(?:(?:(?:(?:25[0-5]|(?:[1-9]|1[0-9]|2[0-4])?[0-9]))\\.){3}(?:(?:25[0-5]|(?:[1-9]|1[0-9]|2[0-4])?[0-9])))))))|(?:(?:(?:(?:(?:[0-9a-f]{1,4})))?::(?:(?:(?:[0-9a-f]{1,4})):){4})(?:(?:(?:(?:(?:[0-9a-f]{1,4})):(?:(?:[0-9a-f]{1,4})))|(?:(?:(?:(?:(?:25[0-5]|(?:[1-9]|1[0-9]|2[0-4])?[0-9]))\\.){3}(?:(?:25[0-5]|(?:[1-9]|1[0-9]|2[0-4])?[0-9])))))))|(?:(?:(?:(?:(?:(?:[0-9a-f]{1,4})):){0,1}(?:(?:[0-9a-f]{1,4})))?::(?:(?:(?:[0-9a-f]{1,4})):){3})(?:(?:(?:(?:(?:[0-9a-f]{1,4})):(?:(?:[0-9a-f]{1,4})))|(?:(?:(?:(?:(?:25[0-5]|(?:[1-9]|1[0-9]|2[0-4])?[0-9]))\\.){3}(?:(?:25[0-5]|(?:[1-9]|1[0-9]|2[0-4])?[0-9])))))))|(?:(?:(?:(?:(?:(?:[0-9a-f]{1,4})):){0,2}(?:(?:[0-9a-f]{1,4})))?::(?:(?:(?:[0-9a-f]{1,4})):){2})(?:(?:(?:(?:(?:[0-9a-f]{1,4})):(?:(?:[0-9a-f]{1,4})))|(?:(?:(?:(?:(?:25[0-5]|(?:[1-9]|1[0-9]|2[0-4])?[0-9]))\\.){3}(?:(?:25[0-5]|(?:[1-9]|1[0-9]|2[0-4])?[0-9])))))))|(?:(?:(?:(?:(?:(?:[0-9a-f]{1,4})):){0,3}(?:(?:[0-9a-f]{1,4})))?::(?:(?:[0-9a-f]{1,4})):)(?:(?:(?:(?:(?:[0-9a-f]{1,4})):(?:(?:[0-9a-f]{1,4})))|(?:(?:(?:(?:(?:25[0-5]|(?:[1-9]|1[0-9]|2[0-4])?[0-9]))\\.){3}(?:(?:25[0-5]|(?:[1-9]|1[0-9]|2[0-4])?[0-9])))))))|(?:(?:(?:(?:(?:(?:[0-9a-f]{1,4})):){0,4}(?:(?:[0-9a-f]{1,4})))?::)(?:(?:(?:(?:(?:[0-9a-f]{1,4})):(?:(?:[0-9a-f]{1,4})))|(?:(?:(?:(?:(?:25[0-5]|(?:[1-9]|1[0-9]|2[0-4])?[0-9]))\\.){3}(?:(?:25[0-5]|(?:[1-9]|1[0-9]|2[0-4])?[0-9])))))))|(?:(?:(?:(?:(?:(?:[0-9a-f]{1,4})):){0,5}(?:(?:[0-9a-f]{1,4})))?::)(?:(?:[0-9a-f]{1,4})))|(?:(?:(?:(?:(?:(?:[0-9a-f]{1,4})):){0,6}(?:(?:[0-9a-f]{1,4})))?::))))
                \\]  # an IPv6 address
            )
            (:[0-9]+)?                              # a port (optional)
            (?:/ (?:[\\pL\\pN\\-._\\~!$&\'()*+,;=:@]|%%[0-9A-Fa-f]{2})* )*      # a path
            (?:\\? (?:[\\pL\\pN\\-._\\~!$&\'()*+,;=:@/?]|%%[0-9A-Fa-f]{2})* )?   # a query (optional)
            (?:\\# (?:[\\pL\\pN\\-._\\~!$&\'()*+,;=:@/?]|%%[0-9A-Fa-f]{2})* )?   # a fragment (optional)
        $~ixu';
    /**
     * {@inheritdoc}
     */
    public function validate($value, \MailPoetVendor\Symfony\Component\Validator\Constraint $constraint)
    {
        if (!$constraint instanceof \MailPoetVendor\Symfony\Component\Validator\Constraints\Url) {
            throw new \MailPoetVendor\Symfony\Component\Validator\Exception\UnexpectedTypeException($constraint, __NAMESPACE__ . '\\Url');
        }
        if (null === $value || '' === $value) {
            return;
        }
        if (!\is_scalar($value) && !(\is_object($value) && \method_exists($value, '__toString'))) {
            throw new \MailPoetVendor\Symfony\Component\Validator\Exception\UnexpectedTypeException($value, 'string');
        }
        $value = (string) $value;
        if ('' === $value) {
            return;
        }
        $pattern = \sprintf(static::PATTERN, \implode('|', $constraint->protocols));
        if (!\preg_match($pattern, $value)) {
            $this->context->buildViolation($constraint->message)->setParameter('{{ value }}', $this->formatValue($value))->setCode(\MailPoetVendor\Symfony\Component\Validator\Constraints\Url::INVALID_URL_ERROR)->addViolation();
            return;
        }
        if ($constraint->checkDNS) {
            // backwards compatibility
            if (\true === $constraint->checkDNS) {
                $constraint->checkDNS = \MailPoetVendor\Symfony\Component\Validator\Constraints\Url::CHECK_DNS_TYPE_ANY;
                @\trigger_error(\sprintf('Use of the boolean TRUE for the "checkDNS" option in %s is deprecated.  Use Url::CHECK_DNS_TYPE_ANY instead.', \MailPoetVendor\Symfony\Component\Validator\Constraints\Url::class), \E_USER_DEPRECATED);
            }
            if (!\in_array($constraint->checkDNS, [\MailPoetVendor\Symfony\Component\Validator\Constraints\Url::CHECK_DNS_TYPE_ANY, \MailPoetVendor\Symfony\Component\Validator\Constraints\Url::CHECK_DNS_TYPE_A, \MailPoetVendor\Symfony\Component\Validator\Constraints\Url::CHECK_DNS_TYPE_A6, \MailPoetVendor\Symfony\Component\Validator\Constraints\Url::CHECK_DNS_TYPE_AAAA, \MailPoetVendor\Symfony\Component\Validator\Constraints\Url::CHECK_DNS_TYPE_CNAME, \MailPoetVendor\Symfony\Component\Validator\Constraints\Url::CHECK_DNS_TYPE_MX, \MailPoetVendor\Symfony\Component\Validator\Constraints\Url::CHECK_DNS_TYPE_NAPTR, \MailPoetVendor\Symfony\Component\Validator\Constraints\Url::CHECK_DNS_TYPE_NS, \MailPoetVendor\Symfony\Component\Validator\Constraints\Url::CHECK_DNS_TYPE_PTR, \MailPoetVendor\Symfony\Component\Validator\Constraints\Url::CHECK_DNS_TYPE_SOA, \MailPoetVendor\Symfony\Component\Validator\Constraints\Url::CHECK_DNS_TYPE_SRV, \MailPoetVendor\Symfony\Component\Validator\Constraints\Url::CHECK_DNS_TYPE_TXT], \true)) {
                throw new \MailPoetVendor\Symfony\Component\Validator\Exception\InvalidOptionsException(\sprintf('Invalid value for option "checkDNS" in constraint %s', \get_class($constraint)), ['checkDNS']);
            }
            $host = \parse_url($value, \PHP_URL_HOST);
            if (!\is_string($host) || !\checkdnsrr($host, $constraint->checkDNS)) {
                $this->context->buildViolation($constraint->dnsMessage)->setParameter('{{ value }}', $this->formatValue($host))->setCode(\MailPoetVendor\Symfony\Component\Validator\Constraints\Url::INVALID_URL_ERROR)->addViolation();
            }
        }
    }
}
