<?php
declare(strict_types=1);

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

namespace Buckaroo\Magento2\Test\Unit\Service\Formatter;

use Buckaroo\Magento2\Logging\BuckarooLoggerInterface;
use Buckaroo\Magento2\Service\Formatter\BirthDateFormatter;
use Buckaroo\Magento2\Test\BaseTest;
use PHPUnit\Framework\Attributes\DataProvider;

class BirthDateFormatterTest extends BaseTest
{
    protected $instanceClass = BirthDateFormatter::class;

    /**
     * @param BuckarooLoggerInterface|null $logger
     *
     * @return BirthDateFormatter
     */
    private function getFormatter($logger = null): BirthDateFormatter
    {
        return $this->getInstance([
            'logger' => $logger ?? $this->createMock(BuckarooLoggerInterface::class),
        ]);
    }

    /**
     * Every separator the checkout accepts (and the datetime string Magento stores
     * on the order) must produce the same day-month-year output.
     *
     * @return array<string, array{0: string, 1: string}>
     */
    public static function parsableDateProvider(): array
    {
        return [
            'slashes (checkout default)'   => ['31/12/1990', '31-12-1990'],
            'dashes'                       => ['31-12-1990', '31-12-1990'],
            'dots (accepted since v2.5.1)' => ['31.12.1990', '31-12-1990'],
            'single digit day and month'   => ['1/1/1990', '01-01-1990'],
            'iso date'                     => ['1990-01-01', '01-01-1990'],
            'order customer_dob datetime'  => ['1990-01-01 00:00:00', '01-01-1990'],
            'surrounding whitespace'       => ['  31/12/1990  ', '31-12-1990'],
        ];
    }

    /**
     * @param string $raw
     * @param string $expected
     */
    #[DataProvider('parsableDateProvider')]
    public function testFormatParsesEverySeparatorTheCheckoutAccepts(string $raw, string $expected): void
    {
        $this->assertSame($expected, $this->getFormatter()->format($raw));
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
     *
     * @return array<string, array{0: string|null}>
     */
    public static function unusableDateProvider(): array
    {
        return [
            'null'                     => [null],
            'empty string'             => [''],
            'whitespace only'          => ['   '],
            'month out of range (US)'  => ['12/31/1990'],
            'day out of range'         => ['32/01/1990'],
            'not a date at all'        => ['DD-MM-YYYY'],
        ];
    }

    /**
     * @param string|null $raw
     */
    #[DataProvider('unusableDateProvider')]
    public function testFormatReturnsNullInsteadOfThrowingOnUnusableInput(?string $raw): void
    {
        $this->assertNull($this->getFormatter()->format($raw));
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
