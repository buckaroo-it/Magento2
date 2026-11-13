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

use Buckaroo\Magento2\Observer\InvoiceRegister;
use Buckaroo\Magento2\Test\BaseTest;
use Buckaroo\Magento2\Test\Unit\Stubs\InvoiceStub;
use Buckaroo\Magento2\Test\Unit\Stubs\ObserverStub;
use Buckaroo\Magento2\Test\Unit\Stubs\OrderStub;

class InvoiceRegisterTest extends BaseTest
{
    protected $instanceClass = InvoiceRegister::class;

    /**
     * When the invoice carries no base Buckaroo fee, the order must never be
     * loaded and none of the invoiced-fee setters may be called.
     */
    public function testExecuteWithoutFeeDoesNotTouchOrder()
    {
        $invoiceMock = $this->getFakeMock(InvoiceStub::class)
            ->onlyMethods(['getBaseBuckarooFee', 'getOrder'])
            ->getMock();
        $invoiceMock->method('getBaseBuckarooFee')->willReturn(null);
        $invoiceMock->expects($this->never())->method('getOrder');

        $observerMock = $this->createObserverMock($invoiceMock);

        $instance = $this->getInstance();
        $result = $instance->execute($observerMock);

        $this->assertSame($instance, $result);
    }

    /**
     * When the invoice carries a Buckaroo fee, each invoiced-fee total on the
     * order must be increased by exactly the invoice amount. All values are
     * exact binary fractions so the float sums are precise.
     */
    public function testExecuteWithFeeAddsInvoiceAmountsToOrderTotals()
    {
        $invoiceMock = $this->getFakeMock(InvoiceStub::class)
            ->onlyMethods([
                'getBaseBuckarooFee',
                'getBuckarooFee',
                'getBuckarooFeeTaxAmount',
                'getBuckarooFeeBaseTaxAmount',
                'getBuckarooFeeInclTax',
                'getBaseBuckarooFeeInclTax',
                'getOrder',
            ])
            ->getMock();
        $invoiceMock->method('getBaseBuckarooFee')->willReturn(2.0);
        $invoiceMock->method('getBuckarooFee')->willReturn(2.5);
        $invoiceMock->method('getBuckarooFeeTaxAmount')->willReturn(0.5);
        $invoiceMock->method('getBuckarooFeeBaseTaxAmount')->willReturn(0.25);
        $invoiceMock->method('getBuckarooFeeInclTax')->willReturn(3.0);
        $invoiceMock->method('getBaseBuckarooFeeInclTax')->willReturn(2.25);

        $orderMock = $this->getFakeMock(OrderStub::class)
            ->onlyMethods([
                'getBuckarooFeeInvoiced',
                'getBaseBuckarooFeeInvoiced',
                'getBuckarooFeeTaxAmountInvoiced',
                'getBuckarooFeeBaseTaxAmountInvoiced',
                'getBuckarooFeeInclTaxInvoiced',
                'getBaseBuckarooFeeInclTaxInvoiced',
                'setBuckarooFeeInvoiced',
                'setBaseBuckarooFeeInvoiced',
                'setBuckarooFeeTaxAmountInvoiced',
                'setBuckarooFeeBaseTaxAmountInvoiced',
                'setBuckarooFeeInclTaxInvoiced',
                'setBaseBuckarooFeeInclTaxInvoiced',
            ])
            ->getMock();

        // Already invoiced totals on the order.
        $orderMock->method('getBuckarooFeeInvoiced')->willReturn(1.25);
        $orderMock->method('getBaseBuckarooFeeInvoiced')->willReturn(1.0);
        $orderMock->method('getBuckarooFeeTaxAmountInvoiced')->willReturn(0.25);
        $orderMock->method('getBuckarooFeeBaseTaxAmountInvoiced')->willReturn(0.125);
        $orderMock->method('getBuckarooFeeInclTaxInvoiced')->willReturn(1.5);
        $orderMock->method('getBaseBuckarooFeeInclTaxInvoiced')->willReturn(1.125);

        // Expected new totals: existing order total + invoice amount.
        $orderMock->expects($this->once())
            ->method('setBuckarooFeeInvoiced')
            ->with(1.25 + 2.5)
            ->willReturnSelf();
        $orderMock->expects($this->once())
            ->method('setBaseBuckarooFeeInvoiced')
            ->with(1.0 + 2.0)
            ->willReturnSelf();
        $orderMock->expects($this->once())
            ->method('setBuckarooFeeTaxAmountInvoiced')
            ->with(0.25 + 0.5)
            ->willReturnSelf();
        $orderMock->expects($this->once())
            ->method('setBuckarooFeeBaseTaxAmountInvoiced')
            ->with(0.125 + 0.25)
            ->willReturnSelf();
        $orderMock->expects($this->once())
            ->method('setBuckarooFeeInclTaxInvoiced')
            ->with(1.5 + 3.0)
            ->willReturnSelf();
        $orderMock->expects($this->once())
            ->method('setBaseBuckarooFeeInclTaxInvoiced')
            ->with(1.125 + 2.25)
            ->willReturnSelf();

        $invoiceMock->method('getOrder')->willReturn($orderMock);

        $observerMock = $this->createObserverMock($invoiceMock);

        $instance = $this->getInstance();
        $result = $instance->execute($observerMock);

        $this->assertSame($instance, $result);
    }

    /**
     * Build an observer mock whose event exposes the given invoice.
     *
     * @param \PHPUnit\Framework\MockObject\MockObject $invoiceMock
     *
     * @return \PHPUnit\Framework\MockObject\MockObject
     */
    private function createObserverMock($invoiceMock)
    {
        $observerMock = $this->getFakeMock(ObserverStub::class)
            ->onlyMethods(['getEvent', 'getInvoice'])
            ->getMock();
        $observerMock->method('getEvent')->willReturnSelf();
        $observerMock->method('getInvoice')->willReturn($invoiceMock);

        return $observerMock;
    }
}
