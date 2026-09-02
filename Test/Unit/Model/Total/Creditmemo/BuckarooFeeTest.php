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

namespace Buckaroo\Magento2\Test\Unit\Model\Total\Creditmemo;

use PHPUnit\Framework\Attributes\DataProvider;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Creditmemo;
use Buckaroo\Magento2\Model\Total\Creditmemo\BuckarooFee;
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
            'no fee on invoice' => [
                0,
                2,
                3,
                '0',
                3
            ],
            'no fee invoiced' => [
                5,
                0,
                0,
                '0',
                0
            ],
            'all fee refunded' => [
                5,
                2,
                2,
                '0',
                2
            ],
            'new fee to refund' => [
                5,
                10,
                2,
                '5',
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
     */
    #[DataProvider('collectProvider')]
    public function testCollect($fee, $feeinvoiced, $feerefunded, $expectedGrandTotal, $expectedTotalRefunded)
    {
        // Mock Payment
        $paymentMock = $this->getFakeMock(\Magento\Sales\Model\Order\Payment::class)
            ->onlyMethods(['getMethod'])
            ->getMock();
        $paymentMock->method('getMethod')->willReturn('buckaroo_magento2_ideal');

        // Mock CreditmemoCollection
        $creditmemoCollectionMock =
            $this->createMock(\Magento\Sales\Model\ResourceModel\Order\Creditmemo\Collection::class);

        // Partial Order mock: getPayment/getCreditmemosCollection are mocked, the buckaroo
        // fee fields go through the real DataObject magic getters/setters so collect()
        // mutates real state we can assert on afterwards.
        $orderMock = $this->getFakeMock(Order::class)
            ->onlyMethods(['getPayment', 'getCreditmemosCollection'])
            ->getMock();
        $orderMock->method('getPayment')->willReturn($paymentMock);
        $orderMock->method('getCreditmemosCollection')->willReturn($creditmemoCollectionMock);
        $orderMock->setBaseBuckarooFeeInvoiced($feeinvoiced);
        $orderMock->setBaseBuckarooFeeRefunded($feerefunded);
        $orderMock->setBuckarooFeeRefunded($feerefunded);

        // Invoice mock: the invoiced buckaroo fee amounts drive the calculation.
        $invoiceMock = $this->getFakeMock(\Buckaroo\Magento2\Test\Unit\Stubs\InvoiceStub::class)
            ->onlyMethods(['getBaseBuckarooFee', 'getBuckarooFee'])
            ->getMock();
        $invoiceMock->method('getBaseBuckarooFee')->willReturn($fee);
        $invoiceMock->method('getBuckarooFee')->willReturn($fee);

        // Partial Creditmemo mock: only the order/invoice lookups are mocked, the grand
        // totals and buckaroo fee fields use the real (stateful) implementation.
        $creditmemoMock = $this->getFakeMock(Creditmemo::class)
            ->onlyMethods(['getOrder', 'getInvoice'])
            ->getMock();
        $creditmemoMock->method('getOrder')->willReturn($orderMock);
        $creditmemoMock->method('getInvoice')->willReturn($invoiceMock);
        $creditmemoMock->setGrandTotal(0);
        $creditmemoMock->setBaseGrandTotal(0);

        // Mock Request (use HTTP request which has getPost method)
        $requestMock = $this->createMock(\Magento\Framework\App\Request\Http::class);
        $requestMock->method('getPost')->with('creditmemo')->willReturn([]);

        $instance = $this->getInstance(['request' => $requestMock]);
        $result = $instance->collect($creditmemoMock);

        $this->assertInstanceOf(BuckarooFee::class, $result);
        $this->assertSame($instance, $result);

        // The creditmemo grand totals must be raised by exactly the refundable fee.
        $this->assertEquals(
            $expectedGrandTotal,
            $creditmemoMock->getGrandTotal(),
            'Creditmemo grand total should equal initial total plus the refundable buckaroo fee'
        );
        $this->assertEquals(
            $expectedGrandTotal,
            $creditmemoMock->getBaseGrandTotal(),
            'Creditmemo base grand total should equal initial total plus the refundable base buckaroo fee'
        );

        // The order refunded-fee counters must be increased by the refunded fee.
        $this->assertEquals(
            $expectedTotalRefunded,
            $orderMock->getBaseBuckarooFeeRefunded(),
            'Order base_buckaroo_fee_refunded should be increased by the refunded base fee'
        );
        $this->assertEquals(
            $expectedTotalRefunded,
            $orderMock->getBuckarooFeeRefunded(),
            'Order buckaroo_fee_refunded should be increased by the refunded fee'
        );

        // When a fee is actually refunded it must also be written onto the creditmemo itself.
        if ($fee && $feeinvoiced > $feerefunded) {
            $this->assertEquals($fee, $creditmemoMock->getBaseBuckarooFee());
            $this->assertEquals($fee, $creditmemoMock->getBuckarooFee());
        }
    }
}
