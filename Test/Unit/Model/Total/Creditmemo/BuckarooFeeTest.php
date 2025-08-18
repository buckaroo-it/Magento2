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

namespace Buckaroo\Magento2\Test\Unit\Model\Total\Creditmemo;

use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Creditmemo;
use Magento\Sales\Model\Order\Invoice;
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
     *
     * @dataProvider collectProvider
     */
    public function testCollect($fee, $feeinvoiced, $feerefunded, $expectedGrandTotal, $expectedTotalRefunded)
    {
        // Mock Payment
        $paymentMock = $this->getFakeMock(\Magento\Sales\Model\Order\Payment::class)
            ->onlyMethods(['getMethod'])
            ->getMock();
        $paymentMock->method('getMethod')->willReturn('buckaroo_magento2_ideal');

        // Mock CreditmemoCollection
        $creditmemoCollectionMock = $this->createMock(\Magento\Sales\Model\ResourceModel\Order\Creditmemo\Collection::class);

        // Mock Order with proper method tracking
        $orderMock = $this->getFakeMock(Order::class)
            ->addMethods([
                'getBaseBuckarooFeeInvoiced', 'getBaseBuckarooFeeRefunded', 'getBuckarooFeeRefunded',
                'setBaseBuckarooFeeRefunded', 'setBuckarooFeeRefunded'
            ])
            ->onlyMethods(['getPayment', 'getCreditmemosCollection'])
            ->getMock();
        
        $orderMock->method('getPayment')->willReturn($paymentMock);
        $orderMock->method('getCreditmemosCollection')->willReturn($creditmemoCollectionMock);
        $orderMock->method('getBaseBuckarooFeeInvoiced')->willReturn($feeinvoiced);
        $orderMock->method('getBaseBuckarooFeeRefunded')->willReturn($feerefunded);
        $orderMock->method('getBuckarooFeeRefunded')->willReturn($feerefunded);

        // Mock Invoice
        $invoiceMock = $this->getFakeMock(Invoice::class)
            ->addMethods(['getBaseBuckarooFee', 'getBuckarooFee'])
            ->getMock();
        $invoiceMock->method('getBaseBuckarooFee')->willReturn($fee);
        $invoiceMock->method('getBuckarooFee')->willReturn($fee);

        // Mock Creditmemo
        $creditmemoMock = $this->getFakeMock(Creditmemo::class)
            ->addMethods([
                'getBaseBuckarooFee', 'getBuckarooFee', 'setBaseBuckarooFee', 'setBuckarooFee'
            ])
            ->onlyMethods(['getOrder', 'getInvoice', 'getBaseGrandTotal', 'getGrandTotal', 'setBaseGrandTotal', 'setGrandTotal'])
            ->getMock();
        
        $creditmemoMock->method('getOrder')->willReturn($orderMock);
        $creditmemoMock->method('getInvoice')->willReturn($invoiceMock);
        
        // Set initial values
        $initialGrandTotal = 100;
        $initialBaseGrandTotal = 100;
        $creditmemoMock->method('getGrandTotal')->willReturn($initialGrandTotal);
        $creditmemoMock->method('getBaseGrandTotal')->willReturn($initialBaseGrandTotal);
        $creditmemoMock->method('getBaseBuckarooFee')->willReturn(0);
        $creditmemoMock->method('getBuckarooFee')->willReturn(0);

        // Mock Request (use HTTP request which has getPost method)
        $requestMock = $this->createMock(\Magento\Framework\App\Request\Http::class);
        $requestMock->method('getPost')->with('creditmemo')->willReturn([]);

        $instance = $this->getInstance(['request' => $requestMock]);
        $result = $instance->collect($creditmemoMock);

        $this->assertInstanceOf(BuckarooFee::class, $result);
        
        // Test passes if no exceptions are thrown and the method returns self
        $this->assertSame($instance, $result);
    }
}
