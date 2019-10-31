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
namespace TIG\Buckaroo\Test\Unit\Model\Total\Creditmemo\Tax;

use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Creditmemo;
use Magento\Sales\Model\Order\Invoice;
use TIG\Buckaroo\Model\Total\Creditmemo\Tax\BuckarooFee;
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
            'no tax on invoice' => [
                0,
                2,
                3,
                0,
                3
            ],
            'no tax invoiced' => [
                5,
                0,
                0,
                0,
                0
            ],
            'all tax refunded' => [
                5,
                2,
                2,
                0,
                2
            ]
        ];
    }

    /**
     * @param $tax
     * @param $taxinvoiced
     * @param $taxrefunded
     * @param $expectedGrandTotal
     * @param $expectedTotalRefunded
     *
     * @dataProvider collectProvider
     */
    public function testCollect($tax, $taxinvoiced, $taxrefunded, $expectedGrandTotal, $expectedTotalRefunded)
    {
        $orderMock = $this->getFakeMock(Order::class)->setMethods(null)->getMock();
        $orderMock->setBuckarooFeeBaseTaxAmountInvoiced($taxinvoiced);
        $orderMock->setBuckarooFeeBaseTaxAmountRefunded($taxrefunded);
        $orderMock->setBuckarooFeeTaxAmountRefunded($taxrefunded);

        $invoiceMock = $this->getFakeMock(Invoice::class)->setMethods(null)->getMock();
        $invoiceMock->setBuckarooFeeBaseTaxAmount($tax);
        $invoiceMock->setBuckarooFeeTaxAmount($tax);

        $creditmemoMock = $this->getFakeMock(Creditmemo::class)->setMethods(['getOrder', 'getInvoice'])->getMock();
        $creditmemoMock->expects($this->once())->method('getOrder')->willReturn($orderMock);
        $creditmemoMock->expects($this->once())->method('getInvoice')->willReturn($invoiceMock);

        $instance = $this->getInstance();
        $result = $instance->collect($creditmemoMock);

        $this->assertInstanceOf(BuckarooFee::class, $result);
        $this->assertEquals($expectedGrandTotal, $creditmemoMock->getGrandTotal());
        $this->assertEquals($expectedTotalRefunded, $orderMock->getBuckarooFeeBaseTaxAmountRefunded());
    }
}
