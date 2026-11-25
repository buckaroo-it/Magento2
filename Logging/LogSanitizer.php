<?php
/**
 * Buckaroo Magento 2 payment module (https://www.buckaroo.eu/)
 *
 * Copyright (c) Buckaroo B.V.
 * See LICENSE for license details.
 *
 * Support: support@buckaroo.nl
 *
 * @copyright Copyright (c) Buckaroo B.V.
 * @license   MIT
 */

declare(strict_types=1);

namespace Buckaroo\Magento2\Logging;

// phpcs:disable Magento2.Functions.StaticFunction -- pure stateless formatter, usable from any logging call site
/**
 * Make untrusted data safe to write to the Buckaroo log
 *
 * Redacts personal and payment data, and renders values on a single line so a value
 * containing CR/LF cannot start a forged log record.
 */
class LogSanitizer
{
    public const REDACTED = '***REDACTED***';

    /**
     * Line breaks as JSON unicode escapes, which Monolog does not expand
     */
    private const ESCAPED_LF = '\\u000a';
    private const ESCAPED_CR = '\\u000d';

    /**
     * Keys (lowercase, without the brq_/add_ prefix) whose values are always redacted
     */
    private const PII_KEYS = [
        'customer_name', 'customername', 'consumername', 'customeraccountname', 'accountname',
        'customeriban', 'customerbic', 'consumeriban', 'consumerbic', 'iban', 'bic',
        'customeremail', 'consumeremail', 'email', 'phone', 'phonenumber', 'mobilephone',
        'firstname', 'lastname', 'street', 'housenumber', 'zipcode', 'postalcode', 'city',
        'card_number', 'cardnumber', 'cardnumberending', 'encryptedcarddata', 'encrypteddata',
        'cvc', 'cvv', 'expirydate', 'paymentdata', 'paymenttoken', 'token', 'birthdate',
        'dateofbirth', 'socialsecuritynumber', 'identificationnumber', 'chamberofcommerce',
        'vatnumber', 'customer', 'billing', 'shipping', 'billingcustomer', 'shippingcustomer',
        'address', 'secretkey', 'websitekey', 'password',
    ];

    /**
     * Key fragments (lowercase) whose values are always redacted
     */
    private const PII_KEY_FRAGMENTS = [
        'iban', 'bic', 'email', 'phone', 'street', 'firstname', 'lastname', 'billing_', 'shipping_',
        'customer_', 'cardnumber', 'card_number',
    ];

    /**
     * Prefix of push fields that carry service-specific (often personal) data
     */
    private const SERVICE_KEY_PREFIX = 'brq_service_';

    /**
     * Redact sensitive values in a (nested) array, matching keys case-insensitively
     *
     * @param array $data
     * @return array
     */
    public static function redact(array $data): array
    {
        foreach ($data as $key => $value) {
            if (is_string($key) && self::isSensitiveKey($key)) {
                $data[$key] = self::REDACTED;
                continue;
            }

            if (is_array($value)) {
                $data[$key] = self::redactList($value);
            }
        }

        return $data;
    }

    /**
     * Render a value for the log on a single line, with sensitive data redacted
     *
     * @param mixed $value
     * @return string
     */
    public static function export($value): string
    {
        if (is_object($value)) {
            $value = method_exists($value, 'toArray') ? $value->toArray() : get_class($value);
        }

        if (is_array($value)) {
            $value = self::redact($value);
        }

        $encoded = json_encode(
            $value,
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE
        );

        return $encoded === false ? '"[unserializable]"' : self::defuseEscapedLineBreaks($encoded);
    }

    /**
     * Neutralize line breaks in a string so it cannot start a new log record
     *
     * @param string $value
     * @return string
     */
    public static function singleLine(string $value): string
    {
        return str_replace(["\r", "\n"], [self::ESCAPED_CR, self::ESCAPED_LF], $value);
    }

    /**
     * Make a log message unable to forge a separate log record
     *
     * Real line breaks get an indented continuation line, so multi-line dumps stay readable
     * while no injected line can start at the beginning of a line like a real record does.
     * Escaped "\n"/"\r" sequences are rewritten as well: Magento's log formatter allows inline
     * line breaks, and for a message that starts with "[" or "{" Monolog turns those escapes
     * back into real line breaks after this logger has run.
     *
     * @param string $message
     * @return string
     */
    public static function neutralizeLineBreaks(string $message): string
    {
        $message = str_replace(["\r\n", "\r"], "\n", self::defuseEscapedLineBreaks($message));

        return str_replace("\n", "\n    ", $message);
    }

    /**
     * Rewrite the escapes Monolog would expand into real line breaks as JSON unicode escapes
     *
     * Matches exactly what Monolog's LineFormatter expands (a backslash, not itself escaped,
     * followed by r or n), so JSON stays valid and decodes to the same value.
     *
     * @param string $value
     * @return string
     */
    private static function defuseEscapedLineBreaks(string $value): string
    {
        return (string) preg_replace_callback(
            '/(?<!\\\\)\\\\([rn])/',
            static function (array $match): string {
                return $match[1] === 'n' ? self::ESCAPED_LF : self::ESCAPED_CR;
            },
            $value
        );
    }

    /**
     * Redact the entries of a nested list
     *
     * Items such as Buckaroo's Services[].Parameters[] carry the sensitive key in a "Name" field
     * instead of in the array key.
     *
     * @param array $data
     * @return array
     */
    private static function redactList(array $data): array
    {
        $data = self::redact($data);

        foreach (['Name', 'name'] as $nameKey) {
            if (isset($data[$nameKey]) && is_string($data[$nameKey]) && self::isSensitiveKey($data[$nameKey])) {
                foreach (['Value', 'value'] as $valueKey) {
                    if (array_key_exists($valueKey, $data)) {
                        $data[$valueKey] = self::REDACTED;
                    }
                }
            }
        }

        return $data;
    }

    /**
     * Whether the value of this key must not be logged
     *
     * @param string $key
     * @return bool
     */
    private static function isSensitiveKey(string $key): bool
    {
        $key = strtolower($key);

        if (strpos($key, self::SERVICE_KEY_PREFIX) === 0) {
            return true;
        }

        $bare = (string) preg_replace('/^(brq_|add_|cust_)/', '', $key);
        if (in_array($bare, self::PII_KEYS, true)) {
            return true;
        }

        foreach (self::PII_KEY_FRAGMENTS as $fragment) {
            if (strpos($bare, $fragment) !== false) {
                return true;
            }
        }

        return false;
    }
}
