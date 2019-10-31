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
use TIG\Buckaroo\Model\ConfigProvider\Method\Emandate;
use \Magento\Framework\App\Config\ScopeConfigInterface;

class EmandateTest extends BaseTest
{
    protected $instanceClass = Emandate::class;

    public function testIsInactive()
    {
        $scopeConfigMock = $this->getMockBuilder(ScopeConfigInterface::class)->getMock();
        $scopeConfigMock->expects($this->once())
            ->method('getValue')
            ->with(Emandate::XPATH_EMANDATE_ACTIVE)
            ->willReturn(0);

        $instance = $this->getInstance(['scopeConfig' => $scopeConfigMock]);
        $result = $instance->getConfig();

        $this->assertEquals([], $result);
    }

    public function testGetConfig()
    {
        $scopeConfigMock = $this->getMockBuilder(ScopeConfigInterface::class)->getMock();
        $scopeConfigMock->expects($this->atLeastOnce())
            ->method('getValue')
            ->withConsecutive($this->onConsecutiveCalls([[Emandate::XPATH_EMANDATE_ACTIVE]]))
            ->willReturn(1);

        $instance = $this->getInstance(['scopeConfig' => $scopeConfigMock]);
        $result = $instance->getConfig();

        $this->assertInternalType('array', $result);

        $resultPaymentBuckaroo = $result['payment']['buckaroo'];

        $this->assertCount(1, $resultPaymentBuckaroo);
        $this->assertArrayHasKey('emandate', $resultPaymentBuckaroo);
        $this->assertCount(3, $resultPaymentBuckaroo['emandate']);
        $this->assertArrayHasKey('banks', $resultPaymentBuckaroo['emandate']);
        $this->assertArrayHasKey('paymentFeeLabel', $resultPaymentBuckaroo['emandate']);
        $this->assertArrayHasKey('allowedCurrencies', $resultPaymentBuckaroo['emandate']);
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
            ->with(Emandate::XPATH_EMANDATE_PAYMENT_FEE, ScopeInterface::SCOPE_STORE)
            ->willReturn($fee);

        $instance = $this->getInstance(['scopeConfig' => $scopeConfigMock]);
        $result = $instance->getPaymentFee();

        $this->assertEquals($expected, $result);
    }

    public function testIssuers()
    {
        $instance = $this->getInstance();
        $issuers = $instance->getIssuers();

        foreach ($issuers as $issuer) {
            $this->assertTrue(array_key_exists('name', $issuer));
            $this->assertTrue(array_key_exists('code', $issuer));
        }
    }
}
