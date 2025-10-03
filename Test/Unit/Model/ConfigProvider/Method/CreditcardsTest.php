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
use Buckaroo\Magento2\Model\ConfigProvider\Method\Creditcard;
use Buckaroo\Magento2\Model\ConfigProvider\Method\Creditcards;
use Buckaroo\Magento2\Model\ConfigProvider\AllowedCurrencies;
use Buckaroo\Magento2\Service\LogoService;
use Magento\Framework\View\Asset\Repository;
use Buckaroo\Magento2\Test\BaseTest;
use \Magento\Framework\App\Config\ScopeConfigInterface;

class CreditcardsTest extends BaseTest
{
    protected $instanceClass = Creditcards::class;

    public static function getConfigProvider()
    {
        return [
            'active' => [
                [
                    'paymentFeeLabel' => 'Fee',
                    'creditcards' => [],
                    'defaultCardImage' => 'test/image/url',
                    'allowedCurrencies' => ['EUR']
                ]
            ]
        ];
    }

    /**
     * @param $expected
     *
     * @dataProvider getConfigProvider
     */
    public function testGetConfig($expected)
    {
        $scopeConfigMock = $this->getFakeMock(ScopeConfigInterface::class)
            ->onlyMethods(['getValue'])
            ->getMockForAbstractClass();
        $scopeConfigMock->method('getValue')
            ->willReturnMap([
                [
                    $this->getPaymentMethodConfigPath(Creditcards::CODE, AbstractConfigProvider::ACTIVE),
                    ScopeInterface::SCOPE_STORE,
                    null,
                    1
                ],
                [
                    $this->getPaymentMethodConfigPath(Creditcards::CODE, AbstractConfigProvider::ALLOWED_CURRENCIES),
                    ScopeInterface::SCOPE_STORE,
                    null,
                    'EUR'
                ]
            ]);

        $paymentFeeMock = $this->getFakeMock(PaymentFee::class)->onlyMethods(['getBuckarooPaymentFeeLabel'])->getMock();
        $paymentFeeMock->method('getBuckarooPaymentFeeLabel')->willReturn('Fee');

        $assetRepoMock = $this->getFakeMock(Repository::class)->onlyMethods(['getUrl'])->getMock();
        $assetRepoMock->method('getUrl')->willReturn('test/image/url');

        $allowedCurrenciesMock = $this->getFakeMock(AllowedCurrencies::class)->getMock();
        $logoServiceMock = $this->getFakeMock(LogoService::class)->getMock();

        // Mock store and storeManager to prevent null reference errors
        $storeMock = $this->getMockBuilder(\Magento\Store\Model\Store::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getId'])
            ->getMock();
        $storeMock->method('getId')->willReturn(1);

        $storeManagerMock = $this->getFakeMock(\Magento\Store\Model\StoreManagerInterface::class)
            ->getMock();
        $storeManagerMock->method('getStore')->willReturn($storeMock);

        $instance = $this->getInstance([
            'assetRepo' => $assetRepoMock,
            'scopeConfig' => $scopeConfigMock,
            'allowedCurrencies' => $allowedCurrenciesMock,
            'paymentFeeHelper' => $paymentFeeMock,
            'logoService' => $logoServiceMock,
            'storeManager' => $storeManagerMock
        ]);

        $result = $instance->getConfig();

        $this->assertArrayHasKey('payment', $result);
        $this->assertArrayHasKey('buckaroo', $result['payment']);
        $this->assertArrayHasKey('buckaroo_magento2_creditcards', $result['payment']['buckaroo']);
        $config = $result['payment']['buckaroo']['buckaroo_magento2_creditcards'];
        
        $this->assertIsArray($config);
        $this->assertArrayHasKey('paymentFeeLabel', $config);
        $this->assertSame($expected['paymentFeeLabel'], $config['paymentFeeLabel']);
        $this->assertArrayHasKey('defaultCardImage', $config);
        $this->assertSame($expected['defaultCardImage'], $config['defaultCardImage']);
        $this->assertArrayHasKey('allowedCurrencies', $config);
        $this->assertTrue(in_array('EUR', $config['allowedCurrencies']));
        $this->assertArrayHasKey('creditcards', $config);
        $this->assertIsArray($config['creditcards']);
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
                Creditcards::XPATH_CREDITCARDS_PAYMENT_FEE,
                ScopeInterface::SCOPE_STORE
            )
            ->willReturn($value);

        $assetRepoMock = $this->getFakeMock(Repository::class)->getMock();
        $allowedCurrenciesMock = $this->getFakeMock(AllowedCurrencies::class)->getMock();
        $paymentFeeMock = $this->getFakeMock(PaymentFee::class)->getMock();
        $logoServiceMock = $this->getFakeMock(LogoService::class)->getMock();

        $instance = $this->getInstance([
            'assetRepo' => $assetRepoMock,
            'scopeConfig' => $scopeConfigMock,
            'allowedCurrencies' => $allowedCurrenciesMock,
            'paymentFeeHelper' => $paymentFeeMock,
            'logoService' => $logoServiceMock
        ]);

        $result = $instance->getPaymentFee();

        $this->assertEquals($expected, $result);
    }
}
