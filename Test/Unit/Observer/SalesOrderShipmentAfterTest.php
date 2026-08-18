<?php
/**
 * NOTICE OF LICENSE
 *
 * This source file is subject to the MIT License
 * It is available through the world-wide-web at this URL:
 * https://tldrlegal.com/license/mit-license
 * If you are unable to obtain it through the world-wide-web, please email
 * to support@buckaroo.nl, so we can send you a copy immediately.
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
declare(strict_types=1);

namespace Buckaroo\Magento2\Test\Unit\Observer;

/**
 * BTI-1312 — Invoice::register() writes qty_invoiced and the *_invoiced amounts onto the order
 * items BEFORE it runs the online capture. When the capture throws, the invoice entity is never
 * persisted while the dirty order items are saved later in the same request, leaving an order
 * that reports invoiced items with no invoice entity: it is marked complete/closed, the capture
 * is never retried, and the reservation expires. The failure path must reverse those item writes
 * (the 1.x plugin did; the 2.x rewrite lost the rollback).
 */
class SalesOrderShipmentAfterTest extends \Buckaroo\Magento2\Test\BaseTest
{
    protected $instanceClass = 'Buckaroo\Magento2\Observer\SalesOrderShipmentAfter';

    public function testRollbackCancelsEveryRegisteredInvoiceItem(): void
    {
        $instance = $this->getInstance();

        // Invoice\Item::cancel() is Magento's exact inverse of Invoice\Item::register(): it
        // subtracts only this invoice's quantities, so previously persisted invoices stay intact.
        $firstItem = $this->getFakeMock('Magento\Sales\Model\Order\Invoice\Item')->getMock();
        $firstItem->expects($this->once())->method('cancel');

        $secondItem = $this->getFakeMock('Magento\Sales\Model\Order\Invoice\Item')->getMock();
        $secondItem->expects($this->once())->method('cancel');

        $invoiceMock = $this->getFakeMock('Magento\Sales\Model\Order\Invoice')->getMock();
        $invoiceMock->method('getAllItems')->willReturn([$firstItem, $secondItem]);

        $this->invokeArgs('rollBackRegisteredInvoiceValues', [$invoiceMock], $instance);
    }

    public function testRollbackIsANoOpForAnInvoiceWithoutItems(): void
    {
        $instance = $this->getInstance();

        $invoiceMock = $this->getFakeMock('Magento\Sales\Model\Order\Invoice')->getMock();
        $invoiceMock->expects($this->once())->method('getAllItems')->willReturn([]);

        $this->invokeArgs('rollBackRegisteredInvoiceValues', [$invoiceMock], $instance);
    }
}
