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
use Magento\Framework\Data\Form\FormKey;
use Magento\Store\Model\ScopeInterface;
use TIG\Buckaroo\Model\ConfigProvider\Method\Payconiq;
use TIG\Buckaroo\Test\BaseTest;

class PayconiqTest extends BaseTest
{
    protected $instanceClass = Payconiq::class;

    public function testGetConfig()
    {
        $scopeConfigMock = $this->getMockBuilder(ScopeConfigInterface::class)->getMock();
        $scopeConfigMock->expects($this->atLeastOnce())
            ->method('getValue')
            ->withConsecutive($this->onConsecutiveCalls([[Payconiq::XPATH_PAYCONIQ_ACTIVE]]))
            ->willReturn(1);

        $formKeyMock = $this->getFakeMock(FormKey::class)->setMethods(['getFormKey'])->getMock();
        $formKeyMock->expects($this->once())->method('getFormKey')->willReturn('123abc');

        $instance = $this->getInstance(['scopeConfig' => $scopeConfigMock, 'formKey' => $formKeyMock]);
        $result = $instance->getConfig();

        $this->assertInternalType('array', $result);

        $resultPaymentBuckaroo = $result['payment']['buckaroo'];

        $this->assertCount(1, $resultPaymentBuckaroo);
        $this->assertArrayHasKey('payconiq', $resultPaymentBuckaroo);
        $this->assertCount(3, $resultPaymentBuckaroo['payconiq']);
        $this->assertArrayHasKey('paymentFeeLabel', $resultPaymentBuckaroo['payconiq']);
        $this->assertArrayHasKey('allowedCurrencies', $resultPaymentBuckaroo['payconiq']);
        $this->assertEquals(
            Payconiq::PAYCONIC_REDIRECT_URL . '?form_key=123abc',
            $resultPaymentBuckaroo['payconiq']['redirecturl']
        );
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
            ->with(Payconiq::XPATH_PAYCONIQ_PAYMENT_FEE, ScopeInterface::SCOPE_STORE)
            ->willReturn($fee);

        $instance = $this->getInstance(['scopeConfig' => $scopeConfigMock]);
        $result = $instance->getPaymentFee();

        $this->assertEquals($expected, $result);
    }
}
