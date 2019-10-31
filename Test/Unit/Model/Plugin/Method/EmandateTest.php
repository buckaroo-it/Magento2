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
namespace TIG\Buckaroo\Test\Unit\Model\Plugin\Method;

use Magento\Sales\Api\Data\OrderPaymentInterface;
use Magento\Sales\Model\Order;
use TIG\Buckaroo\Model\Method\Emandate as EmandateMethod;
use TIG\Buckaroo\Model\Plugin\Method\Emandate;
use TIG\Buckaroo\Test\BaseTest;

class EmandateTest extends BaseTest
{
    protected $instanceClass = Emandate::class;

    /**
     * @return array
     */
    public function beforeCanCreditmemoProvider()
    {
        return [
            'different payment method' => [
                'tig_buckaroo_ideal',
                true,
                null

            ],
            'emandate, can refund' => [
                'tig_buckaroo_emandate',
                true,
                true
            ],
            'emandate, can not refund' => [
                'tig_buckaroo_emandate',
                false,
                false
            ]
        ];
    }

    /**
     * @param $method
     * @param $canRefund
     * @param $expected
     *
     * @dataProvider beforeCanCreditmemoProvider
     */
    public function testBeforeCanCreditmemo($method, $canRefund, $expected)
    {
        $paymentMock = $this->getFakeMock(OrderPaymentInterface::class)
            ->setMethods(['getMethod'])
            ->getMockForAbstractClass();
        $paymentMock->expects($this->once())->method('getMethod')->willReturn($method);

        $orderMock = $this->getFakeMock(Order::class)->setMethods(['getPayment'])->getMock();
        $orderMock->expects($this->once())->method('getPayment')->willReturn($paymentMock);

        $emandateMethodMock = $this->getFakeMock(EmandateMethod::class)->setMethods(['canRefund'])->getMock();
        $emandateMethodMock->method('canRefund')->willReturn($canRefund);

        $instance = $this->getInstance(['emandate' => $emandateMethodMock]);
        $result = $instance->beforeCanCreditmemo($orderMock);

        $this->assertEquals($orderMock, $result);
        $this->assertEquals($expected, $result->getForcedCanCreditmemo());
    }
}
