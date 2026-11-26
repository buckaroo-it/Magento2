<?php

/**
 * Buckaroo Magento 2 payment module (https://www.buckaroo.eu/)
 *
 * Copyright (c) Buckaroo B.V.
 * See LICENSE for license details.
 *
 * Support: support@buckaroo.nl
 *
 * @copyright Copyright (c) Buckaroo B.V.
 * @license   MIT
 */

namespace Buckaroo\Magento2\Test\Unit\Observer;

use Magento\Framework\Event\Observer;
use Magento\Quote\Model\Quote;
use Magento\Sales\Model\Order;
use Buckaroo\Magento2\Test\BaseTest;
use Buckaroo\Magento2\Observer\SetBuckarooFee;

class SetBuckarooFeeTest extends BaseTest
{
    protected $instanceClass = SetBuckarooFee::class;

    /**
     * Without a Buckaroo fee on the quote nothing is copied to the order.
     */
    public function testInvoiceRegisterWithoutFeeLeavesTheOrderAlone()
    {
        $quoteMock = $this->getMockBuilder(\Buckaroo\Magento2\Test\Unit\Stubs\QuoteStub::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getBaseBuckarooFee'])->getMock();
        $quoteMock->method('getBaseBuckarooFee')->willReturn(false);

        $orderMock = $this->getMockBuilder(\Buckaroo\Magento2\Test\Unit\Stubs\OrderStub::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['setBuckarooFee', 'setBaseBuckarooFee'])->getMock();
        $orderMock->expects($this->never())->method('setBuckarooFee');
        $orderMock->expects($this->never())->method('setBaseBuckarooFee');

        $observerMock = $this->getMockBuilder(\Buckaroo\Magento2\Test\Unit\Stubs\ObserverStub::class)
            ->onlyMethods(['getEvent', 'getQuote', 'getOrder'])->getMock();
        $observerMock->method('getEvent')->willReturnSelf();
        $observerMock->method('getQuote')->willReturn($quoteMock);
        $observerMock->method('getOrder')->willReturn($orderMock);

        $this->getInstance()->execute($observerMock);
    }

    /**
     * Test that the buckaroo fee and base buckaroo fee are set from the quote.
     */
    public function testInvoiceRegisterWithFee()
    {
        // Distinct values, so a fee copied into the wrong order field fails.
        $buckarooFee = 2.5;
        $buckarooBaseFee = 2.25;
        $getBuckarooFeeInclTax = 3.03;
        $getBuckarooFeeTaxAmount = 0.53;
        $getBaseBuckarooFeeInclTax = 2.72;
        $getBuckarooFeeBaseTaxAmount = 0.47;

        $orderMock = $this->getMockBuilder(\Buckaroo\Magento2\Test\Unit\Stubs\OrderStub::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['setBuckarooFee', 'setBaseBuckarooFee', 'setBuckarooFeeInclTax', 'setBuckarooFeeTaxAmount', 'setBaseBuckarooFeeInclTax', 'setBuckarooFeeBaseTaxAmount'])->getMock();
        $orderMock->expects($this->once())->method('setBuckarooFee')->with($buckarooFee);
        $orderMock->expects($this->once())->method('setBaseBuckarooFee')->with($buckarooBaseFee);
        $orderMock->expects($this->once())->method('setBuckarooFeeInclTax')->with($getBuckarooFeeInclTax);
        $orderMock->expects($this->once())->method('setBuckarooFeeTaxAmount')->with($getBuckarooFeeTaxAmount);
        $orderMock->expects($this->once())->method('setBaseBuckarooFeeInclTax')->with($getBaseBuckarooFeeInclTax);
        $orderMock->expects($this->once())->method('setBuckarooFeeBaseTaxAmount')->with($getBuckarooFeeBaseTaxAmount);

        $quoteMock = $this->getMockBuilder(\Buckaroo\Magento2\Test\Unit\Stubs\QuoteStub::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getBuckarooFee', 'getBaseBuckarooFee', 'getBuckarooFeeInclTax', 'getBuckarooFeeTaxAmount', 'getBaseBuckarooFeeInclTax', 'getBuckarooFeeBaseTaxAmount'])->getMock();
        $quoteMock->method('getBuckarooFee')->willReturn($buckarooFee);
        $quoteMock->method('getBaseBuckarooFee')->willReturn($buckarooBaseFee);
        $quoteMock->method('getBuckarooFeeInclTax')->willReturn($getBuckarooFeeInclTax);
        $quoteMock->method('getBuckarooFeeTaxAmount')->willReturn($getBuckarooFeeTaxAmount);
        $quoteMock->method('getBaseBuckarooFeeInclTax')->willReturn($getBaseBuckarooFeeInclTax);
        $quoteMock->method('getBuckarooFeeBaseTaxAmount')->willReturn($getBuckarooFeeBaseTaxAmount);

        $observerMock = $this->getMockBuilder(\Buckaroo\Magento2\Test\Unit\Stubs\ObserverStub::class)
            ->onlyMethods(['getEvent', 'getQuote', 'getOrder'])->getMock();
        $observerMock->method('getEvent')->willReturnSelf();
        $observerMock->method('getOrder')->willReturn($orderMock);
        $observerMock->method('getQuote')->willReturn($quoteMock);

        $instance = $this->getInstance();
        $instance->execute($observerMock);
    }
}
