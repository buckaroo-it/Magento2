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
use Buckaroo\Magento2\Helper\PaymentGroupTransaction;
use Buckaroo\Magento2\Model\Method\BuckarooAdapter;
use Buckaroo\Magento2\Model\Push\PushTransactionType;
use Buckaroo\Magento2\Model\Refund\Push as RefundPush;
use Buckaroo\Magento2\Service\Push\OrderRequestService;
use Buckaroo\Magento2\Test\Unit\Stubs\PushRequestInterfaceStub;
use Magento\Directory\Model\Currency;
use Magento\Framework\DataObject;
use Magento\Sales\Api\OrderManagementInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Payment;

/**
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 * @SuppressWarnings(PHPMD.TooManyPublicMethods)
 */
class RefundProcessorTest extends \Buckaroo\Magento2\Test\BaseTest
{
    protected $instanceClass = 'Buckaroo\Magento2\Model\Push\RefundProcessor';

    private const PART = '9898363B3646D5208B3728175261FA92';

    private const SUCCESS = 'BUCKAROO_MAGENTO2_STATUSCODE_SUCCESS';

    protected function tearDown(): void
    {
        BuckarooAdapter::$requestOnVoid = true;
        parent::tearDown();
    }

    public function testASuccessfulRefundOnAnInvoicedOrderGoesToTheCreditMemoFlow(): void
    {
        $order = $this->makeOrder(true, 50.0);
        $pushRequest = $this->makePushRequest(['statusCode' => '190']);

        $refundPush = $this->createMock(RefundPush::class);
        $refundPush->expects($this->once())->method('receiveRefundPush')
            ->with($this->identicalTo($pushRequest), true, $this->identicalTo($order))
            ->willReturn(false);

        $instance = $this->getInstance([
            'orderRequestService' => $this->makeOrderRequestService($order),
            'pushTransactionType' => $this->makePushTransactionType(self::SUCCESS),
            'refundPush' => $refundPush,
        ]);

        $this->assertFalse($instance->processPush($pushRequest));
    }

    public function testAFailedRefundOnAnInvoicedOrderOnlyAddsANote(): void
    {
        $order = $this->makeOrder(true, 50.0);
        $orderRequestService = $this->makeOrderRequestService($order);
        $orderRequestService->expects($this->once())->method('setOrderNotificationNote')
            ->with($this->callback(static function ($note): bool {
                return (string)$note === 'Push notification for refund has no success status, ignoring.';
            }));

        $refundPush = $this->createMock(RefundPush::class);
        $refundPush->expects($this->never())->method('receiveRefundPush');

        $instance = $this->getInstance([
            'orderRequestService' => $orderRequestService,
            'pushTransactionType' => $this->makePushTransactionType('BUCKAROO_MAGENTO2_STATUSCODE_FAILED'),
            'refundPush' => $refundPush,
        ]);

        $this->assertTrue($instance->processPush($this->makePushRequest(['statusCode' => '490'])));
    }

    public function testAFailedRefundOnAnOrderWithoutInvoiceThrows(): void
    {
        $order = $this->makeOrder(false, 0.0);

        $refundPush = $this->createMock(RefundPush::class);
        $refundPush->expects($this->never())->method('receiveRefundPush');

        $instance = $this->getInstance([
            'orderRequestService' => $this->makeOrderRequestService($order),
            'pushTransactionType' => $this->makePushTransactionType('BUCKAROO_MAGENTO2_STATUSCODE_FAILED'),
            'refundPush' => $refundPush,
        ]);

        $this->expectException(BuckarooException::class);
        $this->expectExceptionMessage(
            'Refund failed ! Status : BUCKAROO_MAGENTO2_STATUSCODE_FAILED and the order does not contain an invoice'
        );

        $instance->processPush($this->makePushRequest(['statusCode' => '490']));
    }

    public function testARefundOfAnUnpaidPartialPaymentDoesNotCreateACreditMemo(): void
    {
        $order = $this->makeOrder(false, 0.0);
        $order->method('canCancel')->willReturn(true);

        $groupTransaction = $this->makeGroupTransaction($this->makePart('300000376'), 5.0);
        $groupTransaction->expects($this->once())->method('updateGroupTransaction')
            ->with(['entity_id' => 7, 'refunded_amount' => 10.0]);

        $refundPush = $this->createMock(RefundPush::class);
        $refundPush->expects($this->never())->method('receiveRefundPush');

        $instance = $this->getInstance([
            'orderRequestService' => $this->makeOrderRequestService($order),
            'pushTransactionType' => $this->makePushTransactionType(self::SUCCESS),
            'refundPush' => $refundPush,
            'groupTransaction' => $groupTransaction,
            'orderRepository' => $this->createMock(OrderRepositoryInterface::class),
        ]);

        $this->assertTrue($instance->processPush($this->makePushRequest(['statusCode' => '190'])));
    }

    /**
     * Refunds started from Magento: which pushes are skipped before any processing.
     *
     * @param string      $statusCode
     * @param string|null $relatedRefund
     * @param array|null  $receivedStatuses
     * @param bool        $skipped
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('magentoRefundProvider')]
    public function testPushesOfARefundStartedFromMagento(
        string $statusCode,
        ?string $relatedRefund,
        ?array $receivedStatuses,
        bool $skipped
    ): void {
        $order = $this->makeOrder(true, 50.0);
        $payment = $this->createMock(Payment::class);
        $payment->method('getMethod')->willReturn('buckaroo_magento2_ideal');
        $payment->method('getAdditionalInformation')->willReturn($receivedStatuses);
        $order->method('getPayment')->willReturn($payment);

        $pushRequest = $this->makePushRequest([
            'statusCode' => $statusCode,
            'relatedRefund' => $relatedRefund,
            'initiatedByMagento' => true,
        ]);

        $refundPush = $this->createMock(RefundPush::class);
        $refundPush->expects($skipped ? $this->never() : $this->once())->method('receiveRefundPush')
            ->willReturn(true);

        $instance = $this->getInstance([
            'orderRequestService' => $this->makeOrderRequestService($order),
            'pushTransactionType' => $this->makePushTransactionType(self::SUCCESS),
            'refundPush' => $refundPush,
        ]);

        $this->assertTrue($instance->processPush($pushRequest));
    }

    public static function magentoRefundProvider(): array
    {
        return [
            'pending approval' => ['794', 'REFUND_KEY', null, true],
            'success after an approved pending refund' => ['190', 'REFUND_KEY', ['REFUND_KEY' => '794'], true],
            'success of a refund without pending approval' => ['190', 'REFUND_KEY', ['OTHER_KEY' => '794'], false],
            'success without related refund' => ['190', null, null, true],
            'any other status' => ['792', 'REFUND_KEY', null, true],
        ];
    }

    public function testARefundStartedOutsideMagentoIsNotSkippedWhilePendingApproval(): void
    {
        $order = $this->makeOrder(true, 50.0);
        $pushRequest = $this->makePushRequest(['statusCode' => '794', 'relatedRefund' => 'REFUND_KEY']);

        $refundPush = $this->createMock(RefundPush::class);
        $refundPush->expects($this->once())->method('receiveRefundPush')->willReturn(true);

        $instance = $this->getInstance([
            'orderRequestService' => $this->makeOrderRequestService($order),
            'pushTransactionType' => $this->makePushTransactionType(self::SUCCESS),
            'refundPush' => $refundPush,
        ]);

        $this->assertTrue($instance->processPush($pushRequest));
    }

    public function testAnotherMagentoActionIsNotSkippedWhilePendingApproval(): void
    {
        $order = $this->makeOrder(true, 50.0);
        $pushRequest = $this->makePushRequest([
            'statusCode' => '794',
            'relatedRefund' => 'REFUND_KEY',
            'initiatedByMagento' => true,
            'action' => 'capture',
        ]);

        $refundPush = $this->createMock(RefundPush::class);
        $refundPush->expects($this->once())->method('receiveRefundPush')->willReturn(true);

        $instance = $this->getInstance([
            'orderRequestService' => $this->makeOrderRequestService($order),
            'pushTransactionType' => $this->makePushTransactionType(self::SUCCESS),
            'refundPush' => $refundPush,
        ]);

        $this->assertTrue($instance->processPush($pushRequest));
    }

    public function testWithoutRelatedRefundTheGroupTransactionIsNotLookedUp(): void
    {
        $groupTransaction = $this->createMock(PaymentGroupTransaction::class);
        $groupTransaction->expects($this->never())->method('getGroupTransactionByTrxId');

        $instance = $this->getInstance(['groupTransaction' => $groupTransaction]);
        $this->setProperty('order', $this->makeOrder(false, 0.0), $instance);
        $this->setProperty('pushRequest', $this->makePushRequest(['relatedRefund' => null]), $instance);

        $this->assertFalse($this->invoke('isRefundOfUnpaidPartialPayment', $instance));
    }

    /**
     * @param bool       $hasInvoices
     * @param float      $totalPaid
     * @param DataObject $part
     * @param bool       $expected
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('unpaidPartialPaymentProvider')]
    public function testRecognisesARefundOfAnUnpaidPartialPayment(
        bool $hasInvoices,
        float $totalPaid,
        DataObject $part,
        bool $expected
    ): void {
        $instance = $this->getInstance(['groupTransaction' => $this->makeGroupTransaction($part, 0.0)]);
        $this->setProperty('order', $this->makeOrder($hasInvoices, $totalPaid), $instance);
        $this->setProperty('pushRequest', $this->makePushRequest([]), $instance);

        $this->assertSame($expected, $this->invoke('isRefundOfUnpaidPartialPayment', $instance));
    }

    public static function unpaidPartialPaymentProvider(): array
    {
        $part = new DataObject(['id' => 7, 'order_id' => '300000376', 'amount' => '10.00']);

        return [
            'invoiced order, nothing paid' => [true, 0.0, $part, false],
            'less than half a cent paid' => [false, 0.004, $part, true],
            'one cent paid' => [false, 0.01, $part, false],
            'four cents paid' => [false, 0.04, $part, false],
            'part without id' => [false, 0.0, new DataObject(['order_id' => '300000376']), false],
            'numeric order id on the part' => [false, 0.0, new DataObject(['id' => 7, 'order_id' => 300000376]), true],
        ];
    }

    /**
     * @param float $stillPaid
     * @param bool  $cancelled
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('stillPaidProvider')]
    public function testTheOrderIsCancelledOnlyWhenNothingIsStillPaid(float $stillPaid, bool $cancelled): void
    {
        $order = $this->makeOrder(false, 0.0);
        $order->method('canCancel')->willReturn(true);

        $orderManagement = $this->createMock(OrderManagementInterface::class);
        $orderManagement->expects($cancelled ? $this->once() : $this->never())->method('cancel')->with(376);

        $orderRepository = $this->createMock(OrderRepositoryInterface::class);
        $orderRepository->method('get')->willReturn($this->createMock(Order::class));

        $instance = $this->makeProcessor($order, $stillPaid, [
            'orderManagement' => $orderManagement,
            'orderRepository' => $orderRepository,
        ]);

        $this->assertTrue($this->invoke('handleRefundOfUnpaidPartialPayment', $instance));
    }

    public static function stillPaidProvider(): array
    {
        return [
            'less than half a cent' => [0.004, true],
            'four cents' => [0.04, false],
            'half a euro' => [0.5, false],
        ];
    }

    public function testAnOrderKeptOpenGetsACommentAndIsSaved(): void
    {
        $order = $this->makeOrder(false, 0.0);
        $order->method('canCancel')->willReturn(true);
        $order->expects($this->once())->method('addCommentToStatusHistory')
            ->with('Buckaroo: the partial payment of €10.00 (transaction ' . self::PART . ') was refunded.');

        $orderRepository = $this->createMock(OrderRepositoryInterface::class);
        $orderRepository->expects($this->once())->method('save')->with($this->identicalTo($order));

        $instance = $this->makeProcessor($order, 5.0, ['orderRepository' => $orderRepository]);

        $this->assertTrue($this->invoke('handleRefundOfUnpaidPartialPayment', $instance));
    }

    public function testACancelledOrderIsReloadedCommentedAndSaved(): void
    {
        $order = $this->makeOrder(false, 0.0);
        $order->method('canCancel')->willReturn(true);

        $reloaded = $this->createMock(Order::class);
        $reloaded->expects($this->once())->method('addCommentToStatusHistory')
            ->with(
                'Buckaroo: the partial payment of €10.00 (transaction ' . self::PART . ') was refunded before'
                . ' the order was paid. The order is cancelled.'
            );

        $orderRepository = $this->createMock(OrderRepositoryInterface::class);
        $orderRepository->expects($this->once())->method('get')->with(376)->willReturn($reloaded);
        $orderRepository->expects($this->once())->method('save')->with($this->identicalTo($reloaded));

        $instance = $this->makeProcessor($order, 0.0, [
            'orderManagement' => $this->createMock(OrderManagementInterface::class),
            'orderRepository' => $orderRepository,
        ]);

        $this->assertTrue($this->invoke('handleRefundOfUnpaidPartialPayment', $instance));
    }

    public function testAFailingCancelStillRestoresTheVoidRequestFlag(): void
    {
        $order = $this->makeOrder(false, 0.0);
        $order->method('canCancel')->willReturn(true);

        $orderManagement = $this->createMock(OrderManagementInterface::class);
        $orderManagement->method('cancel')->willThrowException(new \RuntimeException('cancel failed'));

        $instance = $this->makeProcessor($order, 0.0, ['orderManagement' => $orderManagement]);

        try {
            $this->invoke('handleRefundOfUnpaidPartialPayment', $instance);
            $this->fail('The cancel exception is passed on');
        } catch (\RuntimeException $exception) {
            $this->assertSame('cancel failed', $exception->getMessage());
        }

        $this->assertTrue(BuckarooAdapter::$requestOnVoid);
    }

    /**
     * @param bool   $hasInvoices
     * @param float  $totalPaid
     * @param string $state
     *
     * @return Order&\PHPUnit\Framework\MockObject\MockObject
     */
    private function makeOrder(bool $hasInvoices, float $totalPaid, string $state = Order::STATE_NEW)
    {
        $currency = $this->createMock(Currency::class);
        $currency->method('formatTxt')->willReturnCallback(static fn($amount) => '€' . number_format($amount, 2));

        $order = $this->createMock(Order::class);
        $order->method('hasInvoices')->willReturn($hasInvoices);
        $order->method('getTotalPaid')->willReturn($totalPaid);
        $order->method('getIncrementId')->willReturn('300000376');
        $order->method('getId')->willReturn(376);
        $order->method('getState')->willReturn($state);
        $order->method('getOrderCurrencyCode')->willReturn('EUR');
        $order->method('getOrderCurrency')->willReturn($currency);

        return $order;
    }

    /**
     * @param array $data statusCode, relatedRefund, initiatedByMagento
     *
     * @return PushRequestInterfaceStub&\PHPUnit\Framework\MockObject\MockObject
     */
    private function makePushRequest(array $data)
    {
        $initiated = $data['initiatedByMagento'] ?? false;
        $action = $data['action'] ?? 'refund';

        $pushRequest = $this->createMock(PushRequestInterfaceStub::class);
        $pushRequest->method('getStatusCode')->willReturn($data['statusCode'] ?? '190');
        $pushRequest->method('getRelatedtransactionRefund')->willReturn(
            array_key_exists('relatedRefund', $data) ? $data['relatedRefund'] : self::PART
        );
        $pushRequest->method('getAmountCredit')->willReturn('10.00');
        $pushRequest->method('getCurrency')->willReturn('EUR');
        $pushRequest->method('hasAdditionalInformation')->willReturnCallback(
            static function (string $name, $value) use ($initiated, $action): bool {
                if (!$initiated) {
                    return false;
                }
                if ($name === 'initiated_by_magento') {
                    return $value === 1;
                }

                return $name === 'service_action_from_magento' && in_array($action, (array)$value, true);
            }
        );

        return $pushRequest;
    }

    /**
     * @param Order $order
     *
     * @return OrderRequestService&\PHPUnit\Framework\MockObject\MockObject
     */
    private function makeOrderRequestService($order)
    {
        $service = $this->createMock(OrderRequestService::class);
        $service->expects($this->once())->method('loadOrder');
        $service->method('getOrderByRequest')->willReturn($order);

        return $service;
    }

    /**
     * @param string $statusKey
     *
     * @return PushTransactionType&\PHPUnit\Framework\MockObject\MockObject
     */
    private function makePushTransactionType(string $statusKey)
    {
        $type = $this->createMock(PushTransactionType::class);
        $type->method('getStatusKey')->willReturn($statusKey);
        $type->method('getPushType')->willReturn(PushTransactionType::BUCK_PUSH_TYPE_TRANSACTION);

        return $type;
    }

    /**
     * @param string $orderId
     *
     * @return DataObject
     */
    private function makePart(string $orderId): DataObject
    {
        return new DataObject([
            'id' => 7,
            'entity_id' => 7,
            'order_id' => $orderId,
            'transaction_id' => self::PART,
            'amount' => '10.00',
        ]);
    }

    /**
     * @param DataObject $part
     * @param float      $stillPaid
     *
     * @return PaymentGroupTransaction&\PHPUnit\Framework\MockObject\MockObject
     */
    private function makeGroupTransaction(DataObject $part, float $stillPaid)
    {
        $groupTransaction = $this->createMock(PaymentGroupTransaction::class);
        $groupTransaction->method('getGroupTransactionByTrxId')->with(self::PART)->willReturn($part);
        $groupTransaction->method('getGroupTransactionAmount')->with('300000376')->willReturn($stillPaid);

        return $groupTransaction;
    }

    /**
     * @param Order $order
     * @param float $stillPaid
     * @param array $args
     *
     * @return object
     */
    private function makeProcessor($order, float $stillPaid, array $args = [])
    {
        $instance = $this->getInstance(array_merge([
            'groupTransaction' => $this->makeGroupTransaction($this->makePart('300000376'), $stillPaid),
            'orderRepository' => $this->createMock(OrderRepositoryInterface::class),
        ], $args));
        $this->setProperty('order', $order, $instance);
        $this->setProperty('pushRequest', $this->makePushRequest([]), $instance);

        return $instance;
    }
}
