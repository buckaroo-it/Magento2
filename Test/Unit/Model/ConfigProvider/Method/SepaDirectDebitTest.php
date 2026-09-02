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
use Buckaroo\Magento2\Model\ConfigProvider\Method\SepaDirectDebit;
use \Magento\Framework\App\Config\ScopeConfigInterface;

class SepaDirectDebitTest extends BaseTest
{
    protected $instanceClass = SepaDirectDebit::class;

    public static function getConfigProvider()
    {
        return [
            'active' => [
                [
                    'payment' => [
                        'buckaroo' => [
                            'buckaroo_magento2_sepadirectdebit' => [
                                'paymentFeeLabel' => 'Fee',
                                'allowedCurrencies' => ['EUR'],
                                'title' => null,
                                'subtext' => null,
                                'subtext_style' => null,
                                'subtext_color' => null,
                                'isTestMode' => true,
                                'logo' => '',
                                'display_subtext' => false
                            ]
                        ]
                    ]
                ]
            ]
        ];
    }

    /**
     * @param $expected
     *
     */
    #[DataProvider('getConfigProvider')]
    public function testGetConfig($expected)
    {
        $scopeConfigMock = $this->getFakeMock(ScopeConfigInterface::class)
            ->getMock();
        $scopeConfigMock->method('getValue')
            ->willReturnCallback(function($path, $scope = null, $scopeId = null) {
                // Use parameters to avoid PHPMD warnings
                unset($scope, $scopeId);

                if (strpos($path, 'active') !== false) {
                    return 1; // Make the payment method active
                } elseif (strpos($path, 'allowed_currencies') !== false) {
                    return 'EUR';
                }
                return null; // Default for other config paths
            });

        $paymentFeeMock = $this->getFakeMock(PaymentFee::class)->onlyMethods(['getBuckarooPaymentFeeLabel'])->getMock();
        $paymentFeeMock->method('getBuckarooPaymentFeeLabel')->willReturn('Fee');

        $instance = $this->getInstance(['scopeConfig' => $scopeConfigMock, 'paymentFeeHelper' => $paymentFeeMock]);
        $result = $instance->getConfig();

        $this->assertEquals($expected, $result);
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
                $this->getPaymentMethodConfigPath(SepaDirectDebit::CODE, AbstractConfigProvider::PAYMENT_FEE),
                ScopeInterface::SCOPE_STORE
            )
            ->willReturn($value);

        $instance = $this->getInstance(['scopeConfig' => $scopeConfigMock]);
        $result = $instance->getPaymentFee();

        $this->assertEquals($expected, $result);
    }
}
