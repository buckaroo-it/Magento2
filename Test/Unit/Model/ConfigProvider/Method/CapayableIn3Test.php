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
use TIG\Buckaroo\Model\ConfigProvider\Method\CapayableIn3;
use TIG\Buckaroo\Test\BaseTest;

class CapayableIn3Test extends BaseTest
{
    protected $instanceClass = CapayableIn3::class;

    public function testIsInactive()
    {
        $scopeConfigMock = $this->getMockBuilder(ScopeConfigInterface::class)->getMock();
        $scopeConfigMock->expects($this->once())
            ->method('getValue')
            ->with(CapayableIn3::XPATH_CAPAYABLEIN3_ACTIVE)
            ->willReturn(0);

        $instance = $this->getInstance(['scopeConfig' => $scopeConfigMock]);
        $result = $instance->getConfig();

        $this->assertEquals([], $result);
    }

    public function testGetConfig()
    {
        $scopeConfigMock = $this->getFakeMock(ScopeConfigInterface::class)
            ->setMethods(['getValue'])
            ->getMockForAbstractClass();
        $scopeConfigMock->expects($this->exactly(2))
            ->method('getValue')
            ->withConsecutive(
                [CapayableIn3::XPATH_CAPAYABLEIN3_ACTIVE, ScopeInterface::SCOPE_STORE],
                [CapayableIn3::XPATH_ALLOWED_CURRENCIES, ScopeInterface::SCOPE_STORE, null]
            )
            ->willReturnOnConsecutiveCalls(true, 'EUR');

        $instance = $this->getInstance(['scopeConfig' => $scopeConfigMock]);
        $result = $instance->getConfig();

        $this->assertArrayHasKey('payment', $result);
        $this->assertArrayHasKey('buckaroo', $result['payment']);
        $this->assertArrayHasKey('capayablein3', $result['payment']['buckaroo']);
        $this->assertArrayHasKey('allowedCurrencies', $result['payment']['buckaroo']['capayablein3']);
    }

    /**
     * @return array
     */
    public function getPaymentFeeProvider()
    {
        return [
            'null value' => [
                null,
                0
            ],
            'empty value' => [
                '',
                0
            ],
            'no fee' => [
                0.00,
                0
            ],
            'with fee' => [
                1.23,
                1.23
            ],
        ];
    }

    /**
     * @param $fee
     * @param $expected
     *
     * @dataProvider getPaymentFeeProvider
     */
    public function testGetPaymentFee($fee, $expected)
    {
        $scopeConfigMock = $this->getMockBuilder(ScopeConfigInterface::class)->getMock();
        $scopeConfigMock->expects($this->once())
            ->method('getValue')
            ->with(CapayableIn3::XPATH_CAPAYABLEIN3_PAYMENT_FEE, ScopeInterface::SCOPE_STORE)
            ->willReturn($fee);

        $instance = $this->getInstance(['scopeConfig' => $scopeConfigMock]);
        $result = $instance->getPaymentFee();

        $this->assertEquals($expected, $result);
    }
}
