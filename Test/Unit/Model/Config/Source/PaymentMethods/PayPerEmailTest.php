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

namespace Buckaroo\Magento2\Test\Unit\Model\Config\Source\PayPerEmail;


use PHPUnit\Framework\Attributes\DataProvider;
use Buckaroo\Magento2\Model\Config\Source\PaymentMethods\PayPerEmail;
use Buckaroo\Magento2\Test\BaseTest;

class PayPerEmailTest extends BaseTest
{
    protected $instanceClass = PayPerEmail::class;

    /**
     * @return array
     */
    public static function toOptionArrayProvider()
    {
        return [
            [
                ['value' => 'amex', 'label' => 'American Express', 'code' => 'buckaroo_magento2_creditcards']
            ],
            [
                ['value' => 'eps', 'label' => 'EPS', 'code' => 'buckaroo_magento2_eps']
            ],
            [
                ['value' => 'sepadirectdebit', 'label' => 'SEPA Direct Debit', 'code' => 'buckaroo_magento2_sepadirectdebit']
            ],
            [
                ['value' => 'giftcard', 'label' => 'Giftcards', 'code' => 'buckaroo_magento2_giftcards']
            ],
            [
                ['value' => 'ideal', 'label' => 'iDEAL', 'code' => 'buckaroo_magento2_ideal']
            ],
            [
                ['value' => 'mastercard', 'label' => 'Mastercard', 'code' => 'buckaroo_magento2_creditcards']
            ],
            [
                ['value' => 'paypal', 'label' => 'PayPal', 'code' => 'buckaroo_magento2_paypal']
            ],
            [
                ['value' => 'transfer', 'label' => 'Bank Transfer', 'code' => 'buckaroo_magento2_transfer']
            ],
            [
                ['value' => 'visa', 'label' => 'Visa', 'code' => 'buckaroo_magento2_creditcards']
            ],
            [
                ['value' => 'maestro', 'label' => 'Maestro', 'code' => 'buckaroo_magento2_creditcards']
            ],
            [
                ['value' => 'visaelectron', 'label' => 'Visa Electron', 'code' => 'buckaroo_magento2_creditcards']
            ],
            [
                ['value' => 'vpay', 'label' => 'V PAY', 'code' => 'buckaroo_magento2_creditcards']
            ]
        ];
    }

    /**
     * @param $paymentOption
     *
     */
    #[DataProvider('toOptionArrayProvider')]
    public function testToOptionArray($paymentOption)
    {
        $instance = $this->getInstance();
        $result = $instance->toOptionArray();

        $this->assertIsArray($result);
        $this->assertNotEmpty($result);

        // Check if the specific payment option is in the results
        $found = false;
        foreach ($result as $option) {
            if ($option['value'] === $paymentOption['value'] &&
                $option['code'] === $paymentOption['code']) {
                $found = true;
                // Check that the label contains the expected text (accounting for translation)
                $this->assertStringContainsString($paymentOption['label'], (string)$option['label']);
                break;
            }
        }

        $this->assertTrue($found, "Payment option {$paymentOption['value']} not found in results");
    }
}
