<?php
/**
 * NOTICE OF LICENSE
 * (license header omitted for brevity)
 */
namespace Buckaroo\Magento2\Test\Unit\Model\Config\Source\PaymentMethods;

use Buckaroo\Magento2\Model\Config\Source\PaymentMethods\AfterExpiry;
use Buckaroo\Magento2\Test\BaseTest;

class AfterExpiryTest extends BaseTest
{
    protected $instanceClass = AfterExpiry::class;

    /**
     * @return array
     */
    public static function toOptionArrayProvider()
    {
        return [
            [['value' => 'amex',         'label' => 'American Express']],
            [['value' => 'eps',          'label' => 'EPS']],
            [['value' => 'giftcard',     'label' => 'Giftcards']],
            [['value' => 'ideal',        'label' => 'iDEAL']],
            [['value' => 'mastercard',   'label' => 'Mastercard']],
            [['value' => 'paypal',       'label' => 'PayPal']],
            [['value' => 'transfer',     'label' => 'Bank Transfer']],
            [['value' => 'visa',         'label' => 'Visa']],
            [['value' => 'maestro',      'label' => 'Maestro']],
            [['value' => 'visaelectron', 'label' => 'Visa Electron']],
            [['value' => 'vpay',         'label' => 'V PAY']],
        ];
    }

    /**
     * @param array $paymentOption
     *
     * @dataProvider toOptionArrayProvider
     */
    public function testToOptionArray($paymentOption)
    {
        $instance = $this->getInstance();
        $result   = $instance->toOptionArray();

        // Normalize result into [value => label] with scalar strings
        $map = [];
        foreach ((array)$result as $row) {
            if (!is_array($row)) {
                continue;
            }
            $value = array_key_exists('value', $row) ? (string)$row['value'] : null;
            $label = array_key_exists('label', $row) ? (string)$row['label'] : null;
            if ($value !== null) {
                $map[$value] = $label;
            }
        }

        $expectedValue = (string)$paymentOption['value'];
        $expectedLabel = (string)$paymentOption['label'];

        $this->assertArrayHasKey(
            $expectedValue,
            $map,
            "Payment option '{$expectedValue}' not found. Got: " . json_encode(array_keys($map))
        );
        $this->assertSame(
            $expectedLabel,
            $map[$expectedValue],
            "Label mismatch for '{$expectedValue}'. Got '{$map[$expectedValue]}'"
        );
    }
}
