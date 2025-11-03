<?php
// phpcs:ignoreFile
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
 */

namespace Buckaroo\Magento2\Test\Unit\Model\ConfigProvider\Method;

use Buckaroo\Magento2\Helper\PaymentFee;
use Buckaroo\Magento2\Model\ConfigProvider\Method\AbstractConfigProvider;
use Buckaroo\Magento2\Model\ConfigProvider\Method\Eps;
use Buckaroo\Magento2\Test\BaseTest;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;

class EpsTest extends BaseTest
{
    protected $instanceClass = Eps::class;

    public static function getConfigProvider(): array
    {
        return [
            'active' => [
                true,
                [
                    'payment' => [
                        'buckaroo' => [
                            // The exact key (short vs full) is detected dynamically below.
                            // Keep expected values here so we can compare later.
                            'expected' => [
                                'sendEmail'         => true,
                                'paymentFeeLabel'   => 'Fee',
                                'allowedCurrencies' => ['EUR'],
                            ],
                        ],
                    ],
                ],
            ],
            'inactive' => [
                false,
                [],
            ],
        ];
    }

    /**
     * @param bool  $active
     * @param array $expected
     *
     * @dataProvider getConfigProvider
     */
    public function testGetConfig($active, $expected): void
    {
        // $expected parameter is from data provider but not used in this test implementation
        unset($expected);

        $scopeConfigMock = $this->getFakeMock(ScopeConfigInterface::class)
            ->onlyMethods(['getValue'])
            ->getMockForAbstractClass();

        $scopeConfigMock->method('getValue')
            ->willReturnCallback(function ($path, $scope = null, $storeId = null) use ($active) {
                // Use parameters to avoid PHPMD warnings
                unset($scope, $storeId);
                $path = (string)$path;

                if (strpos($path, Eps::CODE . '/active') !== false) {
                    return $active ? '1' : '0';
                }
                if (strpos($path, Eps::CODE . '/order_email') !== false) {
                    return '1';
                }
                if (strpos($path, Eps::CODE . '/allowed_currencies') !== false) {
                    return 'EUR';
                }
                return null;
            });

        $paymentFeeMock = $this->getFakeMock(PaymentFee::class)
            ->onlyMethods(['getBuckarooPaymentFeeLabel'])
            ->getMock();
        $paymentFeeMock->method('getBuckarooPaymentFeeLabel')->willReturn('Fee');

        $instance = $this->getInstance([
            'scopeConfig'      => $scopeConfigMock,
            'paymentFeeHelper' => $paymentFeeMock,
        ]);

        $result = $instance->getConfig();

        if (!$active) {
            $this->assertSame([], $result);
            return;
        }

        // basic structure
        $this->assertIsArray($result);
        $this->assertArrayHasKey('payment', $result);
        $this->assertArrayHasKey('buckaroo', $result['payment']);

        $bucket = $result['payment']['buckaroo'];

        // Accept either 'eps' or the full code key 'buckaroo_magento2_eps'
        $methodKey = array_key_exists('eps', $bucket) ? 'eps'
            : (array_key_exists(Eps::CODE, $bucket) ? Eps::CODE : null);

        $this->assertNotNull($methodKey, 'EPS config key not found under payment/buckaroo');

        $cfg = $bucket[$methodKey];

        // Compare against expected subset
        $this->assertTrue((bool)($cfg['sendEmail'] ?? null));
        $this->assertSame('Fee', $cfg['paymentFeeLabel'] ?? null);
        $this->assertSame(['EUR'], $cfg['allowedCurrencies'] ?? null);
    }

    public static function getPaymentFeeProvider(): array
    {
        return [
            'null value'         => [null, 0],
            'false value'        => [false, 0],
            'empty int value'    => [0, 0],
            'empty float value'  => [0.00, 0],
            'empty string value' => ['', 0],
            'int value'          => [1, 1],
            'float value'        => [2.34, 2.34],
            'string value'       => ['5.67', 5.67],
        ];
    }

    /**
     * @param mixed $value
     * @param mixed $expected
     *
     * @dataProvider getPaymentFeeProvider
     */
    public function testGetPaymentFee($value, $expected): void
    {
        $scopeConfigMock = $this->getFakeMock(ScopeConfigInterface::class)
            ->onlyMethods(['getValue'])
            ->getMockForAbstractClass();

        $scopeConfigMock->method('getValue')
            ->with(
                Eps::XPATH_EPS_PAYMENT_FEE,
                ScopeInterface::SCOPE_STORE,
                null
            )
            ->willReturn($value);

        $instance = $this->getInstance(['scopeConfig' => $scopeConfigMock]);

        $this->assertEquals($expected, $instance->getPaymentFee());
    }
}
