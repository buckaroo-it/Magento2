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
declare(strict_types=1);

namespace Buckaroo\Magento2\Test\Unit\Service\Push;

use Buckaroo\Magento2\Exception as BuckarooException;
use Buckaroo\Magento2\Logging\BuckarooLoggerInterface;
use Buckaroo\Magento2\Model\RequestPush\HttppostPushRequest;
use Buckaroo\Magento2\Model\Validator\Push as ValidatorPush;
use Buckaroo\Magento2\Service\Order\OrderCommentHistoryService;
use Buckaroo\Magento2\Service\Push\KlarnaKpOrderService;
use Buckaroo\Magento2\Service\Push\KlarnaMorOrderService;
use Buckaroo\Magento2\Service\Push\OrderEmailService;
use Buckaroo\Magento2\Service\Push\OrderRequestService;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Invoice;
use Magento\Sales\Model\Order\Payment\Transaction;
use Magento\Sales\Model\OrderFactory;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 * @SuppressWarnings(PHPMD.TooManyPublicMethods)
 */
class OrderRequestServiceTest extends TestCase
{
    /**
     * @var Order|MockObject
     */
    private $order;

    /**
     * @var BuckarooLoggerInterface|MockObject
     */
    private $logger;

    /**
     * @var Transaction|MockObject
     */
    private $transaction;

    /**
     * @var OrderEmailService|MockObject
     */
    private $orderEmailService;

    /**
     * @var KlarnaKpOrderService|MockObject
     */
    private $klarnaKpOrderService;

    /**
     * @var KlarnaMorOrderService|MockObject
     */
    private $klarnaMorOrderService;

    /**
     * @var OrderCommentHistoryService|MockObject
     */
    private $commentHistory;

    /**
     * @var OrderRepositoryInterface|MockObject
     */
    private $orderRepository;

    /**
     * @var OrderFactory|MockObject
     */
    private $orderFactory;

    /**
     * @var OrderRequestService
     */
    private OrderRequestService $service;

    protected function setUp(): void
    {
        $this->order = $this->createMock(Order::class);
        $this->logger = $this->createMock(BuckarooLoggerInterface::class);
        $this->transaction = $this->createMock(Transaction::class);
        $this->orderEmailService = $this->createMock(OrderEmailService::class);
        $this->klarnaKpOrderService = $this->createMock(KlarnaKpOrderService::class);
        $this->klarnaMorOrderService = $this->createMock(KlarnaMorOrderService::class);
        $this->commentHistory = $this->createMock(OrderCommentHistoryService::class);
        $this->orderRepository = $this->createMock(OrderRepositoryInterface::class);
        $this->orderFactory = $this->createMock(OrderFactory::class);

        $this->service = new OrderRequestService(
            $this->order,
            $this->logger,
            $this->transaction,
            $this->orderEmailService,
            $this->klarnaKpOrderService,
            $this->klarnaMorOrderService,
            $this->commentHistory,
            $this->orderRepository,
            $this->orderFactory
        );
    }

    private function push(array $data): HttppostPushRequest
    {
        return new HttppostPushRequest($data, $this->createMock(ValidatorPush::class));
    }

    private function orderWithId(?int $orderId): Order
    {
        $order = $this->createMock(Order::class);
        $order->method('getId')->willReturn($orderId);
        return $order;
    }

    public function testAlreadyLoadedOrderIsReturnedWithoutLookup(): void
    {
        $this->order->method('getId')->willReturn(5);
        $this->order->expects($this->never())->method('loadByIncrementId');
        $this->transaction->expects($this->never())->method('load');

        $this->assertSame($this->order, $this->service->getOrderByRequest($this->push(['brq_ordernumber' => 'X'])));
    }

    public function testOrderNumberTakesPrecedenceOverInvoiceNumber(): void
    {
        $this->order->method('getId')->willReturnOnConsecutiveCalls(null, 7);
        $this->order->expects($this->once())->method('loadByIncrementId')->with('ORD-2');
        $this->transaction->expects($this->never())->method('load');

        $result = $this->service->getOrderByRequest(
            $this->push(['brq_invoicenumber' => 'INV-1', 'brq_ordernumber' => 'ORD-2'])
        );

        $this->assertSame($this->order, $result);
    }

    public function testInvoiceNumberIsUsedWhenOrderNumberMissing(): void
    {
        $this->order->method('getId')->willReturnOnConsecutiveCalls(null, 7);
        $this->order->expects($this->once())->method('loadByIncrementId')->with('INV-1');

        $this->service->getOrderByRequest($this->push(['brq_invoicenumber' => 'INV-1']));
    }

    public function testFallsBackToOrderOfPaymentTransaction(): void
    {
        $trxOrder = $this->orderWithId(11);
        $this->order->method('getId')->willReturn(null);
        $this->order->expects($this->once())->method('loadByIncrementId')->with('');
        $this->transaction->expects($this->once())->method('load')->with('TRX-1', 'txn_id');
        $this->transaction->method('getOrder')->willReturn($trxOrder);
        $this->klarnaKpOrderService->expects($this->never())->method('getOrderByReservationNumber');
        $this->klarnaMorOrderService->expects($this->never())->method('getOrderByDataRequestKey');

        $result = $this->service->getOrderByRequest($this->push(['brq_transactions' => 'TRX-1']));

        $this->assertSame($trxOrder, $result);
        $this->assertSame($trxOrder, $this->service->order);
    }

    public function testDataRequestKeyOverridesTransactionsKey(): void
    {
        $this->order->method('getId')->willReturn(null);
        $this->transaction->expects($this->once())->method('load')->with('DR-1', 'txn_id');
        $this->transaction->method('getOrder')->willReturn($this->orderWithId(3));

        $this->service->getOrderByRequest($this->push(['brq_transactions' => 'TRX-1', 'brq_datarequest' => 'DR-1']));
    }

    public function testRefundTransactionKeyOverridesOtherKeys(): void
    {
        $this->order->method('getId')->willReturn(null);
        $this->transaction->expects($this->once())->method('load')->with('REF-1', 'txn_id');
        $this->transaction->method('getOrder')->willReturn($this->orderWithId(3));

        $this->service->getOrderByRequest($this->push([
            'brq_transactions' => 'TRX-1',
            'brq_datarequest' => 'DR-1',
            'brq_relatedtransaction_refund' => 'REF-1',
        ]));
    }

    public function testFallsBackToKlarnaReservationNumberWhenTransactionHasNoOrder(): void
    {
        $kpOrder = $this->orderWithId(21);
        $this->order->method('getId')->willReturn(null);
        $this->transaction->method('getOrder')->willReturn($this->orderWithId(null));
        $this->klarnaKpOrderService->expects($this->once())
            ->method('getOrderByReservationNumber')
            ->with('RES-9')
            ->willReturn($kpOrder);
        $this->klarnaMorOrderService->expects($this->never())->method('getOrderByDataRequestKey');

        $result = $this->service->getOrderByRequest($this->push([
            'brq_transactions' => 'TRX-1',
            'brq_service_klarnakp_reservationnumber' => 'RES-9',
        ]));

        $this->assertSame($kpOrder, $result);
        $this->assertSame($kpOrder, $this->service->order);
    }

    public function testFallsBackToPendingKlarnaMorPushKey(): void
    {
        $morOrder = $this->orderWithId(31);
        $this->order->method('getId')->willReturn(null);
        $this->transaction->method('getOrder')->willReturn(null);
        $this->klarnaKpOrderService->expects($this->never())->method('getOrderByReservationNumber');
        $this->klarnaMorOrderService->expects($this->once())
            ->method('getOrderByDataRequestKey')->with('DR-1')->willReturn(null);
        $this->klarnaMorOrderService->expects($this->once())
            ->method('getOrderByPendingDataRequestPushKey')->with('DR-1')->willReturn($morOrder);

        $result = $this->service->getOrderByRequest($this->push(['brq_datarequest' => 'DR-1']));

        $this->assertSame($morOrder, $result);
        $this->assertSame($morOrder, $this->service->order);
    }

    public function testKlarnaMorLookupTriesRelatedDataRequestKeyAfterOwnKey(): void
    {
        $morOrder = $this->orderWithId(32);
        $this->order->method('getId')->willReturn(null);
        $this->transaction->method('getOrder')->willReturn(null);
        $keys = [];
        $this->klarnaMorOrderService->expects($this->exactly(2))
            ->method('getOrderByDataRequestKey')
            ->willReturnCallback(function (string $key) use (&$keys, $morOrder) {
                $keys[] = $key;
                return $key === 'REL-1' ? $morOrder : null;
            });
        $this->klarnaMorOrderService->expects($this->once())
            ->method('getOrderByPendingDataRequestPushKey')->with('DR-1')->willReturn(null);

        $result = $this->service->getOrderByRequest(
            $this->push(['brq_datarequest' => 'DR-1', 'brq_datarequest_' => 'REL-1'])
        );

        $this->assertSame(['DR-1', 'REL-1'], $keys);
        $this->assertSame($morOrder, $result);
    }

    public function testDuplicateKlarnaMorKeyIsLookedUpOnce(): void
    {
        $this->order->method('getId')->willReturn(null);
        $this->transaction->method('getOrder')->willReturn(null);
        $this->klarnaMorOrderService->expects($this->once())
            ->method('getOrderByDataRequestKey')->with('DR-1')->willReturn(null);
        $this->klarnaMorOrderService->expects($this->once())
            ->method('getOrderByPendingDataRequestPushKey')->with('DR-1')->willReturn(null);
        $this->expectException(BuckarooException::class);

        $this->service->getOrderByRequest($this->push(['brq_datarequest' => 'DR-1', 'brq_datarequest_' => 'DR-1']));
    }

    public function testThrowsWhenNoLookupFindsAnOrder(): void
    {
        $this->order->method('getId')->willReturn(null);
        $this->transaction->method('getOrder')->willReturn($this->orderWithId(null));
        $this->klarnaKpOrderService->method('getOrderByReservationNumber')->willReturn(null);
        $this->klarnaMorOrderService->method('getOrderByDataRequestKey')->willReturn(null);
        $this->klarnaMorOrderService->method('getOrderByPendingDataRequestPushKey')->willReturn(null);

        $this->expectException(BuckarooException::class);
        $this->expectExceptionMessage('There was no order found by transaction Id');

        $this->service->getOrderByRequest($this->push([
            'brq_transactions' => 'TRX-1',
            'brq_datarequest' => 'DR-1',
            'brq_service_klarnakp_reservationnumber' => 'RES-9',
        ]));
    }

    public function testThrowsWhenKlarnaMorOrderHasNoId(): void
    {
        $this->order->method('getId')->willReturn(null);
        $this->transaction->method('getOrder')->willReturn(null);
        $this->klarnaMorOrderService->method('getOrderByDataRequestKey')->willReturn($this->orderWithId(null));

        $this->expectException(BuckarooException::class);

        $this->service->getOrderByRequest($this->push(['brq_datarequest' => 'DR-1']));
    }

    public function testReloadOrderReadsFreshCopyById(): void
    {
        $fresh = $this->createMock(Order::class);
        $created = $this->createMock(Order::class);
        $created->expects($this->once())->method('load')->with(42)->willReturn($fresh);
        $this->order->method('getId')->willReturn(42);
        $this->orderFactory->expects($this->once())->method('create')->willReturn($created);

        $this->assertSame($fresh, $this->service->reloadOrder());
        $this->assertSame($fresh, $this->service->order);
    }

    public function testReloadOrderKeepsUnsavedOrder(): void
    {
        $this->order->method('getId')->willReturn(null);
        $this->orderFactory->expects($this->never())->method('create');

        $this->assertSame($this->order, $this->service->reloadOrder());
    }

    public function testNotificationNoteIsAddedToOrder(): void
    {
        $this->commentHistory->expects($this->once())
            ->method('add')
            ->with($this->order, 'Buckaroo attempted to update this order, but failed: amount mismatch');
        $this->logger->expects($this->never())->method('addError');

        $this->service->setOrderNotificationNote('amount mismatch');
    }

    public function testNotificationNoteFailureIsLoggedNotThrown(): void
    {
        $this->commentHistory->method('add')->willThrowException(new \RuntimeException('db down'));
        $this->logger->expects($this->once())
            ->method('addError')
            ->with($this->stringContains('db down'));

        $this->service->setOrderNotificationNote('amount mismatch');
    }

    public function testUpdateOrderStatusSetsStateCommentsAndSavesOnce(): void
    {
        $calls = [];
        $this->order->expects($this->once())->method('setState')->with('processing')
            ->willReturnCallback(function () use (&$calls) {
                $calls[] = 'state';
                return $this->order;
            });
        $this->order->expects($this->once())->method('addCommentToStatusHistory')->with('Paid', 'buckaroo_paid')
            ->willReturnCallback(function () use (&$calls) {
                $calls[] = 'comment';
                return $this->order;
            });
        $this->orderRepository->expects($this->once())->method('save')->with($this->order)
            ->willReturnCallback(function () use (&$calls) {
                $calls[] = 'save';
                return $this->order;
            });

        $this->service->updateOrderStatus('processing', 'buckaroo_paid', 'Paid');

        $this->assertSame(['state', 'comment', 'save'], $calls);
    }

    public function testSendOrderEmailDelegatesAndReturnsResult(): void
    {
        $order = $this->createMock(Order::class);
        $this->orderEmailService->expects($this->once())
            ->method('sendOrderEmail')->with($order, true)->willReturn(false);

        $this->assertFalse($this->service->sendOrderEmail($order, true));
    }

    public function testSendInvoiceEmailDelegatesAndReturnsResult(): void
    {
        $invoice = $this->createMock(Invoice::class);
        $this->orderEmailService->expects($this->once())
            ->method('sendInvoiceEmail')->with($invoice, true)->willReturn(true);

        $this->assertTrue($this->service->sendInvoiceEmail($invoice, true));
    }

    public function testEmailsAreQueuedByDefault(): void
    {
        $order = $this->createMock(Order::class);
        $invoice = $this->createMock(Invoice::class);
        $this->orderEmailService->expects($this->once())->method('sendOrderEmail')->with($order, false);
        $this->orderEmailService->expects($this->once())->method('sendInvoiceEmail')->with($invoice, false);

        $this->service->sendOrderEmail($order);
        $this->service->sendInvoiceEmail($invoice);
    }

    public function testLoadOrderReloadsTheCurrentOrderByIncrementId(): void
    {
        $this->order->method('getId')->willReturn(9);
        $this->order->method('getIncrementId')->willReturn('000000124');
        $this->order->expects($this->once())->method('loadByIncrementId')->with('000000124');

        $this->service->loadOrder();
    }

    public function testSaveAndReloadOrderSavesThenReloadsByIncrementId(): void
    {
        $calls = [];
        $this->order->method('getId')->willReturn(9);
        $this->order->method('getIncrementId')->willReturn('000000123');
        $this->orderRepository->expects($this->once())->method('save')->with($this->order)
            ->willReturnCallback(function () use (&$calls) {
                $calls[] = 'save';
                return $this->order;
            });
        $this->order->expects($this->once())->method('loadByIncrementId')->with('000000123')
            ->willReturnCallback(function () use (&$calls) {
                $calls[] = 'load';
                return $this->order;
            });

        $this->service->saveAndReloadOrder();

        $this->assertSame(['save', 'load'], $calls);
    }
}
