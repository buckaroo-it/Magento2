<?php

/**
 * NOTICE OF LICENSE
 *
 * This source file is subject to the MIT License
 * It is available through the world-wide-web at this URL:
 * https://tldrlegal.com/license/mit-license
 * If you are unable to obtain it through the world-wide-web, please send an email
 * to support@buckaroo.nl so we can send you a copy immediately.
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

namespace Buckaroo\Magento2\Test\Unit\Model\ConfigProvider\Method;



use Buckaroo\Magento2\Model\ConfigProvider\Method\AbstractConfigProvider;
use Buckaroo\Magento2\Model\ConfigProvider\Method\Applepay;
use Magento\Store\Model\ScopeInterface;
use Buckaroo\Magento2\Helper\PaymentFee;
use Buckaroo\Magento2\Test\BaseTest;
use Buckaroo\Magento2\Model\ConfigProvider\Method\Belfius;
use Magento\Framework\App\Config\ScopeConfigInterface;

class BelfiusTest extends BaseTest
{
    protected $instanceClass = Belfius::class;

    public static function getConfigProvider()
    {
        return [
            'active' => [
                true,
                [
                    'payment' => [
                        'buckaroo' => [
                            'belfius' => [
                                'paymentFeeLabel' => 'Belfius Fee',
                                'allowedCurrencies' => ['EUR'],
                            ]
                        ]
                    ]
                ]
            ]
        ];
    }

    /**
     * @param $active
     * @param $expected
     *
     * @dataProvider getConfigProvider
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function testGetConfig($active, $expected)
    {
        $scopeConfigMock = $this->getFakeMock(ScopeConfigInterface::class)
            ->onlyMethods(['getValue'])
            ->getMockForAbstractClass();

        $valueMap = [
            [
                $this->getPaymentMethodConfigPath(Belfius::CODE, AbstractConfigProvider::ACTIVE),
                ScopeInterface::SCOPE_STORE,
                null,
                1
            ],
        ];
        $scopeConfigMock->method('getValue')->willReturnMap(array_merge($valueMap, [
            [Belfius::CODE, AbstractConfigProvider::PAYMENT_FEE, 'EUR'],
            [Belfius::CODE, AbstractConfigProvider::PAYMENT_FEE_LABEL, 'Belfius Fee'],
        ]));

        $paymentFeeMock = $this->getFakeMock(PaymentFee::class)->onlyMethods(['getBuckarooPaymentFeeLabel'])->getMock();
        $paymentFeeMock->expects($this->once())->method('getBuckarooPaymentFeeLabel');

        $instance = $this->getInstance(['scopeConfig' => $scopeConfigMock, 'paymentFeeHelper' => $paymentFeeMock]);

        $result = $instance->getConfig();

        // Add assertion to verify the method execution
        $this->assertIsArray($result, 'getConfig should return an array');

        if ($active) {
            $this->assertArrayHasKey('payment', $result, 'Config should contain payment key when active');
            $this->assertArrayHasKey('buckaroo', $result['payment']);
            $this->assertArrayHasKey('buckaroo_magento2_belfius', $result['payment']['buckaroo']);
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
     * @covers ::getPaymentFee
     *
     * @dataProvider getPaymentFeeProvider
     */
    public function testGetPaymentFee($value, $expected)
    {
        $scopeConfigMock = $this->getFakeMock(ScopeConfigInterface::class)
            ->onlyMethods(['getValue'])
            ->getMockForAbstractClass();

        $scopeConfigMock->method('getValue')
            ->with(
                $this->getPaymentMethodConfigPath(Belfius::CODE, AbstractConfigProvider::PAYMENT_FEE),
                ScopeInterface::SCOPE_STORE
            )
            ->willReturn($value);

        $instance = $this->getInstance(['scopeConfig' => $scopeConfigMock]);
        $result = $instance->getPaymentFee();

        $this->assertEquals($expected, $result);
    }
}
