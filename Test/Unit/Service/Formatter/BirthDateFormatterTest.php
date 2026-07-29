<?php
/**
 * NOTICE OF LICENSE
 *
 * This source file is subject to the MIT License
 * It is available through the world-wide-web at this URL:
 * https://tldrlegal.com/license/mit-license
 * If you are unable to obtain it through the world-wide-web, please email
 * to support@buckaroo.nl, so we can send you a copy immediately.
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

namespace Buckaroo\Magento2\Test\Unit\Service\Formatter;

use Buckaroo\Magento2\Logging\BuckarooLoggerInterface;
use Buckaroo\Magento2\Service\Formatter\BirthDateFormatter;
use PHPUnit\Framework\TestCase;

class BirthDateFormatterTest extends TestCase
{
    /**
     * @param BuckarooLoggerInterface|null $logger
     *
     * @return BirthDateFormatter
     */
    private function getFormatter($logger = null): BirthDateFormatter
    {
        return new BirthDateFormatter($logger ?? $this->createMock(BuckarooLoggerInterface::class));
    }

    /**
     * Every separator the checkout accepts (and the datetime string Magento stores
     * on the order) must produce the same day-month-year output.
     */
    public function testFormatParsesEverySeparatorTheCheckoutAccepts(): void
    {
        $cases = [
            'slashes (checkout default)'   => ['31/12/1990', '31-12-1990'],
            'dashes'                       => ['31-12-1990', '31-12-1990'],
            'dots (accepted since v2.5.1)' => ['31.12.1990', '31-12-1990'],
            'single digit day and month'   => ['1/1/1990', '01-01-1990'],
            'iso date'                     => ['1990-01-01', '01-01-1990'],
            'order customer_dob datetime'  => ['1990-01-01 00:00:00', '01-01-1990'],
            'surrounding whitespace'       => ['  31/12/1990  ', '31-12-1990'],
        ];

        foreach ($cases as $label => [$raw, $expected]) {
            $this->assertSame($expected, $this->getFormatter()->format($raw), $label);
        }
    }

    /**
     * Klarna and in3 ask for Y-m-d rather than the default d-m-Y.
     */
    public function testFormatHonoursTheRequestedOutputFormat(): void
    {
        $this->assertSame('1990-12-31', $this->getFormatter()->format('31/12/1990', 'Y-m-d'));
    }

    /**
     * These are the inputs that used to reach date() as `false` and throw
     * "TypeError: date(): Argument #2 ($timestamp) must be of type ?int, false given".
     */
    public function testFormatReturnsNullInsteadOfThrowingOnUnusableInput(): void
    {
        $cases = [
            'null'                     => null,
            'empty string'             => '',
            'whitespace only'          => '   ',
            'month out of range (US)'  => '12/31/1990',
            'day out of range'         => '32/01/1990',
            'not a date at all'        => 'DD-MM-YYYY',
        ];

        foreach ($cases as $label => $raw) {
            $this->assertNull($this->getFormatter()->format($raw), $label);
        }
    }

    /**
     * An unparsable value is a data problem worth investigating, so it must be
     * logged with the offending value.
     */
    public function testFormatLogsUnparsableValues(): void
    {
        $logger = $this->createMock(BuckarooLoggerInterface::class);
        $logger->expects($this->once())
            ->method('addWarning')
            ->with($this->stringContains('12/31/1990'));

        $this->assertNull($this->getFormatter($logger)->format('12/31/1990'));
    }

    /**
     * A simply absent birthdate is normal (guest checkout, admin order, API order)
     * and must not be logged as a warning.
     */
    public function testFormatDoesNotWarnWhenNoBirthDateWasSupplied(): void
    {
        $logger = $this->createMock(BuckarooLoggerInterface::class);
        $logger->expects($this->never())->method('addWarning');

        $this->assertNull($this->getFormatter($logger)->format(null));
    }

    /**
     * formatOrDefault is for the callers that must always send a birthDate; it
     * substitutes the long-standing 1990-01-01 placeholder rather than throwing.
     */
    public function testFormatOrDefaultFallsBackToThePlaceholderDate(): void
    {
        $formatter = $this->getFormatter();

        $this->assertSame('01-01-1990', $formatter->formatOrDefault(''));
        $this->assertSame('01-01-1990', $formatter->formatOrDefault('12/31/1990'));
        $this->assertSame('1990-01-01', $formatter->formatOrDefault(null, 'Y-m-d'));
    }

    /**
     * A usable value must never be replaced by the placeholder.
     */
    public function testFormatOrDefaultKeepsAUsableDate(): void
    {
        $this->assertSame('31-12-1990', $this->getFormatter()->formatOrDefault('31/12/1990'));
    }
}
