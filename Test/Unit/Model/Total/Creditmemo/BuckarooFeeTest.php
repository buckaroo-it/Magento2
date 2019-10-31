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
namespace TIG\Buckaroo\Test\Unit\Model\Total\Creditmemo;

use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Creditmemo;
use Magento\Sales\Model\Order\Invoice;
use TIG\Buckaroo\Model\Total\Creditmemo\BuckarooFee;
use TIG\Buckaroo\Test\BaseTest;

class BuckarooFeeTest extends BaseTest
{
    protected $instanceClass = BuckarooFee::class;

    /**
     * @return array
     */
    public function collectProvider()
    {
        return [
            'no fee on invoice' => [
                0,
                2,
                3,
                0,
                3
            ],
            'no fee invoiced' => [
                5,
                0,
                0,
                0,
                0
            ],
            'all fee refunded' => [
                5,
                2,
                2,
                0,
                2
            ],
            'new fee to refund' => [
                5,
                10,
                2,
                5,
                7
            ]
        ];
    }

    /**
     * @param $fee
     * @param $feeinvoiced
     * @param $feerefunded
     * @param $expectedGrandTotal
     * @param $expectedTotalRefunded
     *
     * @dataProvider collectProvider
     */
    public function testCollect($fee, $feeinvoiced, $feerefunded, $expectedGrandTotal, $expectedTotalRefunded)
    {
        $orderMock = $this->getFakeMock(Order::class)->setMethods(null)->getMock();
        $orderMock->setBaseBuckarooFeeInvoiced($feeinvoiced);
        $orderMock->setBaseBuckarooFeeRefunded($feerefunded);
        $orderMock->setBuckarooFeeRefunded($feerefunded);

        $invoiceMock = $this->getFakeMock(Invoice::class)->setMethods(null)->getMock();
        $invoiceMock->setBaseBuckarooFee($fee);
        $invoiceMock->setBuckarooFee($fee);

        $creditmemoMock = $this->getFakeMock(Creditmemo::class)->setMethods(['getOrder', 'getInvoice'])->getMock();
        $creditmemoMock->expects($this->once())->method('getOrder')->willReturn($orderMock);
        $creditmemoMock->expects($this->once())->method('getInvoice')->willReturn($invoiceMock);

        $instance = $this->getInstance();
        $result = $instance->collect($creditmemoMock);

        $this->assertInstanceOf(BuckarooFee::class, $result);
        $this->assertEquals($expectedGrandTotal, $creditmemoMock->getGrandTotal());
        $this->assertEquals($expectedTotalRefunded, $orderMock->getBaseBuckarooFeeRefunded());
    }
}
