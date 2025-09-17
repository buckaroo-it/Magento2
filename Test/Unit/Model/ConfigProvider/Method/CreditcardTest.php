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
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;
use Buckaroo\Magento2\Test\BaseTest;
use Buckaroo\Magento2\Model\ConfigProvider\Method\Creditcard;

class CreditcardTest extends BaseTest
{
    protected $instanceClass = Creditcard::class;

    public function testGetConfig()
    {
        $issuers = 'amex,visa';
        $allowedCurrencies = 'USD,EUR';

        $scopeConfigMock = $this->getFakeMock(ScopeConfigInterface::class)
            ->onlyMethods(['getValue'])
            ->getMockForAbstractClass();
        
        // Mock the getValue calls for different config paths
        $scopeConfigMock->method('getValue')->willReturnMap([
            // Make the creditcard method active
            [
                $this->getPaymentMethodConfigPath(Creditcard::CODE, AbstractConfigProvider::ACTIVE),
                ScopeInterface::SCOPE_STORE,
                null,
                1
            ],
            // Set allowed creditcards
            [
                Creditcard::XPATH_CREDITCARD_ALLOWED_CREDITCARDS,
                ScopeInterface::SCOPE_STORE,
                null,
                $issuers
            ],
            // Set allowed currencies
            [
                $this->getPaymentMethodConfigPath(Creditcard::CODE, AbstractConfigProvider::ALLOWED_CURRENCIES),
                ScopeInterface::SCOPE_STORE,
                null,
                $allowedCurrencies
            ]
        ]);

        $instance = $this->getInstance(['scopeConfig' => $scopeConfigMock]);
        $result = $instance->getConfig();

        $this->assertIsArray($result);
        $this->assertArrayHasKey('payment', $result);
        $this->assertArrayHasKey('buckaroo', $result['payment']);
        $this->assertArrayHasKey('buckaroo_magento2_creditcard', $result['payment']['buckaroo']);
        $this->assertArrayHasKey('cards', $result['payment']['buckaroo']['buckaroo_magento2_creditcard']);
        $this->assertIsArray($result['payment']['buckaroo']['buckaroo_magento2_creditcard']['cards']);
    }

    /**
     * Test if the getActive magic method returns the correct value.
     */
    public function testGetActive()
    {
        $scopeConfigMock = $this->getFakeMock(ScopeConfigInterface::class)
            ->onlyMethods(['getValue'])
            ->getMockForAbstractClass();
        $scopeConfigMock->method('getValue')
            ->with(
                $this->getPaymentMethodConfigPath(Creditcard::CODE, AbstractConfigProvider::ACTIVE),
                ScopeInterface::SCOPE_STORE,
                null
            )
            ->willReturn('1');

        $instance = $this->getInstance(['scopeConfig' => $scopeConfigMock]);
        $result = $instance->getActive();

        $this->assertEquals(1, $result);
    }
}
