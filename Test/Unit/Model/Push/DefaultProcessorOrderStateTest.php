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

use Buckaroo\Magento2\Exception as BuckarooException;
use Buckaroo\Magento2\Helper\Data;
use Buckaroo\Magento2\Model\ConfigProvider\Account;
use Buckaroo\Magento2\Model\Method\BuckarooAdapter;
use Buckaroo\Magento2\Model\Push\DefaultProcessor;
use Buckaroo\Magento2\Model\Push\PushTransactionType;
use Buckaroo\Magento2\Model\RequestPush\HttppostPushRequest;
use Buckaroo\Magento2\Model\Service\GiftCardRefundService;
use Buckaroo\Magento2\Model\Validator\Push as PushValidator;
use Buckaroo\Magento2\Service\Order\CancelGuard;
use Buckaroo\Magento2\Service\Order\Uncancel;
use Buckaroo\Magento2\Service\Push\OrderRequestService;
use Buckaroo\Magento2\Test\Unit\Stubs\OrderStub;
use Magento\Framework\Api\SearchCriteria;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Sales\Api\Data\TransactionSearchResultInterface;
use Magento\Sales\Api\OrderManagementInterface;
use Magento\Sales\Api\OrderPaymentRepositoryInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Api\TransactionRepositoryInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Payment;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;

/**
 * Which pushes may change an order, how a cancelled order comes back to life, how repeated pushes are
 * recognised and what a failed payment does to the order.
 *
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 * @SuppressWarnings(PHPMD.TooManyFields)
 * @SuppressWarnings(PHPMD.TooManyPublicMethods)
 * @SuppressWarnings(PHPMD.ExcessiveClassComplexity)
 */
class DefaultProcessorOrderStateTest extends \Buckaroo\Magento2\Test\BaseTest
{
    protected $instanceClass = DefaultProcessor::class;

    private const SUCCESS = 'BUCKAROO_MAGENTO2_STATUSCODE_SUCCESS';

    /** @var OrderRequestService&MockObject */
    private $orderRequestService;

    /** @var PushTransactionType&MockObject */
    private $pushTransactionType;

    /** @var Data&MockObject */
    private $helper;

    /** @var Account&MockObject */
    private $configAccount;

    /** @var Uncancel&MockObject */
    private $uncancel;

    /** @var GiftCardRefundService&MockObject */
    private $giftCardRefund;

    /** @var OrderRepositoryInterface&MockObject */
    private $orderRepository;

    /** @var OrderPaymentRepositoryInterface&MockObject */
    private $paymentRepository;

    /** @var TransactionRepositoryInterface&MockObject */
    private $transactionRepository;

    /** @var SearchCriteriaBuilder&MockObject */
    private $searchCriteriaBuilder;

    /** @var OrderManagementInterface&MockObject */
    private $orderManagement;

    public function setUp(): void
    {
        parent::setUp();

        $this->orderRequestService = $this->createMock(OrderRequestService::class);
        $this->pushTransactionType = $this->createMock(PushTransactionType::class);
        $this->helper = $this->createMock(Data::class);
        $this->configAccount = $this->createMock(Account::class);
        $this->uncancel = $this->createMock(Uncancel::class);
        $this->giftCardRefund = $this->createMock(GiftCardRefundService::class);
        $this->orderRepository = $this->createMock(OrderRepositoryInterface::class);
        $this->paymentRepository = $this->createMock(OrderPaymentRepositoryInterface::class);
        $this->transactionRepository = $this->createMock(TransactionRepositoryInterface::class);
        $this->searchCriteriaBuilder = $this->createMock(SearchCriteriaBuilder::class);
        $this->orderManagement = $this->createMock(OrderManagementInterface::class);

        BuckarooAdapter::$requestOnVoid = true;
    }

    protected function tearDown(): void
    {
        BuckarooAdapter::$requestOnVoid = true;
        parent::tearDown();
    }

    public function getInstance(array $args = [])
    {
        return parent::getInstance($args + [
            'orderRequestService' => $this->orderRequestService,
            'pushTransactionType' => $this->pushTransactionType,
            'helper' => $this->helper,
            'configAccount' => $this->configAccount,
            'uncancelService' => $this->uncancel,
            'giftCardRefundService' => $this->giftCardRefund,
            'orderRepository' => $this->orderRepository,
            'paymentRepository' => $this->paymentRepository,
            'transactionRepository' => $this->transactionRepository,
            'searchCriteriaBuilder' => $this->searchCriteriaBuilder,
            'orderManagement' => $this->orderManagement,
        ]);
    }

    /**
     * @param string $state
     * @param bool   $expected
     */
    #[DataProvider('orderStateProvider')]
    public function testOnlyOpenOrdersReceiveUpdates(string $state, bool $expected): void
    {
        $instance = $this->processorFor($this->push([]), $this->order($state), $this->payment([]));

        $this->assertSame($expected, $this->invoke('canUpdateOrderStatus', $instance));
    }

    public static function orderStateProvider(): array
    {
        return [
            'new' => [Order::STATE_NEW, true],
            'pending payment' => [Order::STATE_PENDING_PAYMENT, true],
            'processing' => [Order::STATE_PROCESSING, true],
            'complete' => [Order::STATE_COMPLETE, false],
            'closed' => [Order::STATE_CLOSED, false],
            'on hold' => [Order::STATE_HOLDED, false],
        ];
    }

    /**
     * @param array  $push
     * @param array  $paymentInfo
     * @param string $statusKey
     * @param string $serviceAction
     */
    #[DataProvider('notReactivatedProvider')]
    public function testACancelledOrderStaysCancelled(
        array $push,
        array $paymentInfo,
        string $statusKey,
        string $serviceAction
    ): void {
        $this->pushTransactionType->method('getStatusKey')->willReturn($statusKey);
        $this->pushTransactionType->method('getServiceAction')->willReturn($serviceAction);
        $this->uncancel->expects($this->never())->method('execute');
        $this->orderRepository->expects($this->never())->method('save');

        $instance = $this->processorFor(
            $this->push($push + ['brq_statuscode' => '190', 'brq_transactions' => 'AUTH_KEY', 'add_initiated_by_magento' => '1']),
            $this->order(Order::STATE_CANCELED),
            $this->payment($paymentInfo + ['buckaroo_original_transaction_key' => 'AUTH_KEY'])
        );

        $this->assertFalse($this->invoke('canUpdateOrderStatus', $instance));
    }

    public static function notReactivatedProvider(): array
    {
        return [
            'not a successful payment' => [[], [], 'BUCKAROO_MAGENTO2_STATUSCODE_FAILED', ''],
            'already reactivated once' => [[], ['buckaroo_order_reactivated' => true], self::SUCCESS, ''],
            'cancelled by the merchant' => [[], ['buckaroo_manually_canceled' => true], self::SUCCESS, ''],
            'part of a split payment' => [['brq_relatedtransaction_partialpayment' => 'GROUP'], [], self::SUCCESS, ''],
            'cancel authorize push' => [[], [], self::SUCCESS, 'cancelauthorize'],
            'cancel authorize from Magento' => [['add_service_action_from_magento' => 'cancelauthorize'], [], self::SUCCESS, ''],
            'cancel reservation from Magento' => [['add_service_action_from_magento' => 'cancelreservation'], [], self::SUCCESS, ''],
            'cancel authorize transaction type' => [['brq_transaction_type' => 'I014'], [], self::SUCCESS, ''],
            'credited amount' => [['brq_amount_credit' => '10.00'], [], self::SUCCESS, 'pay'],
            'started outside Magento' => [['add_initiated_by_magento' => null], [], self::SUCCESS, ''],
            'not flagged as started in Magento' => [['add_initiated_by_magento' => '0'], [], self::SUCCESS, ''],
            'another transaction' => [['brq_transactions' => 'OTHER_KEY'], [], self::SUCCESS, ''],
        ];
    }

    public function testAPaymentCompletedAfterCancellationReactivatesTheOrder(): void
    {
        $this->pushTransactionType->method('getStatusKey')->willReturn(self::SUCCESS);
        $order = $this->order(Order::STATE_CANCELED);
        $order->method('getBuckarooReservationNumber')->willReturn('RESERVATION');
        $payment = $this->payment(['buckaroo_original_transaction_key' => 'AUTH_KEY']);
        $set = $this->recordAdditionalInformation($payment);
        $payment->expects($this->once())->method('setLastTransId')->with(null);
        $cleared = [];
        $payment->method('setData')->willReturnCallback(
            static function ($key, $value) use (&$cleared, $payment) {
                $cleared[$key] = $value;
                return $payment;
            }
        );

        $this->uncancel->expects($this->once())->method('execute')->with(
            $order,
            'Order reactivated: Payment completed after cancellation (Push notification received). '
            . 'Transaction ID: "AUTH_KEY"'
        );

        $methodInstance = $this->createMock(BuckarooAdapter::class);
        $methodInstance->method('getConfigData')->with('create_invoice_after_shipment')->willReturn('1');
        $payment->method('getMethodInstance')->willReturn($methodInstance);

        $searchCriteria = $this->createMock(SearchCriteria::class);
        $this->searchCriteriaBuilder->expects($this->once())->method('addFilter')->with('order_id', 5)->willReturnSelf();
        $this->searchCriteriaBuilder->method('create')->willReturn($searchCriteria);
        $first = $this->createMock(\Magento\Sales\Api\Data\TransactionInterface::class);
        $second = $this->createMock(\Magento\Sales\Api\Data\TransactionInterface::class);
        $result = $this->createMock(TransactionSearchResultInterface::class);
        $result->method('getItems')->willReturn([$first, $second]);
        $this->transactionRepository->method('getList')->with($searchCriteria)->willReturn($result);
        $this->transactionRepository->expects($this->exactly(2))->method('delete');
        $this->orderRepository->expects($this->once())->method('save')->with($order);

        $instance = $this->processorFor(
            $this->push(['brq_statuscode' => '190', 'brq_transactions' => 'AUTH_KEY', 'add_initiated_by_magento' => '1']),
            $order,
            $payment
        );

        $this->assertTrue($this->invoke('canUpdateOrderStatus', $instance));
        $this->assertTrue($this->getProperty('forceInvoice', $instance));
        $this->assertSame(['_transactionsLookup' => null, 'transaction' => null], $cleared);
        $this->assertSame(
            [
                'buckaroo_order_reactivated' => true,
                'invoice_handling' => 2,
                'buckaroo_reservation_number' => 'RESERVATION',
            ],
            $set->getData()
        );
    }

    public function testAReactivationStillSavesTheOrderWhenTheOldTransactionsCannotBeRemoved(): void
    {
        $this->pushTransactionType->method('getStatusKey')->willReturn(self::SUCCESS);
        $order = $this->order(Order::STATE_CANCELED);
        // No authorization stored yet: nothing to compare the transaction with
        $payment = $this->payment([]);
        $methodInstance = $this->createMock(BuckarooAdapter::class);
        $methodInstance->method('getConfigData')->with('create_invoice_after_shipment')->willReturn('0');
        $payment->method('getMethodInstance')->willReturn($methodInstance);
        $set = $this->recordAdditionalInformation($payment);
        $this->searchCriteriaBuilder->method('addFilter')->willThrowException(new \RuntimeException('db down'));
        $this->orderRepository->expects($this->once())->method('save')->with($order);

        $instance = $this->processorFor(
            $this->push(['brq_statuscode' => '190', 'brq_datarequest' => 'AUTH_KEY', 'add_initiated_by_magento' => '1']),
            $order,
            $payment
        );

        $this->assertTrue($this->invoke('canUpdateOrderStatus', $instance));
        $this->assertSame(['buckaroo_order_reactivated' => true], $set->getData());
    }

    public function testARefundCheckedAgainstAnEarlierStatusRecordsNothing(): void
    {
        $this->pushTransactionType->method('getPushType')->willReturn('transaction_push');
        $payment = $this->payment(['buckaroo_received_transactions_statuses' => ['TRX' => '190']], 'buckaroo_magento2_ideal');
        $payment->expects($this->never())->method('setAdditionalInformation');
        $this->paymentRepository->expects($this->never())->method('save');

        $instance = $this->processorFor(
            $this->push(['brq_statuscode' => '190', 'brq_transactions' => 'TRX']),
            $this->order(Order::STATE_PROCESSING),
            $payment
        );

        $this->assertFalse($this->invokeArgs('receivePushCheckDuplicates', [794, 'REFUND_KEY'], $instance));
        $this->assertNull($instance->getRecordedTransactionStatus());
    }

    public function testWithoutPaymentNothingIsADuplicate(): void
    {
        $this->pushTransactionType->method('getPushType')->willReturn('transaction_push');

        $instance = $this->processorFor(
            $this->push(['brq_statuscode' => '190', 'brq_transactions' => 'TRX']),
            $this->order(Order::STATE_PROCESSING),
            $this->payment([])
        );
        $this->setProperty('payment', null, $instance);

        $this->assertFalse($this->invoke('receivePushCheckDuplicates', $instance));
    }

    public function testARepeatedPushWithTheSameStatusIsADuplicate(): void
    {
        $this->pushTransactionType->method('getPushType')->willReturn('transaction_push');
        $this->paymentRepository->expects($this->never())->method('save');

        $instance = $this->processorFor(
            $this->push(['brq_statuscode' => '190', 'brq_transactions' => 'TRX']),
            $this->order(Order::STATE_PROCESSING),
            $this->payment(['buckaroo_received_transactions_statuses' => ['TRX' => '190']], 'buckaroo_magento2_ideal')
        );

        $this->assertTrue($this->invoke('receivePushCheckDuplicates', $instance));
    }

    public function testARepeatedSuccessOnAStillPendingOrderIsProcessedAgain(): void
    {
        $this->pushTransactionType->method('getPushType')->willReturn('transaction_push');
        $order = $this->order(Order::STATE_NEW);
        $order->method('getStatus')->willReturn('pending');
        $this->helper->method('getOrderStatusByState')->with($order, Order::STATE_NEW)->willReturn('pending');

        $instance = $this->processorFor(
            $this->push(['brq_statuscode' => '190', 'brq_transactions' => 'TRX']),
            $order,
            $this->payment(['buckaroo_received_transactions_statuses' => ['TRX' => '190']], 'buckaroo_magento2_ideal')
        );

        $this->assertFalse($this->invoke('receivePushCheckDuplicates', $instance));
    }

    /**
     * @param array|null $statuses
     * @param array      $recorded
     */
    #[DataProvider('newStatusProvider')]
    public function testANewStatusIsRecordedOnThePayment(?array $statuses, array $recorded): void
    {
        $this->pushTransactionType->method('getPushType')->willReturn('transaction_push');
        $payment = $this->payment(['buckaroo_received_transactions_statuses' => $statuses], 'buckaroo_magento2_ideal');
        $payment->method('getId')->willReturn(7);
        $payment->expects($this->once())->method('setAdditionalInformation')
            ->with('buckaroo_received_transactions_statuses', array_merge($statuses ?? [], ['TRX' => '190']));
        $this->paymentRepository->expects($this->once())->method('save')->with($payment);

        $instance = $this->processorFor(
            $this->push(['brq_statuscode' => '190', 'brq_transactions' => 'TRX']),
            $this->order(Order::STATE_NEW),
            $payment
        );

        $this->assertFalse($this->invoke('receivePushCheckDuplicates', $instance));
        $this->assertSame($recorded, $instance->getRecordedTransactionStatus());
    }

    public static function newStatusProvider(): array
    {
        return [
            'first status' => [
                null,
                ['payment_id' => 7, 'transaction_key' => 'TRX', 'had_previous' => false, 'previous' => null],
            ],
            'status changed' => [
                ['TRX' => '792', 'OTHER' => '190'],
                ['payment_id' => 7, 'transaction_key' => 'TRX', 'had_previous' => true, 'previous' => '792'],
            ],
        ];
    }

    /**
     * @param array  $push
     * @param string $method
     * @param string $pushType
     */
    #[DataProvider('notCheckedProvider')]
    public function testSomePushesAreNotCheckedForDuplicates(array $push, string $method, string $pushType): void
    {
        $this->pushTransactionType->method('getPushType')->willReturn($pushType);
        $payment = $this->payment(['buckaroo_received_transactions_statuses' => ['TRX' => '190']], $method);
        $payment->expects($this->never())->method('setAdditionalInformation');
        $this->paymentRepository->expects($this->never())->method('save');

        $instance = $this->processorFor($this->push($push), $this->order(Order::STATE_PROCESSING), $payment);

        $this->assertFalse($this->invoke('receivePushCheckDuplicates', $instance));
    }

    public static function notCheckedProvider(): array
    {
        $push = ['brq_statuscode' => '190', 'brq_transactions' => 'TRX'];

        return [
            'gift card payment' => [$push, 'buckaroo_magento2_giftcards', 'transaction_push'],
            'bank transfer' => [$push, 'buckaroo_magento2_transfer', 'transaction_push'],
            'invoice push' => [$push, 'buckaroo_magento2_ideal', 'invoice_push'],
            'no status code' => [['brq_transactions' => 'TRX'], 'buckaroo_magento2_ideal', 'transaction_push'],
            'no transaction' => [['brq_statuscode' => '190'], 'buckaroo_magento2_ideal', 'transaction_push'],
        ];
    }

    public function testARepeatedRefundOfAGiftCardIsADuplicate(): void
    {
        $this->pushTransactionType->method('getPushType')->willReturn('transaction_push');

        $instance = $this->processorFor(
            $this->push([
                'brq_statuscode' => '190',
                'brq_transactions' => 'TRX',
                'add_service_action_from_magento' => 'refund',
            ]),
            $this->order(Order::STATE_PROCESSING),
            $this->payment(['buckaroo_received_transactions_statuses' => ['TRX' => '190']], 'buckaroo_magento2_giftcards')
        );

        $this->assertTrue($this->invoke('receivePushCheckDuplicates', $instance));
    }

    /**
     * @param string $state
     * @param bool   $hasInvoices
     */
    #[DataProvider('paidOrderProvider')]
    public function testAFailedPushDoesNotTouchAPaidOrder(string $state, bool $hasInvoices): void
    {
        $order = $this->order($state);
        $order->method('hasInvoices')->willReturn($hasInvoices);
        $order->expects($this->never())->method('addCommentToStatusHistory');
        $this->orderRequestService->expects($this->never())->method('updateOrderStatus');

        $instance = $this->processorFor($this->push(['brq_statuscode' => '490']), $order, $this->payment([]));

        $this->assertFalse($instance->processFailedPush('canceled', 'Failed'));
    }

    public static function paidOrderProvider(): array
    {
        return [
            'processing' => [Order::STATE_PROCESSING, false],
            'invoiced' => [Order::STATE_NEW, true],
        ];
    }

    public function testAFailedPushCancelsTheOrderWithoutCallingOrderManagementWhenNotConfigured(): void
    {
        $order = $this->order(Order::STATE_NEW);
        $order->method('canCancel')->willReturn(true);
        $order->expects($this->once())->method('addCommentToStatusHistory')->with('Payment failed: Failed');
        $this->configAccount->method('getCancelOnFailed')->willReturn(false);
        $this->orderManagement->expects($this->never())->method('cancel');
        $this->orderRequestService->expects($this->once())->method('updateOrderStatus')->with(
            Order::STATE_CANCELED,
            'canceled',
            'Payment Push Status: Failed REJECT high_risk score 99'
        );

        $instance = $this->processorFor(
            $this->push([
                'brq_statuscode' => '490',
                'brq_service_antifraud_action' => 'REJECT',
                'brq_service_antifraud_check' => 'high_risk',
                'brq_service_antifraud_details' => 'score 99',
            ]),
            $order,
            $this->payment([])
        );

        $this->assertTrue($instance->processFailedPush('canceled', 'Failed'));
    }

    #[DataProvider('authorizeMethodProvider')]
    public function testAFailedPushCancelsTheOrderAndRefundsItsGiftCards(string $methodCode): void
    {
        $order = $this->order(Order::STATE_NEW);
        $order->method('canCancel')->willReturn(true);
        $order->expects($this->once())->method('addCommentToStatusHistory')
            ->with('Payment failed. Canceling order due to payment failure: Failed');

        $payment = $this->payment([]);
        $set = $this->recordAdditionalInformation($payment);
        $methodInstance = $this->createMock(BuckarooAdapter::class);
        $methodInstance->method('getCode')->willReturn($methodCode);
        $payment->method('getMethodInstance')->willReturn($methodInstance);
        $order->method('getPayment')->willReturn($payment);
        $this->paymentRepository->expects($this->once())->method('save')->with($payment);

        $this->configAccount->method('getCancelOnFailed')->willReturn(true);
        // Without the failure redirect Magento's own gift card observers do not run, so Buckaroo
        // refunds the gift cards itself on Magento Open Source and Adobe Commerce alike
        $this->configAccount->method('getFailureRedirectToCheckout')->willReturn(false);
        $this->orderManagement->expects($this->once())->method('cancel')->with(5)
            ->willReturnCallback(function () {
                $this->assertFalse(BuckarooAdapter::$requestOnVoid, 'Nothing to void without a transaction');
                return true;
            });
        $this->orderRequestService->expects($this->once())->method('loadOrder');
        $this->giftCardRefund->expects($this->once())->method('refund')->with($order);
        $this->orderRequestService->expects($this->once())->method('updateOrderStatus')
            ->with(Order::STATE_CANCELED, 'canceled', 'Payment Push Status: Failed');

        $instance = $this->processorFor($this->push(['brq_statuscode' => '490']), $order, $payment);

        $this->assertTrue($instance->processFailedPush('canceled', 'Failed'));
        $this->assertTrue(BuckarooAdapter::$requestOnVoid);
        $this->assertSame(['buckaroo_failed_authorize' => 1], $set->getData());
    }

    public static function authorizeMethodProvider(): array
    {
        return [
            'Riverty (old)' => ['buckaroo_magento2_afterpay'],
            'Riverty (v2)' => ['buckaroo_magento2_afterpay2'],
            'Klarna' => ['buckaroo_magento2_klarna'],
            'Klarna KP' => ['buckaroo_magento2_klarnakp'],
        ];
    }

    public function testAFailedPushVoidsAnExistingAuthorization(): void
    {
        $order = $this->order(Order::STATE_NEW);
        $order->method('canCancel')->willReturn(true);
        $payment = $this->payment(['buckaroo_original_transaction_key' => 'AUTH_KEY']);
        $methodInstance = $this->createMock(BuckarooAdapter::class);
        $methodInstance->method('getCode')->willReturn('buckaroo_magento2_ideal');
        $payment->method('getMethodInstance')->willReturn($methodInstance);
        $order->method('getPayment')->willReturn($payment);
        $payment->expects($this->never())->method('setAdditionalInformation');

        $this->configAccount->method('getCancelOnFailed')->willReturn(true);
        $this->orderManagement->expects($this->once())->method('cancel')
            ->willReturnCallback(function () {
                $this->assertTrue(BuckarooAdapter::$requestOnVoid);
                return false;
            });
        $this->orderRequestService->expects($this->once())->method('updateOrderStatus');

        $instance = $this->processorFor($this->push(['brq_statuscode' => '490']), $order, $payment);

        $this->assertTrue($instance->processFailedPush('canceled', 'Failed'));
    }

    public function testAFailedCancelIsRetriedByBuckarooUnlessTheOrderGotCancelled(): void
    {
        $order = $this->createMock(OrderStub::class);
        $order->method('getId')->willReturn(5);
        $order->method('canCancel')->willReturn(true);
        $order->method('getState')->willReturn(Order::STATE_NEW);
        $payment = $this->payment(['buckaroo_original_transaction_key' => 'AUTH_KEY']);
        $order->method('getPayment')->willReturn($payment);
        $methodInstance = $this->createMock(BuckarooAdapter::class);
        $payment->method('getMethodInstance')->willReturn($methodInstance);

        $this->configAccount->method('getCancelOnFailed')->willReturn(true);
        $this->orderManagement->method('cancel')->willThrowException(new \RuntimeException('lock wait timeout'));

        $instance = $this->processorFor($this->push(['brq_statuscode' => '490']), $order, $payment);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('lock wait timeout');

        $instance->processFailedPush('canceled', 'Failed');
    }

    public function testAnErrorAfterTheOrderWasCancelledIsAcknowledged(): void
    {
        $order = $this->createMock(OrderStub::class);
        $order->method('getId')->willReturn(5);
        $order->method('canCancel')->willReturn(true);
        $order->method('getState')->willReturnOnConsecutiveCalls(Order::STATE_NEW, Order::STATE_CANCELED);
        $payment = $this->payment(['buckaroo_original_transaction_key' => 'AUTH_KEY']);
        $order->method('getPayment')->willReturn($payment);
        $payment->method('getMethodInstance')->willReturn($this->createMock(BuckarooAdapter::class));

        $this->configAccount->method('getCancelOnFailed')->willReturn(true);
        $this->orderManagement->method('cancel')->willReturn(true);
        $this->giftCardRefund->method('refund')->willThrowException(new \RuntimeException('gift card service down'));
        $this->orderRequestService->expects($this->exactly(2))->method('loadOrder');
        $this->orderRequestService->expects($this->never())->method('updateOrderStatus');

        $instance = $this->processorFor($this->push(['brq_statuscode' => '490']), $order, $payment);

        $this->assertTrue($instance->processFailedPush('canceled', 'Failed'));
    }

    public function testTheFirstPushBeforeTheResponseIsRefusedAndCounted(): void
    {
        $payment = $this->payment(['skip_push' => '1']);
        $payment->expects($this->once())->method('setAdditionalInformation')->with('skip_push', 0);
        $payment->expects($this->never())->method('unsAdditionalInformation');
        $this->paymentRepository->expects($this->once())->method('save')->with($payment);
        $order = $this->order(Order::STATE_NEW);
        $order->method('getPayment')->willReturn($payment);
        $this->orderRequestService->method('getOrderByRequest')->willReturn($order);

        $this->expectException(BuckarooException::class);
        $this->expectExceptionMessage(
            'Skipped handling this push, first handle response, action will be taken on the next push.'
        );

        $this->getInstance()->processPush($this->push(['brq_statuscode' => '190']));
    }

    /**
     * @param string $state
     */
    #[DataProvider('finalStateProvider')]
    public function testAPushOnAFinishedOrderIsAcknowledgedWithoutChanges(string $state): void
    {
        $payment = $this->payment([CancelGuard::OUTCOME_UNKNOWN => true]);
        $payment->expects($this->once())->method('unsAdditionalInformation')->with(CancelGuard::OUTCOME_UNKNOWN);
        $order = $this->order($state);
        $order->method('getPayment')->willReturn($payment);
        $this->orderRequestService->method('getOrderByRequest')->willReturn($order);
        $this->orderRequestService->expects($this->once())->method('setOrderNotificationNote')
            ->with($this->callback(static fn($note) => (string)$note === 'The order has already been processed.'));
        $this->orderRepository->expects($this->never())->method('save');

        $this->assertTrue($this->getInstance()->processPush($this->push(['brq_statuscode' => '190'])));
    }

    /**
     * @param string $method
     * @param array  $push
     * @param string $state
     * @param bool   $processing
     */
    #[DataProvider('authorizationProvider')]
    public function testASucceededAuthorizationMovesAnOpenOrderToProcessing(
        string $method,
        array $push,
        string $state,
        bool $processing
    ): void {
        $order = $this->order($state);
        $order->expects($processing ? $this->once() : $this->never())->method('setState')->with(Order::STATE_PROCESSING);

        $instance = $this->processorFor($this->push($push), $order, $this->payment([], $method));

        $this->invoke('processSucceededPushAuthorization', $instance);
    }

    public static function authorizationProvider(): array
    {
        $authorized = ['brq_statuscode' => '190', 'brq_transaction_type' => 'I038'];

        return [
            'Klarna KP' => ['buckaroo_magento2_klarnakp', ['brq_statuscode' => '190'], Order::STATE_NEW, true],
            'card authorization' => ['buckaroo_magento2_creditcard', $authorized, Order::STATE_PENDING_PAYMENT, true],
            'Riverty authorization in review' => ['buckaroo_magento2_afterpay20', ['brq_statuscode' => '190', 'brq_transaction_type' => 'I880'], Order::STATE_PAYMENT_REVIEW, true],
            'Wero authorization' => ['buckaroo_magento2_wero', $authorized, Order::STATE_NEW, true],
            'card payment' => ['buckaroo_magento2_creditcard', ['brq_statuscode' => '190', 'brq_transaction_type' => 'C800'], Order::STATE_NEW, false],
            'failed authorization' => ['buckaroo_magento2_creditcard', ['brq_statuscode' => '490', 'brq_transaction_type' => 'I038'], Order::STATE_NEW, false],
            'already processing' => ['buckaroo_magento2_klarnakp', ['brq_statuscode' => '190'], Order::STATE_PROCESSING, false],
            'iDEAL' => ['buckaroo_magento2_ideal', $authorized, Order::STATE_NEW, false],
            'Riverty (old) authorization' => ['buckaroo_magento2_afterpay', $authorized, Order::STATE_NEW, true],
            'Riverty (v2) authorization' => ['buckaroo_magento2_afterpay2', $authorized, Order::STATE_NEW, true],
        ];
    }

    /**
     * @param string     $state
     * @param bool       $canceled
     * @param string     $methodCode
     * @param array      $push
     * @param bool       $emailSent
     * @param bool       $globalEmail
     * @param mixed      $methodEmail
     * @param bool|null  $sync null when no email is sent
     */
    #[DataProvider('orderEmailProvider')]
    public function testTheOrderEmailIsSentOnlyForAPaymentThatWillStand(
        string $state,
        bool $canceled,
        string $methodCode,
        array $push,
        bool $emailSent,
        bool $globalEmail,
        $methodEmail,
        ?bool $sync
    ): void {
        $order = $this->order($state);
        $order->method('isCanceled')->willReturn($canceled);
        $order->method('getEmailSent')->willReturn($emailSent);
        $methodInstance = $this->createMock(BuckarooAdapter::class);
        $methodInstance->method('getCode')->willReturn($methodCode);
        $methodInstance->method('getConfigData')->willReturn($methodEmail);
        $payment = $this->payment([]);
        $payment->method('getMethodInstance')->willReturn($methodInstance);
        $this->configAccount->method('getOrderConfirmationEmail')->willReturn($globalEmail);
        $this->configAccount->method('getOrderConfirmationEmailSync')->willReturn('1');

        if ($sync === null) {
            $this->orderRequestService->expects($this->never())->method('sendOrderEmail');
        } else {
            $this->orderRequestService->expects($this->once())->method('sendOrderEmail')->with($order, $sync);
        }

        $instance = $this->processorFor($this->push($push + ['brq_statuscode' => '190']), $order, $payment);

        $this->invoke('sendOrderEmail', $instance);
    }

    public static function orderEmailProvider(): array
    {
        $capture = ['brq_transaction_type' => 'I038', 'add_initiated_by_magento' => '1', 'add_service_action_from_magento' => 'capture'];

        return [
            'paid order' => [Order::STATE_PROCESSING, false, 'buckaroo_magento2_ideal', [], false, true, null, true],
            'method setting only' => [Order::STATE_PROCESSING, false, 'buckaroo_magento2_ideal', [], false, false, '1', true],
            'emails off' => [Order::STATE_PROCESSING, false, 'buckaroo_magento2_ideal', [], false, false, '0', null],
            'already sent' => [Order::STATE_PROCESSING, false, 'buckaroo_magento2_ideal', [], true, true, null, null],
            'cancelled order' => [Order::STATE_PROCESSING, true, 'buckaroo_magento2_ideal', [], false, true, null, null],
            'canceled state' => [Order::STATE_CANCELED, false, 'buckaroo_magento2_ideal', [], false, true, null, null],
            'Riverty authorization' => [Order::STATE_PROCESSING, false, 'buckaroo_magento2_afterpay20', ['brq_transaction_type' => 'I038'], false, true, null, null],
            'Riverty capture' => [Order::STATE_PROCESSING, false, 'buckaroo_magento2_afterpay20', $capture, false, true, null, true],
            'Riverty (old) authorization' => [Order::STATE_PROCESSING, false, 'buckaroo_magento2_afterpay', ['brq_transaction_type' => 'I880'], false, true, null, null],
            'Riverty (v2) authorization' => [Order::STATE_PROCESSING, false, 'buckaroo_magento2_afterpay2', ['brq_transaction_type' => 'I038'], false, true, null, null],
            'Riverty payment' => [Order::STATE_PROCESSING, false, 'buckaroo_magento2_afterpay', ['brq_transaction_type' => 'C800'], false, true, null, true],
        ];
    }

    public function testAnAuthorizationMovesTheOrderToProcessingWithItsDescription(): void
    {
        [$instance, $order, $payment] = $this->succeededPushProcessor(false);
        $order->expects($this->never())->method('setState');
        $set = $this->recordAdditionalInformation($payment);
        $this->paymentRepository->expects($this->once())->method('save')->with($payment);
        $this->setProperty('pendingSingleGiftcardInfo', [
            'servicecode' => 'vvvgiftcard',
            'amount' => 20.0,
            'transaction_id' => 'PAY_KEY',
            'currency' => 'EUR',
        ], $instance);
        $this->orderRequestService->expects($this->once())->method('updateOrderStatus')->with(
            Order::STATE_PROCESSING,
            'buckaroo_authorized',
            'Authorization status: <strong>Success</strong><br/>Total amount of €20.00 has been authorized.'
            . ' Please create an invoice to capture the authorized amount.'
        );

        $this->assertTrue($this->invokeArgs('applySucceededPush', ['buckaroo_authorized', 'Success'], $instance));
        $this->assertTrue($this->getProperty('dontSaveOrderUponSuccessPush', $instance));
        $this->assertSame(
            [
                'single_giftcard_payment' => true,
                'single_giftcard_servicecode' => 'vvvgiftcard',
                'single_giftcard_amount' => 20.0,
                'single_giftcard_transaction_id' => 'PAY_KEY',
                'single_giftcard_currency' => 'EUR',
            ],
            $set->getData()
        );
        $this->assertFalse($set->hasData('specific_payment_details'));
    }

    public function testAPaymentThatDoesNotSettleTheOrderKeepsItInReview(): void
    {
        [$instance] = $this->succeededPushProcessor(true);
        $this->orderRequestService->expects($this->once())->method('updateOrderStatus')->with(
            Order::STATE_PAYMENT_REVIEW,
            Order::STATUS_FRAUD,
            'Payment Push Status: <strong>Success</strong><br/>The received amount of €20.00 does not settle the'
            . ' order. The order is held for review.'
        );

        $this->assertTrue($this->invokeArgs('applySucceededPush', ['buckaroo_authorized', 'Success'], $instance));
        $this->assertTrue($this->getProperty('dontSaveOrderUponSuccessPush', $instance));
    }

    public function testACaptureBeforeShipmentIsRecordedWithoutInvoice(): void
    {
        $payment = $this->payment(['invoice_handling' => '2']);
        $methodInstance = $this->createMock(BuckarooAdapter::class);
        $methodInstance->method('getConfigData')->willReturn('authorize');
        $payment->method('getMethodInstance')->willReturn($methodInstance);
        $payment->expects($this->once())->method('setTransactionId')->with('CAPTURE_KEY');
        $payment->expects($this->once())->method('setBaseAmountPaid')->with(40.0);
        $this->paymentRepository->expects($this->once())->method('save')->with($payment);

        $order = $this->order(Order::STATE_PROCESSING);
        $order->method('getPayment')->willReturn($payment);
        $order->method('getOrderCurrencyCode')->willReturn('EUR');
        $order->method('getOrderCurrency')->willReturn($this->currency());
        $order->expects($this->never())->method('prepareInvoice');

        $this->orderRequestService->expects($this->once())->method('updateOrderStatus')->with(
            Order::STATE_PROCESSING,
            'buckaroo_captured',
            'Capture status: <strong>Success</strong><br/>Total amount of €40.00 has been captured.'
            . ' Invoice will be created on shipment.'
        );

        $instance = $this->processorFor($this->push([
            'brq_statuscode' => '190',
            'brq_transactions' => 'CAPTURE_KEY',
            'brq_amount' => '40.00',
            'brq_currency' => 'EUR',
            'add_initiated_by_magento' => '1',
            'add_service_action_from_magento' => 'capture',
        ]), $order, $payment);

        $this->assertTrue($this->invokeArgs('applySucceededPush', ['buckaroo_captured', 'Success'], $instance));
        $this->assertTrue($this->getProperty('dontSaveOrderUponSuccessPush', $instance));
    }

    public function testACaptureWithoutAmountRecordsTheBaseAmountDue(): void
    {
        $payment = $this->payment(['invoice_handling' => '2']);
        $payment->method('getMethodInstance')->willReturn($this->createMock(BuckarooAdapter::class));
        $payment->expects($this->once())->method('setBaseAmountPaid')->with(45.5);

        $order = $this->order(Order::STATE_PROCESSING);
        $order->method('getPayment')->willReturn($payment);
        $order->method('getBaseTotalDue')->willReturn(45.5);
        $order->method('getBaseCurrencyCode')->willReturn('EUR');
        $order->method('getBaseCurrency')->willReturn($this->currency());

        $this->orderRequestService->expects($this->once())->method('updateOrderStatus')->with(
            Order::STATE_PROCESSING,
            'buckaroo_captured',
            'Capture status: <strong>Success</strong><br/>Total amount of €45.50 has been captured.'
            . ' Invoice will be created on shipment.'
        );

        $instance = $this->processorFor($this->push([
            'brq_statuscode' => '190',
            'brq_transactions' => 'CAPTURE_KEY',
            'add_initiated_by_magento' => '1',
            'add_service_action_from_magento' => 'capture',
        ]), $order, $payment);

        $this->assertTrue($this->invokeArgs('applySucceededPush', ['buckaroo_captured', 'Success'], $instance));
    }

    public function testACaptureInvoicedOnPaymentNeedsAnInvoice(): void
    {
        $payment = $this->payment(['invoice_handling' => '1']);
        $payment->method('getMethodInstance')->willReturn($this->createMock(BuckarooAdapter::class));
        $payment->expects($this->never())->method('setBaseAmountPaid');

        $order = $this->order(Order::STATE_PROCESSING);
        $order->method('getPayment')->willReturn($payment);
        $order->method('canInvoice')->willReturn(false);
        $order->method('getBaseCurrency')->willReturn($this->currency());
        $order->method('getOrderCurrencyCode')->willReturn('EUR');
        $order->method('getOrderCurrency')->willReturn($this->currency());
        $this->orderRequestService->expects($this->never())->method('updateOrderStatus');

        $instance = $this->processorFor($this->push([
            'brq_statuscode' => '190',
            'brq_transactions' => 'CAPTURE_KEY',
            'brq_amount' => '40.00',
            'brq_currency' => 'EUR',
            'add_initiated_by_magento' => '1',
            'add_service_action_from_magento' => 'capture',
        ]), $order, $payment);

        $this->assertFalse($this->invokeArgs('applySucceededPush', ['buckaroo_captured', 'Success'], $instance));
    }

    /**
     * A successful iDEAL-like push on an order whose payment action is authorize and invoiced on payment.
     *
     * @param bool $fraud
     *
     * @return array
     */
    private function succeededPushProcessor(bool $fraud): array
    {
        $payment = $this->payment(['invoice_handling' => '1'], 'buckaroo_magento2_ideal');
        $methodInstance = $this->createMock(BuckarooAdapter::class);
        $methodInstance->method('getConfigData')->willReturn('authorize');
        $payment->method('getMethodInstance')->willReturn($methodInstance);
        $payment->method('getIsFraudDetected')->willReturn($fraud);

        $order = $this->order(Order::STATE_NEW);
        $order->method('getPayment')->willReturn($payment);
        $order->method('getOrderCurrencyCode')->willReturn('EUR');
        $order->method('getOrderCurrency')->willReturn($this->currency());

        $instance = $this->processorFor($this->push([
            'brq_statuscode' => '190',
            'brq_transactions' => 'PAY_KEY',
            'brq_amount' => '20.00',
            'brq_currency' => 'EUR',
        ]), $order, $payment);

        return [$instance, $order, $payment];
    }

    /**
     * @return \Magento\Directory\Model\Currency&MockObject
     */
    private function currency()
    {
        $currency = $this->createMock(\Magento\Directory\Model\Currency::class);
        $currency->method('formatTxt')->willReturnCallback(static fn($amount) => '€' . number_format((float)$amount, 2));

        return $currency;
    }

    public static function finalStateProvider(): array
    {
        return [
            'complete' => [Order::STATE_COMPLETE],
            'closed' => [Order::STATE_CLOSED],
            'on hold' => [Order::STATE_HOLDED],
            'canceled' => [Order::STATE_CANCELED],
        ];
    }

    /**
     * @param HttppostPushRequest $push
     * @param Order               $order
     * @param Payment             $payment
     *
     * @return DefaultProcessor
     */
    private function processorFor($push, $order, $payment)
    {
        $instance = $this->getInstance();
        $this->setProperty('pushRequest', $push, $instance);
        $this->setProperty('order', $order, $instance);
        $this->setProperty('payment', $payment, $instance);

        return $instance;
    }

    /**
     * @param array $data raw push fields
     *
     * @return HttppostPushRequest
     */
    private function push(array $data): HttppostPushRequest
    {
        return new HttppostPushRequest(array_filter($data, static fn($value) => $value !== null), $this->createMock(PushValidator::class));
    }

    /**
     * @param string $state
     *
     * @return OrderStub&MockObject
     */
    private function order(string $state)
    {
        $order = $this->createMock(OrderStub::class);
        $order->method('getState')->willReturn($state);
        $order->method('getId')->willReturn(5);
        $order->method('getIncrementId')->willReturn('300000555');

        return $order;
    }

    /**
     * @param array       $info
     * @param string|null $method
     *
     * @return Payment&MockObject
     */
    private function payment(array $info, ?string $method = null)
    {
        $payment = $this->createMock(Payment::class);
        $payment->method('getAdditionalInformation')->willReturnCallback(static fn($key) => $info[$key] ?? null);
        $payment->method('getMethod')->willReturn($method);

        return $payment;
    }

    /**
     * @param Payment&MockObject $payment
     *
     * @return \Magento\Framework\DataObject what was set, in order
     */
    private function recordAdditionalInformation($payment): \Magento\Framework\DataObject
    {
        $set = new \Magento\Framework\DataObject();
        $payment->method('setAdditionalInformation')->willReturnCallback(
            static function ($key, $value) use ($set, $payment) {
                $set->setData($key, $value);
                return $payment;
            }
        );

        return $set;
    }
}
