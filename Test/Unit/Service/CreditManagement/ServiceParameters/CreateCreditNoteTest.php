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

    public function testGet()
    {
        $orderMock = $this->getFakeMock(Order::class)->getMock();

        $infoInstanceMock = $this->getFakeMock(Payment::class)
            ->onlyMethods(['getAdditionalInformation', 'getOrder'])
            ->getMock();
        $infoInstanceMock->method('getAdditionalInformation')
            ->with('buckaroo_cm3_invoice_key')
            ->willReturn('abc');
        $infoInstanceMock->method('getOrder')->willReturn($orderMock);

        $instance = $this->getInstance();
        $result = $instance->get($infoInstanceMock);

        $this->assertIsArray($result);
        $this->assertEquals('CreditManagement3', $result['Name']);
        $this->assertEquals('CreateCreditNote', $result['Action']);
        $this->assertEquals(1, $result['Version']);
        $this->assertCount(4, $result['RequestParameter']);

        $possibleParameters = ['InvoiceAmount', 'InvoiceAmountVat', 'InvoiceDate', 'OriginalInvoiceNumber'];

        foreach ($result['RequestParameter'] as $array) {
            $this->assertArrayHasKey('_', $array);
            $this->assertArrayHasKey('Name', $array);
            $this->assertTrue(in_array($array['Name'], $possibleParameters));
        }
    }
}
