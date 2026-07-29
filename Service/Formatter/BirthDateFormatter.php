<?php
/**
 * NOTICE OF LICENSE
 *
 * This source file is subject to the MIT License
 * It is available through the world-wide-web at this URL:
 * https://tldrlegal.com/license/mit-license
 * If you are unable to obtain it through the world-wide-web, please send an email
 * to support@buckaroo.nl so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade this module to newer
 * versions in the future. If you wish to customize this module for your
 * needs please contact support@buckaroo.nl for more information.
 *
 * @copyright Copyright (c) Buckaroo B.V.
 * @license   https://tldrlegal.com/license/mit-license
 */

declare(strict_types=1);

namespace Buckaroo\Magento2\Service\Formatter;

use Buckaroo\Magento2\Logging\BuckarooLoggerInterface;

/**
 * Turns a customer birthdate of unknown provenance into a formatted date string.
 *
 * The value can arrive from the checkout DoB field (which accepts dd/mm/yyyy,
 * dd-mm-yyyy and dd.mm.yyyy), from the order's customer_dob column (a datetime
 * string), or not at all - admin orders, REST/headless orders and ERP imports
 * have no DoB field to fill in.
 *
 * Callers used to hand strtotime()'s result straight to date(). strtotime()
 * returns false for anything it cannot parse, and under strict_types that false
 * is a fatal "date(): Argument #2 ($timestamp) must be of type ?int, false
 * given" rather than a wrong date. This class is the single place where that
 * result is validated.
 */
class BirthDateFormatter
{
    /**
     * Day-month-year, what most Buckaroo services expect. Klarna and in3 ask for
     * Y-m-d and pass their own format.
     */
    public const DEFAULT_FORMAT = 'd-m-Y';

    /**
     * Long-standing placeholder for orders that carry no birthdate. Kept so
     * formatOrDefault() reproduces the behaviour the data builders already had
     * when the order's customer_dob was null.
     */
    public const DEFAULT_BIRTH_DATE = '1990-01-01';

    /**
     * Both are valid day/month/year separators in the checkout DoB field, and
     * strtotime() reads d-m-Y as day-first, which is what the field collects.
     */
    private const SEPARATORS = ['/', '.'];

    /**
     * @var BuckarooLoggerInterface
     */
    private $logger;

    /**
     * @param BuckarooLoggerInterface $logger
     */
    public function __construct(BuckarooLoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    /**
     * Format a birthdate, or return null when there is nothing usable to format.
     *
     * Use this when the receiving service tolerates a missing birthDate; use
     * formatOrDefault() when it does not.
     *
     * @param string|null $rawDate
     * @param string      $format
     *
     * @return string|null null when the value is absent or cannot be parsed
     */
    public function format(?string $rawDate, string $format = self::DEFAULT_FORMAT): ?string
    {
        $normalized = str_replace(self::SEPARATORS, '-', trim((string)$rawDate));

        // No birthdate at all is ordinary, not a data problem - don't warn about it.
        if ($normalized === '') {
            return null;
        }

        $timestamp = strtotime($normalized);

        if ($timestamp === false) {
            $this->logger->addWarning(sprintf(
                '[Buckaroo] Could not parse customer birthdate "%s"; sending no birthDate instead.',
                $rawDate
            ));

            return null;
        }

        return date($format, $timestamp);
    }

    /**
     * Format a birthdate, falling back to the placeholder date when the value is
     * absent or unparsable.
     *
     * @param string|null $rawDate
     * @param string      $format
     *
     * @return string
     */
    public function formatOrDefault(?string $rawDate, string $format = self::DEFAULT_FORMAT): string
    {
        $formatted = $this->format($rawDate, $format);

        if ($formatted !== null) {
            return $formatted;
        }

        return date($format, (int)strtotime(self::DEFAULT_BIRTH_DATE));
    }
}
