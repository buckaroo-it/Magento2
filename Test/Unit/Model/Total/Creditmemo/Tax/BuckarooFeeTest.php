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

namespace Buckaroo\Magento2\Test\Unit\Model\Total\Creditmemo\Tax;

use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Creditmemo;
use Magento\Sales\Model\Order\Invoice;
use Buckaroo\Magento2\Model\Total\Creditmemo\Tax\BuckarooFee;
use Buckaroo\Magento2\Test\BaseTest;

class BuckarooFeeTest extends BaseTest
{
    protected $instanceClass = BuckarooFee::class;

    /**
     * @return array
     */
    public static function collectProvider()
    {
        return [
            'no tax on invoice' => [
                0,
                2,
                3,
                '0',
                3
            ],
            'no tax invoiced' => [
                5,
                0,
                0,
                '0',
                0
            ],
            'all tax refunded' => [
                5,
                2,
                2,
                '0',
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
        $orderMock = $this->getFakeMock(Order::class)
            ->addMethods([
                'getBuckarooFeeBaseTaxAmountInvoiced',
                'getBuckarooFeeBaseTaxAmountRefunded',
                'setBuckarooFeeBaseTaxAmountRefunded'
            ])
            ->getMock();
        $orderMock->setBuckarooFeeBaseTaxAmountInvoiced($taxinvoiced);
        $orderMock->setBuckarooFeeBaseTaxAmountRefunded($taxrefunded);
        $orderMock->setBuckarooFeeTaxAmountRefunded($taxrefunded);

        // Set initial values that the collect method expects to read
        $orderMock->expects($this->any())->method('getBuckarooFeeBaseTaxAmountInvoiced')->willReturn($taxinvoiced);

        // The getBuckarooFeeBaseTaxAmountRefunded will be called to read initial value and potentially after setting new value
        $initialRefunded = $taxrefunded;
        $orderMock->expects($this->any())->method('getBuckarooFeeBaseTaxAmountRefunded')->willReturnCallback(function () use (&$initialRefunded) {
            return $initialRefunded;
        });

        // Mock the setter to track changes
        $orderMock->expects($this->any())->method('setBuckarooFeeBaseTaxAmountRefunded')->willReturnCallback(function ($value) use (&$initialRefunded) {
            $initialRefunded = $value;
        });

        $invoiceMock = $this->getFakeMock(Invoice::class)
            ->addMethods([
                'getBuckarooFeeBaseTaxAmount',
                'getBuckarooFeeTaxAmount'
            ])
            ->getMock();
        $invoiceMock->setBuckarooFeeBaseTaxAmount($tax);
        $invoiceMock->setBuckarooFeeTaxAmount($tax);

        // Set up invoice mock methods
        $invoiceMock->expects($this->any())->method('getBuckarooFeeBaseTaxAmount')->willReturn($tax);
        $invoiceMock->expects($this->any())->method('getBuckarooFeeTaxAmount')->willReturn($tax);

        $creditmemoMock = $this->getFakeMock(Creditmemo::class)->onlyMethods(['getOrder', 'getInvoice'])->getMock();
        $creditmemoMock->method('getOrder')->willReturn($orderMock);
        $creditmemoMock->method('getInvoice')->willReturn($invoiceMock);

        // Set initial values for creditmemo
        $creditmemoMock->setGrandTotal(0);
        $creditmemoMock->setBuckarooFeeTaxAmount(0);

        $instance = $this->getInstance();
        $result = $instance->collect($creditmemoMock);

        $this->assertInstanceOf(BuckarooFee::class, $result);
        $this->assertEquals($expectedGrandTotal, $creditmemoMock->getGrandTotal());
        $this->assertEquals($expectedTotalRefunded, $orderMock->getBuckarooFeeBaseTaxAmountRefunded());
        $this->assertEquals($expectedGrandTotal, $creditmemoMock->getBuckarooFeeTaxAmount());
    }
}
