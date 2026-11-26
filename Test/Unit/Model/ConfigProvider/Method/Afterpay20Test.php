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
use Buckaroo\Magento2\Service\LogoService;
use Buckaroo\Magento2\Test\BaseTest;
use Buckaroo\Magento2\Model\ConfigProvider\Method\Afterpay20;
use \Magento\Framework\App\Config\ScopeConfigInterface;

class Afterpay20Test extends BaseTest
{
    protected $instanceClass = Afterpay20::class;

    /**
     * Prefix config fields with this method's config path.
     *
     * @param array $fields
     *
     * @return array
     */
    private static function configValues(array $fields): array
    {
        $values = [];
        foreach ($fields as $field => $value) {
            $values['payment/buckaroo_magento2_afterpay20/' . $field] = $value;
        }

        return $values;
    }

    /**
     * @return array
     */
    public static function getConfigProvider()
    {
        return [
            'active live, B2C only' => [
                self::configValues([
                    'active'             => '2',
                    'title'              => 'Riverty',
                    'subtext'            => 'Pay with Riverty',
                    'display_subtext'    => '1',
                    'subtext_style'      => 'italic',
                    'subtext_color'      => '#336699',
                    'allowed_currencies' => 'EUR,USD',
                    'order_email'        => '1',
                    'customer_type'      => 'b2c',
                    ]),
                ['payment' => ['buckaroo' => ['buckaroo_magento2_afterpay20' => [
                    'paymentFeeLabel'   => 'Fee',
                    'title'             => 'Riverty',
                    'subtext'           => 'Pay with Riverty',
                    'display_subtext'   => true,
                    'subtext_style'     => 'italic',
                    'subtext_color'     => '#336699',
                    'allowedCurrencies' => ['EUR', 'USD'],
                    'isTestMode'        => false,
                    'logo'              => 'afterpay20.svg',
                    'sendEmail'         => true,
                    'is_b2b'            => false,
                ]]]],
            ],
            'active test mode, B2B and B2C' => [
                self::configValues([
                    'active'             => '1',
                    'title'              => 'Riverty',
                    'subtext'            => 'Pay with Riverty',
                    'display_subtext'    => '1',
                    'subtext_style'      => 'italic',
                    'subtext_color'      => '#336699',
                    'allowed_currencies' => 'EUR,USD',
                    'order_email'        => '0',
                    'customer_type'      => 'both',
                    ]),
                ['payment' => ['buckaroo' => ['buckaroo_magento2_afterpay20' => [
                    'paymentFeeLabel'   => 'Fee',
                    'title'             => 'Riverty',
                    'subtext'           => 'Pay with Riverty',
                    'display_subtext'   => true,
                    'subtext_style'     => 'italic',
                    'subtext_color'     => '#336699',
                    'allowedCurrencies' => ['EUR', 'USD'],
                    'isTestMode'        => true,
                    'logo'              => 'afterpay20.svg',
                    'sendEmail'         => false,
                    'is_b2b'            => true,
                ]]]],
            ],
            'inactive' => [
                self::configValues(['active' => '0', 'title' => 'Not shown']),
                [],
            ],
        ];
    }

    /**
     * The checkout config holds every configured value under the method code; inactive methods add nothing.
     *
     * @param array $configValues
     * @param array $expected
     */
    #[DataProvider('getConfigProvider')]
    public function testGetConfig(array $configValues, array $expected)
    {
        $scopeConfigMock = $this->getFakeMock(ScopeConfigInterface::class)
            ->getMock();
        $scopeConfigMock->method('getValue')
            ->willReturnCallback(function ($path, $scope, $scopeCode) use ($configValues) {
                $this->assertSame(ScopeInterface::SCOPE_STORE, $scope);
                $this->assertNull($scopeCode);
                return $configValues[$path] ?? null;
            });

        $paymentFeeMock = $this->getFakeMock(PaymentFee::class)->onlyMethods(['getBuckarooPaymentFeeLabel'])->getMock();
        $paymentFeeMock->method('getBuckarooPaymentFeeLabel')->willReturn('Fee');

        $logoServiceMock = $this->getFakeMock(LogoService::class)->onlyMethods(['getPayment'])->getMock();
        $logoServiceMock->method('getPayment')->with('afterpay20')->willReturn('afterpay20.svg');

        $instance = $this->getInstance([
            'scopeConfig' => $scopeConfigMock,
            'paymentFeeHelper' => $paymentFeeMock,
            'logoService' => $logoServiceMock,
        ]);

        $this->assertSame($expected, $instance->getConfig());
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
}
