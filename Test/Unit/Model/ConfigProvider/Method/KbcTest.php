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
use TIG\Buckaroo\Helper\PaymentFee;
use TIG\Buckaroo\Model\Method\Kbc as KbcMethod;
use TIG\Buckaroo\Test\BaseTest;
use TIG\Buckaroo\Model\ConfigProvider\Method\Kbc;
use \Magento\Framework\App\Config\ScopeConfigInterface;

class KbcTest extends BaseTest
{
    protected $instanceClass = Kbc::class;

    public function getConfigProvider()
    {
        return [
            'active' => [
                true,
                ['payment' => ['buckaroo' => ['kbc' => ['paymentFeeLabel' => 'Fee', 'allowedCurrencies' => ['EUR'],]]]]
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
                [Kbc::XPATH_KBC_ACTIVE, ScopeInterface::SCOPE_STORE],
                [Kbc::XPATH_ALLOWED_CURRENCIES, ScopeInterface::SCOPE_STORE, null]
            )
            ->willReturnOnConsecutiveCalls($active, 'EUR');

        $paymentFeeMock = $this->getFakeMock(PaymentFee::class)->setMethods(['getBuckarooPaymentFeeLabel'])->getMock();
        $paymentFeeMock->method('getBuckarooPaymentFeeLabel')->with(KbcMethod::PAYMENT_METHOD_CODE)->willReturn('Fee');

        $instance = $this->getInstance(['scopeConfig' => $scopeConfigMock, 'paymentFeeHelper' => $paymentFeeMock]);
        $result = $instance->getConfig();

        $this->assertEquals($expected, $result);
    }

    public function getPaymentFeeProvider()
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
                1,
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
            ->setMethods(['getValue'])
            ->getMockForAbstractClass();
        $scopeConfigMock->expects($this->once())
            ->method('getValue')
            ->with(Kbc::XPATH_KBC_PAYMENT_FEE, ScopeInterface::SCOPE_STORE)
            ->willReturn($value);

        $instance = $this->getInstance(['scopeConfig' => $scopeConfigMock]);
        $result = $instance->getPaymentFee();

        $this->assertEquals($expected, $result);
    }
}
