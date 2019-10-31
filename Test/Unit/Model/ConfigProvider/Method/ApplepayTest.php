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
namespace TIG\Buckaroo\Test\Unit\Model\ConfigProvider\Method;

use Magento\Framework\Locale\Resolver;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;
use TIG\Buckaroo\Helper\PaymentFee;
use TIG\Buckaroo\Model\ConfigProvider\Account;
use TIG\Buckaroo\Model\Method\Applepay as ApplepayMethod;
use TIG\Buckaroo\Test\BaseTest;
use TIG\Buckaroo\Model\ConfigProvider\Method\Applepay;
use \Magento\Framework\App\Config\ScopeConfigInterface;

class ApplepayTest extends BaseTest
{
    protected $instanceClass = Applepay::class;

    public function getConfigProvider()
    {
        return [
            'active' => [
                true,
                [
                    'payment' => [
                        'buckaroo' => [
                            'applepay' => [
                                'allowedCurrencies' => ['EUR'],
                                'storeName' => 'TIG Webshop',
                                'currency' => 'EUR',
                                'cultureCode' => 'nl',
                                'guid' => 'GUID12345'
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
            ->setMethods(['getValue'])
            ->getMockForAbstractClass();
        $scopeConfigMock->expects($this->atLeastOnce())
            ->method('getValue')
            ->withConsecutive(
                [Applepay::XPATH_APPLEPAY_ACTIVE, ScopeInterface::SCOPE_STORE],
                [Applepay::XPATH_ALLOWED_CURRENCIES, ScopeInterface::SCOPE_STORE, null]
            )
            ->willReturnOnConsecutiveCalls($active, 'EUR');

        $expectedCount = 0;
        if ($active) {
            $expectedCount = 1;
        }

        $storeManagerMock = $this->getFakeMock(StoreManagerInterface::class)
            ->setMethods(['getStore', 'getName', 'getCurrentCurrency', 'getCode'])
            ->getMockForAbstractClass();
        $storeManagerMock->expects($this->exactly($expectedCount))->method('getStore')->willReturnSelf();
        $storeManagerMock->expects($this->exactly($expectedCount))->method('getName')->willReturn('TIG Webshop');
        $storeManagerMock->expects($this->exactly($expectedCount))->method('getCurrentCurrency')->willReturnSelf();
        $storeManagerMock->expects($this->exactly($expectedCount))->method('getCode')->willReturn('EUR');

        $localeResolverMock = $this->getFakeMock(Resolver::class)->setMethods(['getLocale'])->getMock();
        $localeResolverMock->expects($this->exactly($expectedCount))->method('getLocale')->willReturn('nl_NL');

        $accountConfigMock = $this->getFakeMock(Account::class)->setMethods(['getMerchantGuid'])->getMock();
        $accountConfigMock->expects($this->exactly($expectedCount))->method('getMerchantGuid')->willReturn('GUID12345');

        $instance = $this->getInstance([
            'scopeConfig' => $scopeConfigMock,
            'storeManager' => $storeManagerMock,
            'localeResolver' => $localeResolverMock,
            'configProvicerAccount' => $accountConfigMock
        ]);

        $result = $instance->getConfig();
        $this->assertEquals($expected, $result);
    }
}
