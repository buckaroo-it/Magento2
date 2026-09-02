<?php
// phpcs:ignoreFile
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
namespace Buckaroo\Magento2\Test\Unit\Model\ConfigProvider\Method;


use PHPUnit\Framework\Attributes\DataProvider;
use Buckaroo\Magento2\Model\ConfigProvider\Method\AbstractConfigProvider;
use Magento\Store\Model\ScopeInterface;
use Buckaroo\Magento2\Helper\PaymentFee;
use Buckaroo\Magento2\Test\BaseTest;
use Buckaroo\Magento2\Model\ConfigProvider\Method\Klarnakp;
use \Magento\Framework\App\Config\ScopeConfigInterface;

class KlarnaTest extends BaseTest
{
    protected $instanceClass = Klarnakp::class;

    public static function getConfigProvider()
    {
        return [
            'active' => [
                true,
                [
                    'payment' => [
                        'buckaroo' => [
                            'klarnakp' => [
                                'sendEmail' => true,
                                'paymentFeeLabel' => 'Fee',
                                'allowedCurrencies' => ['EUR'],
                                'businessMethod' => null,
                                'paymentMethod' => null,
                                'paymentFee' => 0
                            ],
                            'response' => []
                        ]
                    ]
                ]
            ],
            'inactive' => [
                false,
                []
            ]
        ];
    }

    /**
     * @param $active
     * @param $expected
     *
     */
    #[DataProvider('getConfigProvider')]
    public function testGetConfig($active, $expected)
    {
        $scopeConfigMock = $this->getFakeMock(ScopeConfigInterface::class)
            ->getMock();

        if ($active) {
            $scopeConfigMock->method('getValue')
                ->willReturnOnConsecutiveCalls(true, 'Fee', 'EUR', null, null, 0, true, 1, 'Test message', 0);
        } else {
            $scopeConfigMock->method('getValue')
                ->willReturnOnConsecutiveCalls(false, 'Fee', 'EUR', null, null, 0, true, 1, 'Test message', 0);
        }

        $paymentFeeMock = $this->getFakeMock(PaymentFee::class)->onlyMethods(['getBuckarooPaymentFeeLabel'])->getMock();
        if ($active) {
            $paymentFeeMock->expects($this->once())->method('getBuckarooPaymentFeeLabel');
        } else {
            $paymentFeeMock->expects($this->never())->method('getBuckarooPaymentFeeLabel');
        }

        $instance = $this->getInstance(['scopeConfig' => $scopeConfigMock, 'paymentFeeHelper' => $paymentFeeMock]);
        $result = $instance->getConfig();

        // Add assertion to verify the method execution
        $this->assertIsArray($result, 'getConfig should return an array');

        if ($active) {
            $this->assertArrayHasKey('payment', $result, 'Config should contain payment key when active');
        } else {
            $this->assertEquals($expected, $result, 'Config should match expected result when inactive');
        }
    }

    public static function getPaymentFeeProvider()
    {
        return [
            'null value' => [
                null,
                false
            ],
            'false value' => [
                false,
                false
            ],
            'empty int value' => [
                0,
                false
            ],
            'empty float value' => [
                0.00,
                false
            ],
            'empty string value' => [
                '',
                false
            ],
            'int value' => [
                '1',
                1
            ],
            'float value' => [
                2.34,
                2.34
            ],
            'string value' => [
                '5.67',
                5.67
            ],
        ];
    }

    /**
     * @param $value
     * @param $expected
     *
     */
    #[DataProvider('getPaymentFeeProvider')]
    public function testGetPaymentFee($value, $expected)
    {
        $scopeConfigMock = $this->getFakeMock(ScopeConfigInterface::class)
            ->getMock();
        $scopeConfigMock->method('getValue')
            ->with(
                $this->getPaymentMethodConfigPath(Klarnakp::CODE, AbstractConfigProvider::PAYMENT_FEE),
                ScopeInterface::SCOPE_STORE
            )
            ->willReturn($value);

        $instance = $this->getInstance(['scopeConfig' => $scopeConfigMock]);
        $result = $instance->getPaymentFee();

        $this->assertEquals($expected, $result);
    }
}
