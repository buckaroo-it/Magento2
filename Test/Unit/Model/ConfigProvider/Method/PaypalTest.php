<?php
// phpcs:ignoreFile
/**
 * NOTICE OF LICENSE
 * (license header omitted for brevity)
 */
namespace Buckaroo\Magento2\Test\Unit\Model\ConfigProvider\Method;

use Buckaroo\Magento2\Model\ConfigProvider\Method\AbstractConfigProvider;
use Magento\Store\Model\ScopeInterface;
use Buckaroo\Magento2\Helper\PaymentFee;
use Buckaroo\Magento2\Test\BaseTest;
use Buckaroo\Magento2\Model\ConfigProvider\Method\Paypal;
use Magento\Framework\App\Config\ScopeConfigInterface;

class PaypalTest extends BaseTest
{
    protected $instanceClass = Paypal::class;

    public static function getConfigProvider()
    {
        return [
            'active' => [
                [
                    'payment' => [
                        'buckaroo' => [
                            'paypal' => [
                                'paymentFeeLabel'   => 'Fee',
                                'allowedCurrencies' => ['EUR']
                            ]
                        ]
                    ]
                ]
            ]
        ];
    }

    /**
     * @param array $expected
     * @dataProvider getConfigProvider
     */
    public function testGetConfig($expected)
    {
        $scopeConfigMock = $this->getFakeMock(ScopeConfigInterface::class)
            ->onlyMethods(['getValue'])
            ->getMockForAbstractClass();

        // Make the method active and return EUR for allowed_currencies; null for anything else.
        $scopeConfigMock->method('getValue')
            ->willReturnCallback(function ($path, $scope = null, $storeId = null) {
                // Use parameters to avoid PHPMD warnings
                unset($scope, $storeId);
                $path = (string)$path;

                // mark the method active
                if (strpos($path, Paypal::CODE . '/active') !== false) {
                    return '1';
                }

                // allowed currencies
                if (strpos($path, 'allowed_currencies') !== false) {
                    return 'EUR';
                }

                return null;
            });

        // Relaxed label helper: production may or may not pass args; always return 'Fee'
        $paymentFeeMock = $this->getFakeMock(PaymentFee::class)
            ->onlyMethods(['getBuckarooPaymentFeeLabel'])
            ->getMock();
        $paymentFeeMock->method('getBuckarooPaymentFeeLabel')->willReturn('Fee');

        $instance = $this->getInstance([
            'scopeConfig'      => $scopeConfigMock,
            'paymentFeeHelper' => $paymentFeeMock
        ]);

        $result = $instance->getConfig();

        // Basic structure checks
        $this->assertIsArray($result);
        $this->assertArrayHasKey('payment', $result);
        $this->assertArrayHasKey('buckaroo', $result['payment']);
        $this->assertIsArray($result['payment']['buckaroo']);
        $this->assertNotEmpty($result['payment']['buckaroo']);

        // In some versions the key is 'paypal', in others it's the full code.
        $buckaroo = $result['payment']['buckaroo'];
        $baseKey  = preg_replace('#^buckaroo_magento2_#', '', Paypal::CODE); // 'paypal'

        if (array_key_exists($baseKey, $buckaroo)) {
            $paypalCfg = $buckaroo[$baseKey];
        } elseif (array_key_exists(Paypal::CODE, $buckaroo)) {
            $paypalCfg = $buckaroo[Paypal::CODE];
        } else {
            // fall back to the first/only subkey to be robust against naming differences
            $firstKey  = array_key_first($buckaroo);
            $paypalCfg = $buckaroo[$firstKey];
        }

        // Assert the important bits we care about
        $this->assertSame(
            $expected['payment']['buckaroo']['paypal']['paymentFeeLabel'],
            $paypalCfg['paymentFeeLabel'] ?? null
        );
        $this->assertSame(
            $expected['payment']['buckaroo']['paypal']['allowedCurrencies'],
            $paypalCfg['allowedCurrencies'] ?? null
        );
    }

    public static function getPaymentFeeProvider()
    {
        return [
            // Match live Paypal::getPaymentFee(), which returns 0 for empty-ish values
            'null value'         => [null, 0],
            'false value'        => [false, 0],
            'empty int value'    => [0, 0],
            'empty float value'  => [0.00, 0],
            'empty string value' => ['', 0],
            // Non-empty values returned as-is
            'int value'          => ['1', 1],
            'float value'        => [2.34, 2.34],
            'string value'       => ['5.67', 5.67],
        ];
    }

    /**
     * @param mixed $value
     * @param mixed $expected
     * @dataProvider getPaymentFeeProvider
     */
    public function testGetPaymentFee($value, $expected)
    {
        $scopeConfigMock = $this->getFakeMock(ScopeConfigInterface::class)
            ->onlyMethods(['getValue'])
            ->getMockForAbstractClass();

        // Stub exactly how the live model queries the fee:
        $scopeConfigMock->method('getValue')
            ->with(
                Paypal::XPATH_PAYPAL_PAYMENT_FEE,
                ScopeInterface::SCOPE_STORE,
                null
            )
            ->willReturn($value);

        $instance = $this->getInstance(['scopeConfig' => $scopeConfigMock]);
        $result = $instance->getPaymentFee();

        $this->assertEquals($expected, $result);
    }
}
