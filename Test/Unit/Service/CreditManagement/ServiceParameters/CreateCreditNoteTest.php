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

namespace Buckaroo\Magento2\Test\Unit\Service\CreditManagement\ServiceParameters;

use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Payment;
use Buckaroo\Magento2\Service\CreditManagement\ServiceParameters\CreateCreditNote;
use Buckaroo\Magento2\Test\BaseTest;

class CreateCreditNoteTest extends BaseTest
{
    protected $instanceClass = CreateCreditNote::class;

    /**
     * The credit note parameters for a fixed order.
     */
    public function testGet()
    {
        $orderMock = $this->getFakeMock(Order::class)
            ->onlyMethods(['getGrandTotal', 'getTaxAmount', 'getIncrementId'])
            ->getMock();
        $orderMock->method('getGrandTotal')->willReturn(121.00);
        $orderMock->method('getTaxAmount')->willReturn(21.00);
        $orderMock->method('getIncrementId')->willReturn('000000042');

        $infoInstanceMock = $this->getFakeMock(Payment::class)
            ->onlyMethods(['getAdditionalInformation', 'getOrder'])
            ->getMock();
        $infoInstanceMock->method('getAdditionalInformation')
            ->with('buckaroo_cm3_invoice_key')
            ->willReturn('abc');
        $infoInstanceMock->method('getOrder')->willReturn($orderMock);

        $instance = $this->getInstance();

        // Retry if the day changes during the call, so the expected date stays exact.
        do {
            $today = date('Y-m-d');
            $result = $instance->get($infoInstanceMock);
        } while ($today !== date('Y-m-d'));

        $this->assertSame(
            [
                'Name'             => 'CreditManagement3',
                'Action'           => 'CreateCreditNote',
                'Version'          => 1,
                'RequestParameter' => [
                    ['_' => 121.00, 'Name' => 'InvoiceAmount'],
                    ['_' => 21.00, 'Name' => 'InvoiceAmountVat'],
                    ['_' => $today, 'Name' => 'InvoiceDate'],
                    ['_' => '000000042', 'Name' => 'OriginalInvoiceNumber'],
                ],
            ],
            $result
        );
    }

    /**
     * Without a stored invoice key there is no credit note to create.
     */
    public function testGetReturnsEmptyWithoutInvoiceKey()
    {
        $infoInstanceMock = $this->getFakeMock(Payment::class)
            ->onlyMethods(['getAdditionalInformation', 'getOrder'])
            ->getMock();
        $infoInstanceMock->method('getAdditionalInformation')
            ->with('buckaroo_cm3_invoice_key')
            ->willReturn('');
        $infoInstanceMock->expects($this->never())->method('getOrder');

        $this->assertSame([], $this->getInstance()->get($infoInstanceMock));
    }
}
