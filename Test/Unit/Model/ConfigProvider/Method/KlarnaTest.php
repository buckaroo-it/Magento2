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
use Buckaroo\Magento2\Model\ConfigProvider\Method\Klarnakp;
use \Magento\Framework\App\Config\ScopeConfigInterface;

class KlarnaTest extends BaseTest
{
    protected $instanceClass = Klarnakp::class;

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
            $values['payment/buckaroo_magento2_klarnakp/' . $field] = $value;
        }

        return $values;
    }

    /**
     * @return array
     */
    public static function getConfigProvider()
    {
        return [
            'active live, financial warning on' => [
                self::configValues([
                    'active'             => '2',
                    'title'              => 'Klarna',
                    'subtext'            => 'Pay with Klarna',
                    'display_subtext'    => '1',
                    'subtext_style'      => 'italic',
                    'subtext_color'      => '#336699',
                    'allowed_currencies' => 'EUR,USD',
                    'financial_warning'  => '1',
                    ]),
                ['payment' => ['buckaroo' => ['buckaroo_magento2_klarnakp' => [
                    'paymentFeeLabel'   => 'Fee',
                    'title'             => 'Klarna',
                    'subtext'           => 'Pay with Klarna',
                    'display_subtext'   => true,
                    'subtext_style'     => 'italic',
                    'subtext_color'     => '#336699',
                    'allowedCurrencies' => ['EUR', 'USD'],
                    'isTestMode'        => false,
                    'logo'              => 'klarnakp.svg',
                    'showFinancialWarning' => true,
                ]]]],
            ],
            'active test mode, financial warning off' => [
                self::configValues([
                    'active'             => '1',
                    'title'              => 'Klarna',
                    'subtext'            => 'Pay with Klarna',
                    'display_subtext'    => '1',
                    'subtext_style'      => 'italic',
                    'subtext_color'      => '#336699',
                    'allowed_currencies' => 'EUR,USD',
                    'financial_warning'  => '0',
                    ]),
                ['payment' => ['buckaroo' => ['buckaroo_magento2_klarnakp' => [
                    'paymentFeeLabel'   => 'Fee',
                    'title'             => 'Klarna',
                    'subtext'           => 'Pay with Klarna',
                    'display_subtext'   => true,
                    'subtext_style'     => 'italic',
                    'subtext_color'     => '#336699',
                    'allowedCurrencies' => ['EUR', 'USD'],
                    'isTestMode'        => true,
                    'logo'              => 'klarnakp.svg',
                    'showFinancialWarning' => false,
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
        $logoServiceMock->method('getPayment')->with('klarnakp')->willReturn('klarnakp.svg');

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
                $this->getPaymentMethodConfigPath(Klarnakp::CODE, AbstractConfigProvider::PAYMENT_FEE),
                ScopeInterface::SCOPE_STORE
            )
            ->willReturn($value);

        $instance = $this->getInstance(['scopeConfig' => $scopeConfigMock]);
        $result = $instance->getPaymentFee();

        $this->assertEquals($expected, $result);
    }
}
