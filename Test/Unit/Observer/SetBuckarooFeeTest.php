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
     * Test the happy path. No Buckaroo Payment Fee
     */
    public function testInvoiceRegisterHappyPath()
    {
        $quoteMock = $this->getMockBuilder(\Buckaroo\Magento2\Test\Unit\Stubs\QuoteStub::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getBaseBuckarooFee'])->getMock();
        $quoteMock->method('getBaseBuckarooFee')->willReturn(false);

        $observerMock = $this->getMockBuilder(\Buckaroo\Magento2\Test\Unit\Stubs\ObserverStub::class)
            ->onlyMethods(['getEvent', 'getQuote'])->getMock();
        $observerMock->method('getEvent')->willReturnSelf();
        $observerMock->method('getQuote')->willReturn($quoteMock);

        $instance = $this->getInstance();
        $result = $instance->execute($observerMock);

        // Assert that the method executed without throwing exceptions
        $this->assertNull($result, 'execute method should return null when no fee is present');
    }

    /**
     * Test that the buckaroo fee and base buckaroo fee are set from the quote.
     */
    public function testInvoiceRegisterWithFee()
    {
        $buckarooFee = rand(1, 1000);
        $buckarooBaseFee = rand(1, 1000);
        $getBuckarooFeeInclTax = rand(1, 1000);
        $getBuckarooFeeTaxAmount = rand(1, 1000);
        $getBaseBuckarooFeeInclTax = rand(1, 1000);
        $getBuckarooFeeBaseTaxAmount = rand(1, 1000);

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
        $result = $instance->execute($observerMock);

        // Assert that the method executed without throwing exceptions
        // The expectations on the order mock methods will verify the behavior
        $this->assertNull($result, 'execute method should return null after setting fees');
    }
}
