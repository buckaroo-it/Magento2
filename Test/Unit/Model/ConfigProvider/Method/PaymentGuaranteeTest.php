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
use TIG\Buckaroo\Model\ConfigProvider\Method\PaymentGuarantee;
use TIG\Buckaroo\Test\BaseTest;

class PaymentGuaranteeTest extends BaseTest
{
    protected $instanceClass = PaymentGuarantee::class;

    /**
     * @return array
     */
    public function getConfigProvider()
    {
        return [
            'inactive method' => [0],
            'test method' => [1],
            'live method' => [2]
        ];
    }

    /**
     * @param $activeMode
     *
     * @dataProvider getConfigProvider
     */
    public function testGetConfig($activeMode)
    {
        $scopeConfigMock = $this->getMockBuilder(ScopeConfigInterface::class)->getMock();
        $scopeConfigMock->expects($this->atLeastOnce())
            ->method('getValue')
            ->withConsecutive($this->onConsecutiveCalls([[PaymentGuarantee::XPATH_PAYMENTGUARANTEE_ACTIVE]]))
            ->willReturn($activeMode);

        $expectedCount = (int)((bool)$activeMode);

        $instance = $this->getInstance(['scopeConfig' => $scopeConfigMock]);
        $result = $instance->getConfig();

        $this->assertInternalType('array', $result);
        $this->assertCount($expectedCount, $result);

        if ($activeMode) {
            $resultPaymentBuckaroo = $result['payment']['buckaroo'];

            $this->assertCount(2, $resultPaymentBuckaroo);
            $this->assertArrayHasKey('paymentguarantee', $resultPaymentBuckaroo);
            $this->assertArrayHasKey('response', $resultPaymentBuckaroo);
        }
    }

    /**
     * @return array
     */
    public function getPaymentMethodToUseProvider()
    {
        return [
            'null value' => [
                null,
                'transfer,ideal'
            ],
            'empty value' => [
                '',
                'transfer,ideal'
            ],
            'transfer method' => [
                '1',
                'transfer'
            ],
            'iDEAL method' => [
                '2',
                'ideal'
            ],
            'both methods' => [
                '2,1',
                'transfer,ideal'
            ],
            'with incorrect methods' => [
                '3,1,5',
                'transfer'
            ],
        ];
    }

    /**
     * @param $value
     * @param $expected
     *
     * @dataProvider getPaymentMethodToUseProvider
     */
    public function testGetPaymentMethodToUse($value, $expected)
    {
        $scopeConfigMock = $this->getMockBuilder(ScopeConfigInterface::class)->getMock();
        $scopeConfigMock->expects($this->exactly(2))
            ->method('getValue')
            ->with(PaymentGuarantee::XPATH_PAYMENTGUARANTEE_PAYMENT_METHOD, ScopeInterface::SCOPE_STORE)
            ->willReturn($value);

        $instance = $this->getInstance(['scopeConfig' => $scopeConfigMock]);
        $result = $instance->getPaymentMethodToUse();

        $this->assertEquals($expected, $result);
    }

    /**
     * @return array
     */
    public function getSendMailProvider()
    {
        return [
            'Do not send mail' => [
                '0',
                'false'
            ],
            'Send mail' => [
                '1',
                'true'
            ]
        ];
    }

    /**
     * @param $value
     * @param $expected
     *
     * @dataProvider getSendMailProvider
     */
    public function testGetSendMail($value, $expected)
    {
        $scopeConfigMock = $this->getMockBuilder(ScopeConfigInterface::class)->getMock();
        $scopeConfigMock->expects($this->once())
            ->method('getValue')
            ->with(PaymentGuarantee::XPATH_PAYMENTGUARANTEE_SEND_EMAIL, ScopeInterface::SCOPE_STORE)
            ->willReturn($value);

        $instance = $this->getInstance(['scopeConfig' => $scopeConfigMock]);
        $result = $instance->getSendMail();

        $this->assertEquals($expected, $result);
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
            ->with(PaymentGuarantee::XPATH_PAYMENTGUARANTEE_PAYMENT_FEE, ScopeInterface::SCOPE_STORE)
            ->willReturn($fee);

        $instance = $this->getInstance(['scopeConfig' => $scopeConfigMock]);
        $result = $instance->getPaymentFee();

        $this->assertEquals($expected, $result);
    }
}
