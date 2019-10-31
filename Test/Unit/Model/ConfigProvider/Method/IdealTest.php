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

use Magento\Store\Model\ScopeInterface;
use TIG\Buckaroo\Test\BaseTest;
use TIG\Buckaroo\Model\ConfigProvider\Method\Ideal;
use \Magento\Framework\App\Config\ScopeConfigInterface;

class IdealTest extends BaseTest
{
    protected $instanceClass = Ideal::class;

    /**
     * Test what happens when the payment method is disabled.
     */
    public function testInactive()
    {
        $scopeConfigMock = $this->getFakeMock(ScopeConfigInterface::class)
            ->setMethods(['getValue'])
            ->getMockForAbstractClass();
        $scopeConfigMock->expects($this->once())
            ->method('getValue')
            ->with(Ideal::XPATH_IDEAL_ACTIVE, ScopeInterface::SCOPE_STORE)
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
            ->setMethods(['getValue'])
            ->getMockForAbstractClass();
        $scopeConfigMock->expects($this->exactly(2))
            ->method('getValue')
            ->withConsecutive(
                [Ideal::XPATH_IDEAL_ACTIVE, ScopeInterface::SCOPE_STORE],
                [Ideal::XPATH_ALLOWED_CURRENCIES, ScopeInterface::SCOPE_STORE, null]
            )
            ->willReturnOnConsecutiveCalls(true, 'EUR,USD');

        $instance = $this->getInstance(['scopeConfig' => $scopeConfigMock]);
        $result = $instance->getConfig();

        $this->assertArrayHasKey('payment', $result);
        $this->assertArrayHasKey('buckaroo', $result['payment']);
        $this->assertArrayHasKey('ideal', $result['payment']['buckaroo']);
        $this->assertArrayHasKey('banks', $result['payment']['buckaroo']['ideal']);
    }

    /**
     * Check if the returned issuers list contains the necessary attributes.
     */
    public function testIssuers()
    {
        $instance = $this->getInstance();
        $issuers = $instance->getIssuers();

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
            ->setMethods(['getValue'])
            ->getMockForAbstractClass();
        $scopeConfigMock->expects($this->once())
            ->method('getValue')
            ->with(Ideal::XPATH_IDEAL_PAYMENT_FEE, ScopeInterface::SCOPE_STORE)
            ->willReturn(null);

        $instance = $this->getInstance(['scopeConfig' => $scopeConfigMock]);
        $result = $instance->getPaymentFee();

        $this->assertFalse($result);
    }

    /**
     * Check if the payment free is return as a float.
     */
    public function testGetPaymentFeeReturnNumber()
    {
        $scopeConfigMock = $this->getFakeMock(ScopeConfigInterface::class)
            ->setMethods(['getValue'])
            ->getMockForAbstractClass();
        $scopeConfigMock->expects($this->once())
            ->method('getValue')
            ->with(Ideal::XPATH_IDEAL_PAYMENT_FEE, ScopeInterface::SCOPE_STORE)
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
            ->setMethods(['getValue'])
            ->getMockForAbstractClass();
        $scopeConfigMock->expects($this->once())
            ->method('getValue')
            ->with(Ideal::XPATH_IDEAL_ACTIVE, ScopeInterface::SCOPE_STORE)
            ->willReturn('1');

        $instance = $this->getInstance(['scopeConfig' => $scopeConfigMock]);
        $result = $instance->getActive();

        $this->assertEquals(1, $result);
    }
}
