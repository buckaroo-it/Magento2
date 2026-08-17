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

use Buckaroo\Magento2\Logging\BuckarooLoggerInterface;
use Buckaroo\Magento2\Model\ConfigProvider\Factory as ConfigProviderFactory;
use Buckaroo\Magento2\Model\Service\CreateInvoice;
use Buckaroo\Magento2\Observer\SalesOrderShipmentAfter;
use Magento\Framework\DB\TransactionFactory;
use Magento\Sales\Api\OrderItemRepositoryInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order\Invoice;
use Magento\Sales\Model\Order\Invoice\Item as InvoiceItem;
use Magento\Sales\Model\Service\InvoiceService;
use PHPUnit\Framework\TestCase;

/**
 * BTI-1312 — Invoice::register() writes qty_invoiced and the *_invoiced amounts onto the
 * order items BEFORE it runs the online capture. When the capture throws, the invoice entity
 * is never persisted while the dirty order items are saved later in the same request, leaving
 * an order that reports invoiced items with no invoice entity: it is marked complete/closed,
 * the capture is never retried, and the reservation expires. The failure path must reverse
 * those item writes (the 1.x plugin did; the 2.x rewrite lost the rollback).
 */
class SalesOrderShipmentAfterTest extends TestCase
{
    /**
     * @var SalesOrderShipmentAfter
     */
    private $observer;

    /**
     * @inheritDoc
     */
    protected function setUp(): void
    {
        $this->observer = new SalesOrderShipmentAfter(
            $this->createMock(InvoiceService::class),
            $this->createMock(TransactionFactory::class),
            $this->createMock(ConfigProviderFactory::class),
            $this->createMock(BuckarooLoggerInterface::class),
            $this->createMock(CreateInvoice::class),
            $this->createMock(OrderRepositoryInterface::class),
            $this->createMock(OrderItemRepositoryInterface::class)
        );
    }

    public function testRollbackCancelsEveryRegisteredInvoiceItem()
    {
        // Arrange: an invoice whose items were registered onto the order items
        $firstItem = $this->createMock(InvoiceItem::class);
        $secondItem = $this->createMock(InvoiceItem::class);

        // Assert: Invoice\Item::cancel() is Magento's exact inverse of register()
        $firstItem->expects($this->once())->method('cancel');
        $secondItem->expects($this->once())->method('cancel');

        $invoice = $this->createMock(Invoice::class);
        $invoice->method('getAllItems')->willReturn([$firstItem, $secondItem]);

        // Act
        $this->invokeRollback($invoice);
    }

    public function testRollbackDoesNothingWhenInvoiceWasNeverPrepared()
    {
        // Act + Assert: no error when the exception occurred before prepareInvoice returned
        $this->invokeRollback(null);
        $this->addToAssertionCount(1);
    }

    /**
     * @param Invoice|null $invoice
     *
     * @return void
     * @throws \ReflectionException
     */
    private function invokeRollback(?Invoice $invoice): void
    {
        $method = new \ReflectionMethod($this->observer, 'rollBackRegisteredInvoiceValues');
        $method->setAccessible(true);
        $method->invoke($this->observer, $invoice);
    }
}
