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
namespace TIG\Buckaroo\Test\Unit\Model\Refund;

use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Creditmemo;
use Magento\Sales\Model\Order\Creditmemo\Item;
use Magento\Sales\Model\Order\CreditmemoFactory;
use Magento\Sales\Model\Order\Item as OrderItem;
use TIG\Buckaroo\Exception;
use TIG\Buckaroo\Model\ConfigProvider\Refund;
use TIG\Buckaroo\Model\Refund\Push;

class PushTest extends \TIG\Buckaroo\Test\BaseTest
{
    protected $instanceClass = Push::class;

    /**
     * Test the happy path of the receiveRefundMethod.
     */
    public function testReceiveRefundPush()
    {
        $id = rand(1, 1000);

        $postData = [
            'brq_currency' => false,
            'brq_amount_credit' => 0,
            'brq_transactions' => $id,
        ];

        $creditmemoMock = $this->getFakeMock(Creditmemo::class)
            ->setMethods(['getAllItems', 'isValidGrandTotal', 'setTransactionId'])
            ->getMock();
        $creditmemoMock->expects($this->any())->method('getAllItems')->willReturn([]);
        $creditmemoMock->expects($this->any())->method('isValidGrandTotal')->willReturn(true);
        $creditmemoMock->expects($this->once())->method('setTransactionId')->with($id);

        $creditmemoFactoryMock = $this->getFakeMock(CreditmemoFactory::class)
            ->setMethods(['createByOrder', 'getItems', 'getItemsByColumnValue'])
            ->getMock();
        $creditmemoFactoryMock->expects($this->once())->method('createByOrder')->willReturn($creditmemoMock);
        $creditmemoFactoryMock->expects($this->once())->method('getItems')->willReturn([]);
        $creditmemoFactoryMock->expects($this->once())
            ->method('getItemsByColumnValue')
            ->with('transaction_id', $id)
            ->willReturn([]);

        $configRefundMock = $this->getFakeMock(Refund::class)->setMethods(['getAllowPush'])->getMock();
        $configRefundMock->expects($this->once())->method('getAllowPush')->willReturn(true);

        $orderMock = $this->getFakeMock(Order::class)
            ->setMethods(['getId', 'getCreditmemosCollection', 'getItemsCollection'])
            ->getMock();
        $orderMock->expects($this->once())->method('getId')->willReturn($id);
        $orderMock->expects($this->exactly(2))->method('getCreditmemosCollection')->willReturn($creditmemoFactoryMock);
        $orderMock->expects($this->once())->method('getItemsCollection')->willReturn($creditmemoFactoryMock);

        $instance = $this->getInstance([
            'creditmemoFactory' => $creditmemoFactoryMock,
            'configRefund' => $configRefundMock
        ]);

        $result = $instance->receiveRefundPush($postData, true, $orderMock);
        $this->assertTrue($result);
    }

    /**
     * Test the path of the receiveRefundMethod where the signature is invalid.
     */
    public function testReceiveRefundPushInvalidSignature()
    {
        $configRefundMock = $this->getFakeMock(Refund::class)->setMethods(['getAllowPush'])->getMock();
        $configRefundMock->expects($this->once())->method('getAllowPush')->willReturn(true);

        $orderMock = $this->getFakeMock(Order::class)->setMethods(['canCreditmemo'])->getMock();
        $orderMock->expects($this->exactly(2))->method('canCreditmemo')->willReturn(false);

        $instance = $this->getInstance(['configRefund' => $configRefundMock]);

        try {
            $instance->receiveRefundPush([], false, $orderMock);
        } catch (Exception $e) {
            $this->assertEquals('Buckaroo refund push validation failed', $e->getMessage());
        }
    }

    /**
     * Test the path with an invalid grand total
     */
    public function testCreateCreditMemoInvalidGrandTotal()
    {
        $creditmemoItemMock = $this->getFakeMock(Item::class)->setMethods(['setBackToStock'])->getMock();
        $creditmemoItemMock->expects($this->once())->method('setBackToStock');

        $creditmemoFactoryMock = $this->getFakeMock(CreditmemoFactory::class)
            ->setMethods(['getItems', 'getAllItems', 'isValidGrandTotal', 'createByOrder'])
            ->getMock();
        $creditmemoFactoryMock->expects($this->once())->method('getItems')->willReturn([]);
        $creditmemoFactoryMock->expects($this->once())->method('getAllItems')->willReturn([$creditmemoItemMock]);
        $creditmemoFactoryMock->expects($this->once())->method('isValidGrandTotal')->willReturn(false);
        $creditmemoFactoryMock->expects($this->once())->method('createByOrder')->willReturnSelf();

        $orderMock = $this->getFakeMock(Order::class)
            ->setMethods(['getCreditmemosCollection', 'getItemsCollection'])
            ->getMock();
        $orderMock->expects($this->once())->method('getCreditmemosCollection')->willReturn($creditmemoFactoryMock);
        $orderMock->expects($this->once())->method('getItemsCollection')->willReturn($creditmemoFactoryMock);

        $instance = $this->getInstance(['creditmemoFactory' => $creditmemoFactoryMock]);
        $instance->order = $orderMock;

        $result = $instance->createCreditmemo();
        $this->assertFalse($result);
    }

    /**
     * Test the path with an invalid grand total
     */
    public function testCreateCreditMemoUnableToCreate()
    {
        $creditmemoFactoryMock = $this->getFakeMock(CreditmemoFactory::class)
            ->setMethods(['getItems', 'getAllItems', 'isValidGrandTotal', 'createByOrder'])
            ->getMock();
        $creditmemoFactoryMock->expects($this->once())->method('getItems')->willReturn([]);
        $creditmemoFactoryMock->expects($this->once())->method('getAllItems')->willReturn([]);
        $creditmemoFactoryMock->expects($this->once())->method('isValidGrandTotal')->willReturn(false);
        $creditmemoFactoryMock->expects($this->once())->method('createByOrder')->willReturnSelf();

        $orderMock = $this->getFakeMock(Order::class)
            ->setMethods(['getCreditmemosCollection', 'getItemsCollection'])
            ->getMock();
        $orderMock->expects($this->once())->method('getCreditmemosCollection')->willReturn($creditmemoFactoryMock);
        $orderMock->expects($this->once())->method('getItemsCollection')->willReturn($creditmemoFactoryMock);

        $instance = $this->getInstance(['creditmemoFactory' => $creditmemoFactoryMock]);
        $instance->order = $orderMock;

        $result = $instance->createCreditmemo();
        $this->assertFalse($result);
    }

    /**
     * Unit test for the getCreditmemoData method.
     */
    public function testGetCreditmemoData()
    {
        $orderMock = $this->getFakeMock(Order::class)
            ->setMethods(['getBaseGrandTotal', 'getBaseTotalRefunded', 'getBaseToOrderRate', 'getAllItems'])
            ->getMock();
        $orderMock->expects($this->once())->method('getBaseGrandTotal')->willReturn(999);
        $orderMock->expects($this->exactly(2))->method('getBaseTotalRefunded')->willReturn('0');
        $orderMock->expects($this->exactly(2))->method('getBaseToOrderRate')->willReturn('1');
        $orderMock->expects($this->once())->method('getAllItems')->willReturn([]);

        $postData = [
            'brq_currency' => 'EUR',
            'brq_amount_credit' => '100'
        ];

        $instance = $this->getInstance();
        $instance->postData = $postData;
        $instance->order = $orderMock;

        $result = $instance->getCreditmemoData();

        $this->assertEquals(0, $result['shipping_amount']);
        $this->assertEquals(0, $result['adjustment_negative']);
        $this->assertEquals(array(), $result['items']);
        $this->assertEquals('100', $result['adjustment_positive']);
    }

    /**
     * Unit test for the getTotalCreditAdjustments method.
     */
    public function testGetTotalCreditAdjustments()
    {
        $creditmemoMock = $this->getFakeMock(Creditmemo::class)
            ->setMethods(['getBaseAdjustmentPositive', 'getBaseAdjustmentNegative'])
            ->getMock();
        $creditmemoMock->expects($this->once())->method('getBaseAdjustmentPositive')->willReturn(12);
        $creditmemoMock->expects($this->once())->method('getBaseAdjustmentNegative')->willReturn(8);

        $orderMock = $this->getFakeMock(Order::class)->setMethods(['getCreditmemosCollection'])->getMock();
        $orderMock->expects($this->once())->method('getCreditmemosCollection')->willReturn([$creditmemoMock]);

        $instance = $this->getInstance();
        $instance->order = $orderMock;

        $result = $instance->getTotalCreditAdjustments();
        $this->assertEquals(4, $result);
    }

    /**
     * Unit test for the getAdjustmentRefundData method.
     */
    public function testGetAdjustmentRefundData()
    {
        $postData = [
            'brq_currency' => 'EUR',
            'brq_amount_credit' => '100',
        ];

        $orderMock = $this->getFakeMock(Order::class)
            ->setMethods([
                'getBaseToOrderRate', 'getBaseTotalRefunded',
                'getBaseBuckarooFeeInvoiced', 'getBuckarooFeeBaseTaxAmountInvoiced'
            ])
            ->getMock();
        $orderMock->expects($this->once())->method('getBaseToOrderRate')->willReturn(1);
        $orderMock->expects($this->once())->method('getBaseTotalRefunded')->willReturn(null);
        $orderMock->expects($this->once())->method('getBaseBuckarooFeeInvoiced')->willReturn(10);
        $orderMock->expects($this->once())->method('getBuckarooFeeBaseTaxAmountInvoiced')->willReturn(5);

        $instance = $this->getInstance();
        $instance->postData = $postData;
        $instance->order = $orderMock;

        $result = $instance->getAdjustmentRefundData();

        $this->assertEquals(85, $result);
    }

    /**
     * Unit test for the getCreditmemoDataItems method.
     */
    public function testGetCreditmemoDataItems()
    {
        $orderItemMock = $this->getFakeMock(OrderItem::class)->setMethods(['getId', 'getQtyInvoiced', 'getQtyRefunded'])->getMock();
        $orderItemMock->expects($this->exactly(2))->method('getId')->willReturn(1);
        $orderItemMock->expects($this->once())->method('getQtyInvoiced')->willReturn(10);
        $orderItemMock->expects($this->once())->method('getQtyRefunded')->willReturn(3);

        $orderMock = $this->getFakeMock(Order::class)->setMethods(['getAllItems'])->getMock();
        $orderMock->expects($this->once())->method('getAllItems')->willReturn([$orderItemMock]);

        $instance = $this->getInstance();
        $instance->order = $orderMock;

        $result = $instance->getCreditmemoDataItems();
        $this->assertEquals(7, $result[1]['qty']);
    }

    /**
     * Unit test for the setCreditQtys method.
     */
    public function testSetCreditQtys()
    {
        $items = [
            15 => ['qty' => 30],
            16 => ['qty' => 32],
        ];

        $instance = $this->getInstance();
        $result = $instance->setCreditQtys($items);

        $this->assertEquals(30, $result[15]);
        $this->assertEquals(32, $result[16]);
    }
}
