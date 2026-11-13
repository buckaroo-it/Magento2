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

namespace Buckaroo\Magento2\Test\Unit\Etc;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Every payment method field on the Payment Methods page must have a config path of its own.
 * Two fields that store into one path overwrite each other on save, so the value the merchant
 * picked in one of them is lost.
 */
class PaymentMethodConfigPathTest extends TestCase
{
    #[DataProvider('paymentMethodFileProvider')]
    public function testEachFieldHasItsOwnConfigPath(string $file): void
    {
        $xml = simplexml_load_file($file);
        $this->assertNotFalse($xml, 'Could not parse ' . basename($file));

        $paths = array_map('strval', $xml->xpath('//field/config_path'));
        $duplicates = array_keys(array_filter(array_count_values($paths), function ($count) {
            return $count > 1;
        }));

        $this->assertSame([], $duplicates, basename($file) . ' stores several fields into one config path');
    }

    /**
     * Fields that deliberately store outside their own payment method, by file.
     */
    private const SHARED_PATHS = [
        'giftcards.xml' => ['buckaroo_magento2/account/advanced_export_giftcards'],
    ];

    /**
     * A field that stores under another payment method's path is not read by its own method,
     * and it overwrites that other method's setting whenever the Payment Methods page is saved.
     */
    #[DataProvider('paymentMethodFileProvider')]
    public function testEachFieldStoresUnderItsOwnPaymentMethod(string $file): void
    {
        $xml = simplexml_load_file($file);
        $this->assertNotFalse($xml, 'Could not parse ' . basename($file));

        $methodCode = (string)$xml->group['id'];
        $allowed = self::SHARED_PATHS[basename($file)] ?? [];

        $foreign = [];
        foreach ($xml->xpath('//field[config_path]') as $field) {
            $path = (string)$field->config_path;
            if (strpos($path, 'payment/' . $methodCode . '/') !== 0 && !in_array($path, $allowed, true)) {
                $foreign[] = $field['id'] . ' -> ' . $path;
            }
        }

        $this->assertSame([], $foreign, basename($file) . ' has fields that store under another path');
    }

    public function testMethodSpecificStatusesAreStoredPerMethod(): void
    {
        foreach (['afterpay', 'afterpay2', 'afterpay20', 'billink', 'payperemail'] as $method) {
            $xml = simplexml_load_file(self::paymentMethodDir() . '/' . $method . '.xml');
            foreach (['order_status_success', 'order_status_failed'] as $fieldId) {
                $this->assertSame(
                    ['payment/buckaroo_magento2_' . $method . '/' . $fieldId],
                    array_map('strval', $xml->xpath("//field[@id='" . $fieldId . "']/config_path")),
                    $method . '.xml ' . $fieldId
                );
            }
        }
    }

    public function testBankTransferEmailsAreStoredSeparately(): void
    {
        $xml = simplexml_load_file(self::paymentMethodDir() . '/transfer.xml');

        $this->assertSame(
            ['payment/buckaroo_magento2_transfer/payment_instruction_email'],
            array_map('strval', $xml->xpath("//field[label='Send Payment Instruction Email']/config_path"))
        );
        $this->assertSame(
            ['payment/buckaroo_magento2_transfer/order_email'],
            array_map('strval', $xml->xpath("//field[label='Send Order Confirmation Email']/config_path"))
        );
    }

    public static function paymentMethodFileProvider(): array
    {
        $cases = [];
        foreach (glob(self::paymentMethodDir() . '/*.xml') as $file) {
            $cases[basename($file)] = [$file];
        }
        return $cases;
    }

    private static function paymentMethodDir(): string
    {
        return dirname(__DIR__, 3) . '/etc/adminhtml/system/payment_methods';
    }
}
