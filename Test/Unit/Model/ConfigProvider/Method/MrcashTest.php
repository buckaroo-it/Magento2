<?php
// phpcs:ignoreFile
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
use Magento\Store\Model\ScopeInterface;
use Buckaroo\Magento2\Helper\PaymentFee;
use Buckaroo\Magento2\Test\BaseTest;
use Buckaroo\Magento2\Model\ConfigProvider\Method\Mrcash;
use \Magento\Framework\App\Config\ScopeConfigInterface;

class MrcashTest extends BaseTest
{
    protected $instanceClass = Mrcash::class;

    public static function getConfigProvider()
    {
        return [
            'active' => [
                true,
                [
                    'payment' => [
                        'buckaroo' => [
                            'buckaroo_magento2_mrcash' => [
                                'paymentFeeLabel' => 'Fee',
                                'allowedCurrencies' => ['EUR'],
                                'useClientSide' => 0,
                                'redirecturl' => '/buckaroo/mrcash/pay?form_key=test_form_key'
                            ]
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
     * @dataProvider getConfigProvider
     */
    public function testGetConfig($active, $expected)
    {
        $scopeConfigMock = $this->getFakeMock(ScopeConfigInterface::class)
            ->onlyMethods(['getValue'])
            ->getMockForAbstractClass();
        $scopeConfigMock->method('getValue')->willReturnMap([
            // Set active state
            [
                $this->getPaymentMethodConfigPath(Mrcash::CODE, AbstractConfigProvider::ACTIVE),
                ScopeInterface::SCOPE_STORE,
                null,
                ($active ? 1 : 0)
            ],
            // Set allowed currencies
            [
                $this->getPaymentMethodConfigPath(Mrcash::CODE, AbstractConfigProvider::ALLOWED_CURRENCIES),
                ScopeInterface::SCOPE_STORE,
                null,
                'EUR'
            ],
            // Set client side setting
            [
                $this->getPaymentMethodConfigPath(Mrcash::CODE, Mrcash::XPATH_MRCASH_USE_CLIENT_SIDE),
                ScopeInterface::SCOPE_STORE,
                null,
                0
            ]
        ]);

        $paymentFeeMock = $this->getFakeMock(PaymentFee::class)->onlyMethods(['getBuckarooPaymentFeeLabel'])->getMock();
        if ($active) {
            $paymentFeeMock->expects($this->once())->method('getBuckarooPaymentFeeLabel');
        } else {
            $paymentFeeMock->expects($this->never())->method('getBuckarooPaymentFeeLabel');
        }

        // Mock FormKey
        $formKeyMock = $this->createMock(\Magento\Framework\Data\Form\FormKey::class);
        $formKeyMock->method('getFormKey')->willReturn('test_form_key');

        $instance = $this->getInstance([
            'scopeConfig' => $scopeConfigMock,
            'paymentFeeHelper' => $paymentFeeMock,
            'formKey' => $formKeyMock
        ]);
        $result = $instance->getConfig();

        if ($active) {
            $this->assertArrayHasKey('payment', $result);
            $this->assertArrayHasKey('buckaroo', $result['payment']);
            $this->assertArrayHasKey('buckaroo_magento2_mrcash', $result['payment']['buckaroo']);
            $config = $result['payment']['buckaroo']['buckaroo_magento2_mrcash'];
            $this->assertArrayHasKey('useClientSide', $config);
            $this->assertArrayHasKey('redirecturl', $config);
            $this->assertStringContainsString('test_form_key', $config['redirecturl']);
        } else {
            $this->assertEquals($expected, $result);
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
     * @dataProvider getPaymentFeeProvider
     */
    public function testGetPaymentFee($value, $expected)
    {
        $scopeConfigMock = $this->getFakeMock(ScopeConfigInterface::class)
            ->onlyMethods(['getValue'])
            ->getMockForAbstractClass();
        $scopeConfigMock->method('getValue')
            ->with(
                $this->getPaymentMethodConfigPath(Mrcash::CODE, AbstractConfigProvider::PAYMENT_FEE),
                ScopeInterface::SCOPE_STORE
            )
            ->willReturn($value);

        $instance = $this->getInstance(['scopeConfig' => $scopeConfigMock]);
        $result = $instance->getPaymentFee();

        $this->assertEquals($expected, $result);
    }
}
