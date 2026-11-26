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

namespace Buckaroo\Magento2\Test\Unit\Model\Push;

use Buckaroo\Magento2\Helper\PaymentGroupTransaction;
use Buckaroo\Magento2\Logging\BuckarooLoggerInterface;
use Buckaroo\Magento2\Model\Push\DefaultProcessor;
use Buckaroo\Magento2\Model\Push\GroupTransactionPushProcessor;
use Buckaroo\Magento2\Service\Order\OrderCommentHistoryService;
use Buckaroo\Magento2\Service\Push\OrderRequestService;
use Buckaroo\Magento2\Test\Unit\Stubs\PushRequestInterfaceStub;
use Buckaroo\Magento2\Test\Unit\Stubs\QuoteStub;
use Magento\Framework\DataObject;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Model\Quote\Address;
use Magento\Quote\Model\QuoteFactory;
use Magento\Quote\Model\QuoteManagement;
use Magento\Quote\Model\ResourceModel\Quote as QuoteResource;
use Magento\Sales\Api\OrderManagementInterface;
use Magento\Sales\Model\Order;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 * @SuppressWarnings(PHPMD.TooManyFields)
 * @SuppressWarnings(PHPMD.TooManyPublicMethods)
 */
class GroupTransactionPushProcessorTest extends TestCase
{
    /** @var PaymentGroupTransaction&MockObject */
    private $groupTransaction;

    /** @var BuckarooLoggerInterface&MockObject */
    private $logger;

    /** @var OrderRequestService&MockObject */
    private $orderRequestService;

    /** @var OrderManagementInterface&MockObject */
    private $orderManagement;

    /** @var QuoteManagement&MockObject */
    private $quoteManagement;

    /** @var QuoteFactory&MockObject */
    private $quoteFactory;

    /** @var QuoteResource&MockObject */
    private $quoteResource;

    /** @var DefaultProcessor&MockObject */
    private $defaultProcessor;

    /** @var OrderCommentHistoryService&MockObject */
    private $commentHistory;

    /** @var CartRepositoryInterface&MockObject */
    private $cartRepository;

    protected function setUp(): void
    {
        $this->groupTransaction = $this->createMock(PaymentGroupTransaction::class);
        $this->logger = $this->createMock(BuckarooLoggerInterface::class);
        $this->orderRequestService = $this->createMock(OrderRequestService::class);
        $this->orderManagement = $this->createMock(OrderManagementInterface::class);
        $this->quoteManagement = $this->createMock(QuoteManagement::class);
        $this->quoteFactory = $this->createMock(QuoteFactory::class);
        $this->quoteResource = $this->createMock(QuoteResource::class);
        $this->defaultProcessor = $this->createMock(DefaultProcessor::class);
        $this->commentHistory = $this->createMock(OrderCommentHistoryService::class);
        $this->cartRepository = $this->createMock(CartRepositoryInterface::class);
    }

    public function testAFailedGroupTransactionCancelsTheOrderAndRecordsTheStatus(): void
    {
        $order = $this->createOrder(5, Order::STATE_NEW);
        $order->expects($this->once())->method('loadByIncrementId')->with('300000123')->willReturnSelf();
        $this->orderRequestService->method('getOrderByRequest')->willReturn($order);

        $this->orderManagement->expects($this->once())->method('cancel')->with(5);
        $this->commentHistory->expects($this->once())->method('add')
            ->with($order, $this->callback(static fn($comment) => (string)$comment === 'Giftcard has expired'));
        $this->groupTransaction->expects($this->once())->method('setGroupTransactionsStatus')->with('GROUP_KEY', '490');
        $this->groupTransaction->method('getGroupTransactionByTrxId')->with('GROUP_KEY')
            ->willReturn(new DataObject(['entity_id' => 9]));
        $this->groupTransaction->expects($this->once())->method('updateGroupTransaction')
            ->with(['entity_id' => 9, 'status' => '490']);
        $this->defaultProcessor->expects($this->never())->method('processPush');

        $processor = $this->createProcessor();

        $this->assertTrue($processor->processPush($this->createPushRequest('490')));
        $this->assertNull($processor->getRecordedTransactionStatus());
    }

    public function testAFailedGroupTransactionLeavesACancelledOrderAlone(): void
    {
        $order = $this->createOrder(5, Order::STATE_CANCELED);
        $order->method('loadByIncrementId')->willReturnSelf();
        $this->orderRequestService->method('getOrderByRequest')->willReturn($order);

        $this->orderManagement->expects($this->never())->method('cancel');
        $this->commentHistory->expects($this->never())->method('add');
        $this->groupTransaction->expects($this->once())->method('setGroupTransactionsStatus');
        $this->groupTransaction->method('getGroupTransactionByTrxId')->willReturn(new DataObject());
        $this->groupTransaction->expects($this->never())->method('updateGroupTransaction');

        $this->assertTrue($this->createProcessor()->processPush($this->createPushRequest('490')));
    }

    public function testAnErrorWhileHandlingAFailedGroupTransactionIsLoggedAndAcknowledged(): void
    {
        $order = $this->createOrder(5, Order::STATE_NEW);
        $order->method('loadByIncrementId')->willReturnSelf();
        $this->orderRequestService->method('getOrderByRequest')->willReturn($order);

        $this->orderManagement->method('cancel')->willThrowException(new \RuntimeException('cancel failed'));
        $this->groupTransaction->expects($this->never())->method('setGroupTransactionsStatus');
        $this->logger->expects($this->once())->method('addError')
            ->with($this->stringContains('cancel failed'));

        $this->assertTrue($this->createProcessor()->processPush($this->createPushRequest('490')));
    }

    public function testAFailedGroupTransactionWithoutOrderCreatesTheOrderFromTheQuoteAndCancelsIt(): void
    {
        $missing = $this->createOrder(null, Order::STATE_NEW);
        $missing->method('loadByIncrementId')->willReturnSelf();
        $this->orderRequestService->method('getOrderByRequest')->willReturn($missing);

        $billing = $this->createMock(Address::class);
        $billing->method('getEmail')->willReturn('jan@example.com');

        $quote = $this->createMock(QuoteStub::class);
        $quote->method('isEmpty')->willReturn(false);
        $quote->method('getCustomerEmail')->willReturn(null);
        $quote->method('getBillingAddress')->willReturn($billing);
        $quote->expects($this->once())->method('setCustomerEmail')->with('jan@example.com');
        $quote->expects($this->once())->method('setData')->with('buckaroo_idin_already_checked', true);
        $quote->expects($this->once())->method('setIsActive')->with(true);
        $quote->expects($this->once())->method('setOrigOrderId')->with(0);
        $quote->expects($this->once())->method('setReservedOrderId')->with(null);

        $this->quoteFactory->method('create')->willReturn($quote);
        $this->quoteResource->expects($this->once())->method('load')
            ->with($quote, '300000123', 'reserved_order_id');

        $created = $this->createOrder(6, Order::STATE_NEW);
        $this->quoteManagement->expects($this->once())->method('submit')->with($quote)->willReturn($created);
        $this->cartRepository->expects($this->once())->method('save')->with($quote);
        $this->orderManagement->expects($this->once())->method('cancel')->with(6);
        $this->commentHistory->expects($this->once())->method('add')->with($created);

        $this->assertTrue($this->createProcessor()->processPush($this->createPushRequest('490')));
    }

    public function testAFailedGroupTransactionWithoutOrderOrQuoteCancelsNothing(): void
    {
        $missing = $this->createOrder(null, Order::STATE_NEW);
        $missing->method('loadByIncrementId')->willReturnSelf();
        $this->orderRequestService->method('getOrderByRequest')->willReturn($missing);

        $quote = $this->createMock(QuoteStub::class);
        $quote->method('isEmpty')->willReturn(true);
        $this->quoteFactory->method('create')->willReturn($quote);
        $this->quoteManagement->expects($this->never())->method('submit');
        $this->orderManagement->expects($this->never())->method('cancel');
        $this->groupTransaction->expects($this->once())->method('setGroupTransactionsStatus');

        $this->assertTrue($this->createProcessor()->processPush($this->createPushRequest('490')));
    }

    public function testAGroupTransactionCancelledByTheCustomerCancelsTheOrder(): void
    {
        $order = $this->createOrder(5, Order::STATE_NEW);
        $order->method('loadByIncrementId')->with('300000123')->willReturnSelf();
        $this->orderRequestService->method('getOrderByRequest')->willReturn($order);

        $this->orderManagement->expects($this->once())->method('cancel')->with(5);
        $this->commentHistory->expects($this->once())->method('add')->with(
            $order,
            $this->callback(static fn($comment) => (string)$comment === 'Inline giftcard order was canceled')
        );
        $this->groupTransaction->expects($this->never())->method('setGroupTransactionsStatus');
        $this->defaultProcessor->expects($this->never())->method('processPush');

        $this->assertTrue($this->createProcessor()->processPush($this->createPushRequest('890')));
    }

    public function testACancelledGroupTransactionWithoutInvoiceNumberChangesNothing(): void
    {
        $order = $this->createOrder(5, Order::STATE_NEW);
        $order->expects($this->never())->method('loadByIncrementId');
        $this->orderRequestService->method('getOrderByRequest')->willReturn($order);
        $this->orderManagement->expects($this->never())->method('cancel');

        $this->assertTrue($this->createProcessor()->processPush($this->createPushRequest('890', null)));
    }

    public function testAnInformationalGroupTransactionPushIsOnlyAcknowledged(): void
    {
        $this->orderRequestService->method('getOrderByRequest')->willReturn($this->createOrder(5, Order::STATE_NEW));
        $this->orderManagement->expects($this->never())->method('cancel');
        $this->defaultProcessor->expects($this->never())->method('processPush');

        $this->assertTrue($this->createProcessor()->processPush($this->createPushRequest('792')));
    }

    /**
     * @param string      $state
     * @param array       $postData
     * @param string|null $related
     * @param mixed       $relatedPart
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('skippedSuccessProvider')]
    public function testASuccessfulPartIsNotProcessedWhenThePaymentAlreadyFailed(
        string $state,
        array $postData,
        ?string $related,
        $relatedPart
    ): void {
        $this->orderRequestService->method('getOrderByRequest')->willReturn($this->createOrder(5, $state));
        $this->groupTransaction->method('getGroupTransactionByTrxId')->willReturn($relatedPart);
        $this->defaultProcessor->expects($this->never())->method('processPush');
        $this->defaultProcessor->method('getRecordedTransactionStatus')->willReturn(['transaction_key' => 'EARLIER']);

        $processor = $this->createProcessor();

        $this->assertTrue($processor->processPush($this->createPushRequest('190', '300000123', $related, $postData)));
        $this->assertNull($processor->getRecordedTransactionStatus());
    }

    public static function skippedSuccessProvider(): array
    {
        return [
            'cancelled order, giftcard transaction' => [Order::STATE_CANCELED, [['transaction_type', ['V202', 'V203', 'V204']]], null, null],
            'cancelled order' => [Order::STATE_CANCELED, [], null, null],
            'related part failed' => [Order::STATE_NEW, [], 'RELATED_KEY', new DataObject(['entity_id' => 3, 'status' => '490'])],
            'related part rejected' => [Order::STATE_NEW, [], 'RELATED_KEY', new DataObject(['entity_id' => 3, 'status' => '690'])],
            'related part cancelled by merchant' => [Order::STATE_NEW, [], 'RELATED_KEY', new DataObject(['entity_id' => 3, 'status' => '891'])],
        ];
    }

    /**
     * @param string|null $related
     * @param mixed       $relatedPart
     * @param int|null    $orderId
     * @param array       $postData
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('handedOnProvider')]
    public function testASuccessfulPartIsHandedToTheDefaultProcessor(
        ?string $related,
        $relatedPart,
        ?int $orderId = 5,
        array $postData = []
    ): void {
        $this->orderRequestService->method('getOrderByRequest')->willReturn($this->createOrder($orderId, Order::STATE_NEW));
        $this->groupTransaction->method('getGroupTransactionByTrxId')->willReturn($relatedPart);
        $pushRequest = $this->createPushRequest('190', '300000123', $related, $postData);
        $this->defaultProcessor->expects($this->once())->method('processPush')->with($pushRequest)->willReturn(false);
        $this->defaultProcessor->method('getRecordedTransactionStatus')->willReturn(['transaction_key' => 'GROUP_KEY']);

        $processor = $this->createProcessor();

        $this->assertFalse($processor->processPush($pushRequest));
        $this->assertSame(['transaction_key' => 'GROUP_KEY'], $processor->getRecordedTransactionStatus());
    }

    public static function handedOnProvider(): array
    {
        return [
            'no related part' => [null, new DataObject(['entity_id' => 3, 'status' => '490'])],
            'giftcard transaction on an open order' => [null, null, 5, [['transaction_type', ['V202', 'V203', 'V204']]]],
            'order not saved yet' => [null, null, null],
            'related part paid' => ['RELATED_KEY', new DataObject(['entity_id' => 3, 'status' => '190'])],
            'related part unknown' => ['RELATED_KEY', new DataObject(['status' => '490'])],
        ];
    }

    private function createProcessor(): GroupTransactionPushProcessor
    {
        return new GroupTransactionPushProcessor(
            $this->groupTransaction,
            $this->logger,
            $this->orderRequestService,
            $this->orderManagement,
            $this->quoteManagement,
            $this->quoteFactory,
            $this->quoteResource,
            $this->defaultProcessor,
            $this->commentHistory,
            $this->cartRepository
        );
    }

    /**
     * @param int|null $entityId
     * @param string   $state
     *
     * @return Order&MockObject
     */
    private function createOrder(?int $entityId, string $state)
    {
        $order = $this->createMock(Order::class);
        $order->method('getEntityId')->willReturn($entityId);
        $order->method('getId')->willReturn($entityId);
        $order->method('getState')->willReturn($state);
        $order->method('getIncrementId')->willReturn('300000123');

        return $order;
    }

    /**
     * @param string      $statusCode
     * @param string|null $invoiceNumber
     * @param string|null $related
     * @param array       $postData [name, value] pairs hasPostData answers true for
     *
     * @return PushRequestInterfaceStub&MockObject
     */
    private function createPushRequest(
        string $statusCode,
        ?string $invoiceNumber = '300000123',
        ?string $related = null,
        array $postData = []
    ) {
        $pushRequest = $this->createMock(PushRequestInterfaceStub::class);
        $pushRequest->method('getStatusCode')->willReturn($statusCode);
        $pushRequest->method('getInvoiceNumber')->willReturn($invoiceNumber);
        $pushRequest->method('getTransactions')->willReturn('GROUP_KEY');
        $pushRequest->method('getRelatedtransactionPartialpayment')->willReturn($related);
        $pushRequest->method('hasPostData')->willReturnCallback(
            static fn($name, $value) => in_array([$name, $value], $postData, true)
        );

        return $pushRequest;
    }
}
