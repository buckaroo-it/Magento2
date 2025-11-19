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
use Magento\Store\Model\ScopeInterface;
use Buckaroo\Magento2\Test\BaseTest;
use Buckaroo\Magento2\Model\ConfigProvider\Method\Ideal;
use Magento\Framework\App\Config\ScopeConfigInterface;

class IdealTest extends BaseTest
{
    protected $instanceClass = Ideal::class;

    /**
     * Test what happens when the payment method is disabled.
     */
    public function testInactive()
    {
        $scopeConfigMock = $this->getFakeMock(ScopeConfigInterface::class)
            ->onlyMethods(['getValue'])
            ->getMockForAbstractClass();
        $scopeConfigMock->method('getValue')
            ->with(
                $this->getPaymentMethodConfigPath(Ideal::CODE, AbstractConfigProvider::ACTIVE),
                ScopeInterface::SCOPE_STORE
            )
            ->willReturn(false);

        $instance = $this->getInstance(['scopeConfig' => $scopeConfigMock]);
        $result = $instance->getConfig();

        $this->assertEquals([], $result);
    }

    /**
     * Check if the getConfig function is called for every record.
     */
    public function testGetConfig()
    {
        $scopeConfigMock = $this->getFakeMock(ScopeConfigInterface::class)
            ->onlyMethods(['getValue'])
            ->getMockForAbstractClass();
        $scopeConfigMock->method('getValue')->willReturnMap([
            // Make the ideal method active
            [
                $this->getPaymentMethodConfigPath(Ideal::CODE, AbstractConfigProvider::ACTIVE),
                ScopeInterface::SCOPE_STORE,
                null,
                1
            ],
            // Set allowed currencies
            [
                $this->getPaymentMethodConfigPath(Ideal::CODE, AbstractConfigProvider::ALLOWED_CURRENCIES),
                ScopeInterface::SCOPE_STORE,
                null,
                'EUR'
            ]
        ]);

        $instance = $this->getInstance(['scopeConfig' => $scopeConfigMock]);
        $result = $instance->getConfig();

        $this->assertArrayHasKey('payment', $result);
        $this->assertArrayHasKey('buckaroo', $result['payment']);
        $this->assertArrayHasKey('buckaroo_magento2_ideal', $result['payment']['buckaroo']);
        $this->assertArrayHasKey('paymentFeeLabel', $result['payment']['buckaroo']['buckaroo_magento2_ideal']);
        $this->assertArrayHasKey('allowedCurrencies', $result['payment']['buckaroo']['buckaroo_magento2_ideal']);
    }

    /**
     * Check if the returned issuers list contains the necessary attributes.
     */
    public function testIssuers()
    {
        $instance = $this->getInstance();
        $issuers = $instance->getIssuers();

        // Basic assertion to prevent risky test warning
        $this->assertIsArray($issuers);

        foreach ($issuers as $issuer) {
            $this->assertTrue(array_key_exists('name', $issuer));
            $this->assertTrue(array_key_exists('code', $issuer));
        }
    }

    /**
     * Check that the payment fee is return as a false boolean when we have a false-ish value.
     */
    public function testGetPaymentFee()
    {
        $scopeConfigMock = $this->getFakeMock(ScopeConfigInterface::class)
            ->onlyMethods(['getValue'])
            ->getMockForAbstractClass();
        $scopeConfigMock->method('getValue')
            ->with(
                $this->getPaymentMethodConfigPath(Ideal::CODE, AbstractConfigProvider::PAYMENT_FEE),
                ScopeInterface::SCOPE_STORE
            )
            ->willReturn(null);

        $instance = $this->getInstance(['scopeConfig' => $scopeConfigMock]);
        $result = $instance->getPaymentFee();

        $this->assertEquals(0, $result);
    }

    /**
     * Check if the payment free is return as a float.
     */
    public function testGetPaymentFeeReturnNumber()
    {
        $scopeConfigMock = $this->getFakeMock(ScopeConfigInterface::class)
            ->onlyMethods(['getValue'])
            ->getMockForAbstractClass();
        $scopeConfigMock->method('getValue')
            ->with(
                $this->getPaymentMethodConfigPath(Ideal::CODE, AbstractConfigProvider::PAYMENT_FEE),
                ScopeInterface::SCOPE_STORE
            )
            ->willReturn(10.00);

        $instance = $this->getInstance(['scopeConfig' => $scopeConfigMock]);
        $result = $instance->getPaymentFee();

        $this->assertEquals(10.00, $result);
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
                $this->getPaymentMethodConfigPath(Ideal::CODE, AbstractConfigProvider::ACTIVE),
                ScopeInterface::SCOPE_STORE
            )
            ->willReturn('1');

        $instance = $this->getInstance(['scopeConfig' => $scopeConfigMock]);
        $result = $instance->getActive();

        $this->assertEquals(1, $result);
    }
}
