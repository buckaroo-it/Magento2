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

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;
use TIG\Buckaroo\Test\BaseTest;
use TIG\Buckaroo\Model\ConfigProvider\Method\Creditcard;

class CreditcardTest extends BaseTest
{
    protected $instanceClass = Creditcard::class;

    public function testGetConfig()
    {
        $issuers = 'amex,visa';
        $allowedCurrencies = 'USD,EUR';

        $scopeConfigMock = $this->getFakeMock(ScopeConfigInterface::class)
            ->setMethods(['getValue'])
            ->getMockForAbstractClass();
        $scopeConfigMock->method('getValue')
            ->withConsecutive(
                [Creditcard::XPATH_CREDITCARD_ALLOWED_CREDITCARDS, ScopeInterface::SCOPE_STORE],
                [Creditcard::XPATH_ALLOWED_CURRENCIES, ScopeInterface::SCOPE_STORE, null]
            )->willReturnOnConsecutiveCalls($issuers, $allowedCurrencies);

        $instance = $this->getInstance(['scopeConfig' => $scopeConfigMock]);
        $result = $instance->getConfig();

        $this->assertInternalType('array', $result);
        $this->assertArrayHasKey('payment', $result);
        $this->assertArrayHasKey('buckaroo', $result['payment']);
        $this->assertArrayHasKey('creditcard', $result['payment']['buckaroo']);
        $this->assertArrayHasKey('cards', $result['payment']['buckaroo']['creditcard']);
        $this->assertInternalType('array', $result['payment']['buckaroo']['creditcard']['cards']);
    }

    /**
     * Test if the getActive magic method returns the correct value.
     */
    public function testGetActive()
    {
        $scopeConfigMock = $this->getFakeMock(ScopeConfigInterface::class)
            ->setMethods(['getValue'])
            ->getMockForAbstractClass();
        $scopeConfigMock->method('getValue')
            ->with(Creditcard::XPATH_CREDITCARD_ACTIVE, ScopeInterface::SCOPE_STORE, null)
            ->willReturn('1');

        $instance = $this->getInstance(['scopeConfig' => $scopeConfigMock]);
        $result = $instance->getActive();

        $this->assertEquals(1, $result);
    }
}
