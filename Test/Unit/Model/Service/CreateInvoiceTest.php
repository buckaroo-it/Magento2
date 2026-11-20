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

namespace Buckaroo\Magento2\Test\Unit\Model\Service;

use Buckaroo\Magento2\Exception as BuckarooException;
use Buckaroo\Magento2\Model\Method\BuckarooAdapter;
use Buckaroo\Magento2\Model\Service\CreateInvoice;
use Buckaroo\Magento2\Test\BaseTest;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Item;
use Magento\Sales\Model\Order\Payment;
use Magento\Sales\Model\Order\Payment\Transaction;
use Magento\Sales\Model\Order\Shipment;
use Magento\Sales\Model\Order\Shipment\Item as ShipmentItem;

class CreateInvoiceTest extends BaseTest
{
    protected $instanceClass = CreateInvoice::class;

    /**
     * Ship-separately bundles post child item qtys only; the bundle parent qty
     * must be derived so fixed-price bundles end up on the invoice.
     */
    public function testAddMissingBundleParentQtysAddsParentDerivedFromChildren()
    {
        $childOne = $this->createOrderItemMock(11, ['getProductOptions' => $this->selectionOptions(1)]);
        $childTwo = $this->createOrderItemMock(12, ['getProductOptions' => $this->selectionOptions(2)]);
        $parent = $this->createOrderItemMock(10, [
            'getQtyToInvoice' => 2.0,
            'getChildrenItems' => [$childOne, $childTwo],
        ]);

        $result = $this->invokeAddMissingBundleParentQtys(
            [$parent, $childOne, $childTwo],
            [11 => 2, 12 => 4, 99 => 1]
        );

        $this->assertSame(
            [11 => 2, 12 => 4, 99 => 1, 10 => 2.0],
            $result
        );
    }

    public function testAddMissingBundleParentQtysKeepsParentQtyWhenAlreadyProvided()
    {
        $child = $this->createOrderItemMock(11, ['getProductOptions' => $this->selectionOptions(1)]);
        $parent = $this->createOrderItemMock(10, ['getChildrenItems' => [$child]]);

        $result = $this->invokeAddMissingBundleParentQtys(
            [$parent, $child],
            [10 => 1, 11 => 1]
        );

        $this->assertSame([10 => 1, 11 => 1], $result);
    }

    public function testAddMissingBundleParentQtysSkipsParentWhenChildrenHaveNoQty()
    {
        $child = $this->createOrderItemMock(11, ['getProductOptions' => $this->selectionOptions(1)]);
        $parent = $this->createOrderItemMock(10, [
            'getQtyToInvoice' => 1.0,
            'getChildrenItems' => [$child],
        ]);

        $result = $this->invokeAddMissingBundleParentQtys([$parent, $child], [11 => 0]);

        $this->assertArrayNotHasKey(10, $result);
    }

    public function testAddMissingBundleParentQtysCapsParentQtyAtQtyToInvoice()
    {
        $child = $this->createOrderItemMock(11, ['getProductOptions' => $this->selectionOptions(1)]);
        $parent = $this->createOrderItemMock(10, [
            'getQtyToInvoice' => 1.0,
            'getChildrenItems' => [$child],
        ]);

        $result = $this->invokeAddMissingBundleParentQtys([$parent, $child], [11 => 3]);

        $this->assertSame(1.0, $result[10]);
    }

    public function testGetBundleSelectionQtyDefaultsToOneWithoutSelectionAttributes()
    {
        $child = $this->createOrderItemMock(11, ['getProductOptions' => []]);

        $instance = $this->getInstance(['jsonSerializer' => new Json()]);
        $result = $this->getMethod('getBundleSelectionQty', $instance)->invoke($instance, $child);

        $this->assertSame(1.0, $result);
    }

    public function testGetBundleSelectionQtyReadsQtyFromSerializedAttributes()
    {
        $child = $this->createOrderItemMock(11, ['getProductOptions' => $this->selectionOptions(3)]);

        $instance = $this->getInstance(['jsonSerializer' => new Json()]);
        $result = $this->getMethod('getBundleSelectionQty', $instance)->invoke($instance, $child);

        $this->assertSame(3.0, $result);
    }

    public function testGetQtysFromShipmentReturnsEmptyWhenOrderIsFullyShipped()
    {
        $orderMock = $this->createOrderMock(false, []);
        $shipmentMock = $this->createShipmentMock($orderMock, [[5, 2.0]]);

        $instance = $this->getInstance(['jsonSerializer' => new Json()]);

        $this->assertSame([], $instance->getQtysFromShipment($shipmentMock));
    }

    public function testGetQtysFromShipmentMapsShipmentItems()
    {
        $simpleItem = $this->createOrderItemMock(5);
        $orderMock = $this->createOrderMock(true, [$simpleItem]);
        $shipmentMock = $this->createShipmentMock($orderMock, [[5, 2.0]]);

        $instance = $this->getInstance(['jsonSerializer' => new Json()]);

        $this->assertSame([5 => 2.0], $instance->getQtysFromShipment($shipmentMock));
    }

    /**
     * Magento hardcodes qty 1 on the dummy parent shipment item of a ship-separately
     * bundle; the real parent qty must be derived from the children.
     */
    public function testGetQtysFromShipmentCorrectsShipSeparatelyBundleParentQty()
    {
        $childOne = $this->createOrderItemMock(11, ['getProductOptions' => $this->selectionOptions(2)]);
        $childTwo = $this->createOrderItemMock(12, ['getProductOptions' => $this->selectionOptions(1)]);
        $parent = $this->createOrderItemMock(10, [
            'isShipSeparately' => true,
            'getQtyToInvoice' => 2.0,
            'getChildrenItems' => [$childOne, $childTwo],
        ]);

        $orderMock = $this->createOrderMock(true, [$parent, $childOne, $childTwo]);
        $shipmentMock = $this->createShipmentMock($orderMock, [[10, 1.0], [11, 4.0], [12, 2.0]]);

        $instance = $this->getInstance(['jsonSerializer' => new Json()]);

        $this->assertSame(
            [10 => 2.0, 11 => 4.0, 12 => 2.0],
            $instance->getQtysFromShipment($shipmentMock)
        );
    }

    public function testGetQtysFromShipmentKeepsShipTogetherBundleParentQty()
    {
        $child = $this->createOrderItemMock(11, ['getProductOptions' => $this->selectionOptions(1)]);
        $parent = $this->createOrderItemMock(10, [
            'isShipSeparately' => false,
            'getChildrenItems' => [$child],
        ]);

        $orderMock = $this->createOrderMock(true, [$parent, $child]);
        $shipmentMock = $this->createShipmentMock($orderMock, [[10, 3.0], [11, 3.0]]);

        $instance = $this->getInstance(['jsonSerializer' => new Json()]);

        $this->assertSame(
            [10 => 3.0, 11 => 3.0],
            $instance->getQtysFromShipment($shipmentMock)
        );
    }

    /**
     * @return array
     */
    public static function missingTransactionKeyProvider(): array
    {
        return [
            'null' => [null],
            'empty string' => [''],
        ];
    }

    /**
     * Invoice after shipment on an order without a Buckaroo transaction key must raise the
     * handled module exception, not a TypeError from strlen(null) under strict_types.
     *
     * @dataProvider missingTransactionKeyProvider
     *
     * @param mixed $storedKey
     */
    public function testAddTransactionDataThrowsWhenNoTransactionKeyIsStored($storedKey)
    {
        $paymentMock = $this->getMockBuilder(Payment::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getAdditionalInformation', 'setTransactionId', 'setParentTransactionId'])
            ->getMock();
        $paymentMock->method('getAdditionalInformation')
            ->with(BuckarooAdapter::BUCKAROO_ORIGINAL_TRANSACTION_KEY_KEY)
            ->willReturn($storedKey);
        $paymentMock->expects($this->never())->method('setTransactionId');
        $paymentMock->expects($this->never())->method('setParentTransactionId');

        $instance = $this->getInstance();

        $this->expectException(BuckarooException::class);
        $this->expectExceptionMessage('There was no transaction ID found');

        $instance->addTransactionData($paymentMock);
    }

    public function testAddTransactionDataSetsCaptureAndParentTransactionFromStoredKey()
    {
        $paymentMock = $this->getMockBuilder(Payment::class)
            ->disableOriginalConstructor()
            ->onlyMethods([
                'getAdditionalInformation',
                'setAdditionalInformation',
                'setTransactionAdditionalInfo',
                'setTransactionId',
                'setParentTransactionId',
            ])
            ->getMock();
        $paymentMock->method('getAdditionalInformation')->willReturnMap([
            [BuckarooAdapter::BUCKAROO_ORIGINAL_TRANSACTION_KEY_KEY, 'ABC123'],
            [Transaction::RAW_DETAILS, ['ABC123' => ['status' => '190']]],
            ['buckaroo_capture_transaction_key', null],
        ]);
        $paymentMock->expects($this->once())
            ->method('setTransactionAdditionalInfo')
            ->with(Transaction::RAW_DETAILS, ['status' => '190']);
        $paymentMock->expects($this->once())->method('setTransactionId')->with('ABC123-capture');
        $paymentMock->expects($this->once())->method('setParentTransactionId')->with('ABC123');
        $paymentMock->expects($this->once())
            ->method('setAdditionalInformation')
            ->with(BuckarooAdapter::BUCKAROO_ORIGINAL_TRANSACTION_KEY_KEY, 'ABC123');

        $instance = $this->getInstance();

        $this->assertSame($paymentMock, $instance->addTransactionData($paymentMock));
    }

    /**
     * @param bool   $canShip
     * @param Item[] $orderItems
     *
     * @return Order
     */
    private function createOrderMock(bool $canShip, array $orderItems)
    {
        $orderMock = $this->getMockBuilder(Order::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getAllItems', 'canShip'])
            ->getMock();
        $orderMock->method('canShip')->willReturn($canShip);
        $orderMock->method('getAllItems')->willReturn($orderItems);

        return $orderMock;
    }

    /**
     * @param Order $orderMock
     * @param array $itemRows [[order_item_id, qty], ...]
     *
     * @return Shipment
     */
    private function createShipmentMock($orderMock, array $itemRows)
    {
        $shipmentItems = [];
        foreach ($itemRows as [$orderItemId, $qty]) {
            $shipmentItemMock = $this->getMockBuilder(ShipmentItem::class)
                ->disableOriginalConstructor()
                ->onlyMethods(['getOrderItemId', 'getQty'])
                ->getMock();
            $shipmentItemMock->method('getOrderItemId')->willReturn($orderItemId);
            $shipmentItemMock->method('getQty')->willReturn($qty);
            $shipmentItems[] = $shipmentItemMock;
        }

        $shipmentMock = $this->getMockBuilder(Shipment::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getOrder', 'getAllItems'])
            ->getMock();
        $shipmentMock->method('getOrder')->willReturn($orderMock);
        $shipmentMock->method('getAllItems')->willReturn($shipmentItems);

        return $shipmentMock;
    }

    /**
     * @param Item[] $orderItems
     * @param array  $invoiceItems
     *
     * @return array
     */
    private function invokeAddMissingBundleParentQtys(array $orderItems, array $invoiceItems): array
    {
        $orderMock = $this->getMockBuilder(Order::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getAllItems'])
            ->getMock();
        $orderMock->method('getAllItems')->willReturn($orderItems);

        $instance = $this->getInstance(['jsonSerializer' => new Json()]);

        return $this->getMethod('addMissingBundleParentQtys', $instance)
            ->invoke($instance, $orderMock, $invoiceItems);
    }

    /**
     * @param int   $itemId
     * @param array $methodReturns method => return value
     *
     * @return Item
     */
    private function createOrderItemMock(int $itemId, array $methodReturns = [])
    {
        $realMethods = ['getQtyToInvoice', 'getChildrenItems', 'getProductOptions', 'isShipSeparately'];

        $itemMock = $this->getMockBuilder(Item::class)
            ->disableOriginalConstructor()
            ->onlyMethods(array_merge(['getId'], $realMethods))
            ->getMock();
        $itemMock->method('getId')->willReturn($itemId);

        foreach ($methodReturns as $method => $returnValue) {
            $itemMock->method($method)->willReturn($returnValue);
        }

        return $itemMock;
    }

    /**
     * @param int $selectionQty
     *
     * @return array
     */
    private function selectionOptions(int $selectionQty): array
    {
        return [
            'bundle_selection_attributes' => json_encode([
                'option_id' => 1,
                'option_label' => 'Bundle Option',
                'qty' => $selectionQty,
            ]),
        ];
    }
}
