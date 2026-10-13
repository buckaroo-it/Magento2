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


use PHPUnit\Framework\Attributes\DataProvider;
use Buckaroo\Magento2\Model\ConfigProvider\Method\AbstractConfigProvider;
use Magento\Store\Model\ScopeInterface;
use Buckaroo\Magento2\Helper\PaymentFee;
use Buckaroo\Magento2\Test\BaseTest;
use Buckaroo\Magento2\Model\ConfigProvider\Method\Afterpay20;
use \Magento\Framework\App\Config\ScopeConfigInterface;

class Afterpay20Test extends BaseTest
{
    protected $instanceClass = Afterpay20::class;

    public static function getConfigProvider()
    {
        return [
            'active' => [
                true,
                [
                    'payment' => [
                        'buckaroo' => [
                            'afterpay20' => [
                                'sendEmail' => '1',
                                'paymentFeeLabel' => 'Fee',
                                'allowedCurrencies' => ['EUR']
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
        // PHPUnit 10: use a value map instead of withConsecutive()
        $valueMap = [
            [
                $this->getPaymentMethodConfigPath(Afterpay20::CODE, AbstractConfigProvider::ACTIVE),
                ScopeInterface::SCOPE_STORE,
                null,
                $active
            ],
            [
                $this->getPaymentMethodConfigPath(Afterpay20::CODE, AbstractConfigProvider::ORDER_EMAIL),
                ScopeInterface::SCOPE_STORE,
                null,
                '1'
            ],
            [
                $this->getPaymentMethodConfigPath(Afterpay20::CODE, AbstractConfigProvider::ALLOWED_CURRENCIES),
                ScopeInterface::SCOPE_STORE,
                null,
                'EUR'
            ]
        ];

        $scopeConfigMock->method('getValue')
            ->willReturnMap($valueMap);

        $paymentFeeMock = $this->getFakeMock(PaymentFee::class)->onlyMethods(['getBuckarooPaymentFeeLabel'])->getMock();
        $paymentFeeMock->method('getBuckarooPaymentFeeLabel')->willReturn('Fee');

        $instance = $this->getInstance(['scopeConfig' => $scopeConfigMock, 'paymentFeeHelper' => $paymentFeeMock]);
        $result = $instance->getConfig();

        if ($active) {
            $this->assertArrayHasKey('buckaroo_magento2_afterpay20', $result['payment']['buckaroo']);
            $this->assertArrayNotHasKey(
                'showFinancialWarning',
                $result['payment']['buckaroo']['buckaroo_magento2_afterpay20']
            );
        } else {
            $this->assertEquals($expected, $result);
        }
    }

    public function testAdminConfigDoesNotExposeFinancialWarningOrTerms(): void
    {
        $moduleRoot = dirname(__DIR__, 5);

        $systemXml = file_get_contents(
            $moduleRoot . '/etc/adminhtml/system/payment_methods/afterpay20.xml'
        );
        $this->assertIsString($systemXml);
        $this->assertStringNotContainsString('financial_warning', $systemXml);
        $this->assertStringNotContainsString('Consumer Financial Warning', $systemXml);
        $this->assertStringNotContainsString('Terms and Conditions', $systemXml);

        $configXml = simplexml_load_file($moduleRoot . '/etc/config.xml');
        $this->assertNotFalse($configXml);
        $afterpay20 = $configXml->default->payment->buckaroo_magento2_afterpay20;
        $this->assertNotNull($afterpay20);
        $this->assertFalse(isset($afterpay20->financial_warning));

        $legacyAfterpay = $configXml->default->payment->buckaroo_magento2_afterpay;
        $this->assertTrue(isset($legacyAfterpay->financial_warning));
    }

    public function testCheckoutAssetsDoNotShowTermsOrFinancialWarning(): void
    {
        $moduleRoot = dirname(__DIR__, 5);

        $template = file_get_contents(
            $moduleRoot . '/view/frontend/web/template/payment/buckaroo_magento2_afterpay20.html'
        );
        $this->assertIsString($template);
        $this->assertStringNotContainsString('termsCondition', $template);
        $this->assertStringNotContainsString('Terms and Conditions', $template);
        $this->assertStringNotContainsString('showFinancialWarning', $template);
        $this->assertStringNotContainsString('getMessageText', $template);

        $renderer = file_get_contents(
            $moduleRoot . '/view/frontend/web/js/view/payment/method-renderer/afterpay20.js'
        );
        $this->assertIsString($renderer);
        $this->assertStringNotContainsString('termsCondition', $renderer);
        $this->assertStringNotContainsString('termsSelected', $renderer);
        $this->assertStringNotContainsString('showFinancialWarning', $renderer);
        $this->assertStringNotContainsString('getTermsUrl', $renderer);
        $this->assertStringNotContainsString('getFrenchTos', $renderer);

        $legacyTemplate = file_get_contents(
            $moduleRoot . '/view/frontend/web/template/payment/buckaroo_magento2_afterpay.html'
        );
        $this->assertIsString($legacyTemplate);
        $this->assertStringContainsString('termsCondition', $legacyTemplate);
        $this->assertStringContainsString('showFinancialWarning', $legacyTemplate);
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
                $this->getPaymentMethodConfigPath(Afterpay20::CODE, AbstractConfigProvider::PAYMENT_FEE),
                ScopeInterface::SCOPE_STORE
            )
            ->willReturn($value);

        $instance = $this->getInstance(['scopeConfig' => $scopeConfigMock]);
        $result = $instance->getPaymentFee();

        $this->assertEquals($expected, $result);
    }
}
