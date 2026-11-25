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

namespace Buckaroo\Magento2\Test\Unit\Logging;

use Buckaroo\Magento2\Logging\LogSanitizer;
use Monolog\Formatter\LineFormatter;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class LogSanitizerTest extends TestCase
{
    public function testRedactsPushFieldsWhateverTheirCasing(): void
    {
        $redacted = LogSanitizer::redact([
            'BRQ_CUSTOMER_NAME' => 'J. Jansen',
            'brq_SERVICE_ideal_consumerIBAN' => 'NL44RABO0123456789',
            'brq_service_transfer_bic' => 'RABONL2U',
            'Brq_Billing_Email' => 'j@example.com',
            'ADD_customerIban' => 'NL44RABO0123456789',
            'brq_statuscode' => '190',
            'brq_invoicenumber' => '000000001',
            'brq_transactions' => 'ABC123',
        ]);

        foreach (['BRQ_CUSTOMER_NAME', 'brq_SERVICE_ideal_consumerIBAN', 'brq_service_transfer_bic',
            'Brq_Billing_Email', 'ADD_customerIban'] as $key) {
            $this->assertSame(LogSanitizer::REDACTED, $redacted[$key], $key);
        }
        $this->assertSame('190', $redacted['brq_statuscode']);
        $this->assertSame('000000001', $redacted['brq_invoicenumber']);
        $this->assertSame('ABC123', $redacted['brq_transactions']);
    }

    public function testRedactsAddressAndIdentityVariants(): void
    {
        $redacted = LogSanitizer::redact([
            'HouseNumberAddition' => 'B',
            'CustomerBirthDate' => '1990-01-01',
            'Initials' => 'J.',
            'IPAddress' => '192.0.2.10',
            'billingZipCode' => '1234AB',
            'shippingPostalCode' => '1234AB',
            'Currency' => 'EUR',
            'AmountDebit' => '39.00',
        ]);

        foreach (['HouseNumberAddition', 'CustomerBirthDate', 'Initials', 'IPAddress', 'billingZipCode',
            'shippingPostalCode'] as $key) {
            $this->assertSame(LogSanitizer::REDACTED, $redacted[$key], $key);
        }
        $this->assertSame('EUR', $redacted['Currency']);
        $this->assertSame('39.00', $redacted['AmountDebit']);
    }

    public function testRedactsNestedJsonPushData(): void
    {
        $redacted = LogSanitizer::redact([
            'Transaction' => [
                'Key' => 'ABC123',
                'CustomerName' => 'J. Jansen',
                'Services' => [[
                    'Name' => 'ideal',
                    'Parameters' => [
                        ['Name' => 'consumerIBAN', 'Value' => 'NL44RABO0123456789'],
                        ['Name' => 'consumerIssuer', 'Value' => 'Rabobank'],
                    ],
                ]],
            ],
        ]);

        $this->assertSame('ABC123', $redacted['Transaction']['Key']);
        $this->assertSame(LogSanitizer::REDACTED, $redacted['Transaction']['CustomerName']);
        $parameters = $redacted['Transaction']['Services'][0]['Parameters'];
        $this->assertSame(LogSanitizer::REDACTED, $parameters[0]['Value']);
        $this->assertSame('Rabobank', $parameters[1]['Value']);
    }

    public function testExportKeepsAnInjectedLineBreakOnTheSameLine(): void
    {
        $value = ['id' => "abc\r\n[2026-11-25] buckaroo.CRITICAL: forged", 'path' => 'C:\\new'];
        $exported = LogSanitizer::export($value);

        $this->assertStringNotContainsString("\n", $exported);
        $this->assertStringNotContainsString("\r", $exported);
        // Still valid JSON that decodes to the original value
        $this->assertSame($value, json_decode($exported, true));
    }

    public function testSingleLineEscapesLineBreaks(): void
    {
        $this->assertSame('a\u000d\u000ab\u000ac\u000dd', LogSanitizer::singleLine("a\r\nb\nc\rd"));
    }

    /**
     * Magento's handlers format with inline line breaks allowed, which makes Monolog expand
     * escaped \n in messages that start with "[" or "{"
     *
     * @param string $message
     */
    #[DataProvider('injectedMessageProvider')]
    public function testNoInjectedLineStartsARecordAfterFormatting(string $message): void
    {
        $formatter = new LineFormatter(null, null, true);
        $record = [
            'message' => LogSanitizer::neutralizeLineBreaks($message),
            'context' => [],
            'level' => 100,
            'level_name' => 'DEBUG',
            'channel' => 'buckaroo',
            'datetime' => new \DateTimeImmutable(),
            'extra' => [],
        ];
        if (class_exists(\Monolog\LogRecord::class)) {
            $record = new \Monolog\LogRecord(
                $record['datetime'],
                $record['channel'],
                \Monolog\Level::Debug,
                $record['message']
            );
        }

        $lines = explode("\n", rtrim($formatter->format($record), "\n"));
        foreach (array_slice($lines, 1) as $line) {
            $this->assertStringStartsWith('    ', $line);
        }
        $this->assertStringNotContainsString("\r", implode('', $lines));
    }

    public static function injectedMessageProvider(): array
    {
        $forged = '[2026-11-25T09:00:00+00:00] buckaroo.CRITICAL: forged';

        return [
            'real line break' => ["[PUSH] request: abc\r\n" . $forged],
            'escaped line break in a message starting with [' => ['[PUSH] request: {"Key":"abc\r\n' . $forged . '"}'],
            'escaped line break in a message starting with {' => ['{"Key":"abc\n' . $forged . '"}'],
            'exported value' => ['[PUSH] request: ' . LogSanitizer::export(['Key' => "abc\r\n" . $forged])],
        ];
    }
}
