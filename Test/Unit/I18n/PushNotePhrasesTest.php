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

namespace Buckaroo\Magento2\Test\Unit\I18n;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Every shipped locale must translate the phrases that end up in push order notes.
 */
class PushNotePhrasesTest extends TestCase
{
    private const PHRASES = [
        'Payment Push Status: %1',
        'Payment failed: %1',
        'Waiting for user input',
        'Waiting for processor',
        'Waiting on consumer action',
        'Payment on hold',
        'Credit Management invoice with a total amount of %1 has been paid',
        'Credit Management invoice has been (partially) refunded',
        'Unknown response code: %1',
        'Payment failure',
        'Validation error',
        'Technical error',
        'Payment rejected',
        'Cancelled by consumer',
        'Cancelled by merchant',
        'Success',
        'Authorization status: %1',
        'Capture status: %1',
        'Total amount of %1 has been paid',
        'Amount of %1 has been paid',
        'Total amount of %1 has been authorized. Payment will be captured when order is shipped.',
        'Total amount of %1 has been authorized. Please create an invoice to capture the authorized amount.',
        'Total amount of %1 has been captured. Invoice will be created on shipment.',
        'Total amount of %1 has been captured.',
        'The received amount of %1 does not settle the order. The order is held for review.',
    ];

    public static function localeProvider(): array
    {
        $files = glob(dirname(__DIR__, 3) . '/i18n/*.csv') ?: [];
        $locales = [];
        foreach ($files as $file) {
            if (basename($file) !== 'en_US.csv') {
                $locales[basename($file, '.csv')] = [$file];
            }
        }

        return $locales;
    }

    #[DataProvider('localeProvider')]
    public function testLocaleTranslatesThePushNotePhrases(string $file): void
    {
        $translations = [];
        $handle = fopen($file, 'r');
        while (($row = fgetcsv($handle, 0, ',', '"', '\\')) !== false) {
            if (count($row) >= 2) {
                $translations[$row[0]] = $row[1];
            }
        }
        fclose($handle);

        foreach (self::PHRASES as $phrase) {
            $this->assertArrayHasKey($phrase, $translations, basename($file) . ' misses "' . $phrase . '"');
            $this->assertNotSame('', trim($translations[$phrase]));
            if (str_contains($phrase, '%1')) {
                $this->assertStringContainsString('%1', $translations[$phrase], basename($file) . ': ' . $phrase);
            }
        }
    }

    public function testEnglishUsesThePushMessageWording(): void
    {
        $handle = fopen(dirname(__DIR__, 3) . '/i18n/en_US.csv', 'r');
        $translations = [];
        while (($row = fgetcsv($handle, 0, ',', '"', '\\')) !== false) {
            if (count($row) >= 2) {
                $translations[$row[0]] = $row[1];
            }
        }
        fclose($handle);

        $this->assertSame('Waiting for consumer', $translations['Waiting on consumer action'] ?? null);
    }
}
