<?php
declare(strict_types=1);

namespace Buckaroo\Magento2\Test\Unit\Model\Push;


use PHPUnit\Framework\Attributes\DataProvider;
use Buckaroo\Magento2\Model\BuckarooStatusCode;
use Buckaroo\Magento2\Model\Config\Source\InvoiceHandlingOptions;
use Magento\Sales\Api\InvoiceRepositoryInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Invoice;

/**
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 * @SuppressWarnings(PHPMD.ExcessivePublicCount)
 */
class DefaultProcessorTest extends \Buckaroo\Magento2\Test\BaseTest
{
    protected $instanceClass = 'Buckaroo\Magento2\Model\Push\DefaultProcessor';

    private $orderRequestServiceMock;
    private $pushTransactionTypeMock;
    private $loggerMock;
    private $helperMock;
    private $transactionMock;
    private $groupTransactionMock;
    private $buckarooStatusCodeMock;
    private $orderStatusFactoryMock;
    private $configAccountMock;
    private $giftCardRefundServiceMock;
    private $uncancelServiceMock;
    private $resourceConnectionMock;
    private $giftcardCollectionMock;
    private $currencyFactoryMock;

    public function setUp(): void
    {
        parent::setUp();

        $this->orderRequestServiceMock = $this->getFakeMock('Buckaroo\Magento2\Service\Push\OrderRequestService')->getMock();
        $this->pushTransactionTypeMock = $this->getFakeMock('Buckaroo\Magento2\Model\Push\PushTransactionType')->getMock();
        $this->loggerMock = $this->getFakeMock('Buckaroo\Magento2\Logging\BuckarooLoggerInterface')->getMock();
        $this->helperMock = $this->getFakeMock('Buckaroo\Magento2\Helper\Data')->getMock();
        $this->transactionMock = $this->getFakeMock('Magento\Sales\Api\Data\TransactionInterface')->getMock();
        $this->groupTransactionMock = $this->getFakeMock('Buckaroo\Magento2\Helper\PaymentGroupTransaction')->getMock();
        $this->buckarooStatusCodeMock = $this->getFakeMock('Buckaroo\Magento2\Model\BuckarooStatusCode')->getMock();
        $this->orderStatusFactoryMock = $this->getFakeMock('Buckaroo\Magento2\Model\OrderStatusFactory')->getMock();
        $this->configAccountMock = $this->getFakeMock('Buckaroo\Magento2\Model\ConfigProvider\Account')->getMock();
        $this->giftCardRefundServiceMock = $this->getFakeMock('Buckaroo\Magento2\Model\Service\GiftCardRefundService')->getMock();
        $this->uncancelServiceMock = $this->getFakeMock('Buckaroo\Magento2\Service\Order\Uncancel')->getMock();
        $this->resourceConnectionMock = $this->getFakeMock('Magento\Framework\App\ResourceConnection')->getMock();
        $this->giftcardCollectionMock = $this->getFakeMock('Buckaroo\Magento2\Model\ResourceModel\Giftcard\Collection')->getMock();
        $this->currencyFactoryMock = $this->getFakeMock('Magento\Directory\Model\CurrencyFactory')->getMock();
    }

    public function getInstance(array $args = [])
    {
        return parent::getInstance([
            'orderRequestService' => $this->orderRequestServiceMock,
            'pushTransactionType' => $this->pushTransactionTypeMock,
            'logger' => $this->loggerMock,
            'helper' => $this->helperMock,
            'transaction' => $this->transactionMock,
            'groupTransaction' => $this->groupTransactionMock,
            'buckarooStatusCode' => $this->buckarooStatusCodeMock,
            'orderStatusFactory' => $this->orderStatusFactoryMock,
            'configAccount' => $this->configAccountMock,
            'giftCardRefundService' => $this->giftCardRefundServiceMock,
            'uncancelService' => $this->uncancelServiceMock,
            'resourceConnection' => $this->resourceConnectionMock,
            'giftcardCollection' => $this->giftcardCollectionMock,
            'currencyFactory' => $this->currencyFactoryMock,
        ] + $args);
    }

    public static function pendingPaymentEmailProvider(): array
    {
        return [
            'sends when not sent, not canceled, global config on' => [
                'emailSent' => false,
                'orderIsCanceled' => false,
                'globalConfigEnabled' => true,
                'methodOrderEmail' => null,
                'expected' => true,
            ],
            'sends via method-level order_email when global config off' => [
                'emailSent' => false,
                'orderIsCanceled' => false,
                'globalConfigEnabled' => false,
                'methodOrderEmail' => '1',
                'expected' => true,
            ],
            'skips when email already sent' => [
                'emailSent' => true,
                'orderIsCanceled' => false,
                'globalConfigEnabled' => true,
                'methodOrderEmail' => null,
                'expected' => false,
            ],
            'skips when order is canceled' => [
                'emailSent' => false,
                'orderIsCanceled' => true,
                'globalConfigEnabled' => true,
                'methodOrderEmail' => null,
                'expected' => false,
            ],
            'skips when all email configs are off' => [
                'emailSent' => false,
                'orderIsCanceled' => false,
                'globalConfigEnabled' => false,
                'methodOrderEmail' => 0,
                'expected' => false,
            ],
        ];
    }

    public function testResponseCodeZeroDoesNotAddAFailedOrderNote(): void
    {
        $instance = $this->getInstance();

        $this->pushTransactionTypeMock->method('getStatusCode')->willReturn(0);
        $this->pushTransactionTypeMock->method('getStatusKey')->willReturn('BUCKAROO_MAGENTO2_STATUSCODE_NEUTRAL');
        $this->buckarooStatusCodeMock->method('getFailedStatuses')->willReturn([]);
        $this->buckarooStatusCodeMock->method('getPendingStatuses')->willReturn([]);
        $this->orderStatusFactoryMock->method('get')->willReturn('pending');

        $pushRequest = $this->getFakeMock(\Buckaroo\Magento2\Api\Data\PushRequestInterface::class)->getMock();
        $pushRequest->method('getStatusCode')->willReturn('0');
        $this->setProperty('pushRequest', $pushRequest, $instance);
        $this->setProperty('order', $this->getFakeMock(Order::class)->getMock(), $instance);

        $this->orderRequestServiceMock->expects($this->never())->method('setOrderNotificationNote');
        $this->orderRequestServiceMock->expects($this->never())->method('updateOrderStatus');

        $this->assertTrue($this->invoke('processPushByStatus', $instance));
    }

    #[DataProvider('pendingPaymentEmailProvider')]
    public function testShouldSendPendingPaymentEmail(
        bool $emailSent,
        bool $orderIsCanceled,
        bool $globalConfigEnabled,
        $methodOrderEmail,
        bool $expected
    ): void {
        $instance = $this->getInstance();

        $orderMock = $this->getFakeMock('Magento\Sales\Model\Order')->getMock();
        $orderMock->method('getEmailSent')->willReturn($emailSent);

        $storeMock = $this->getFakeMock('Magento\Store\Model\Store')->getMock();

        $methodInstanceMock = $this->getFakeMock('Magento\Payment\Model\MethodInterface')->getMock();
        $methodInstanceMock->method('getConfigData')->willReturn($methodOrderEmail);

        $this->configAccountMock->method('getOrderConfirmationEmail')->willReturn($globalConfigEnabled);

        $this->setProperty('order', $orderMock, $instance);

        $result = $this->invokeArgs(
            'shouldSendPendingPaymentEmail',
            [$orderIsCanceled, $storeMock, $methodInstanceMock],
            $instance
        );

        $this->assertSame($expected, $result);
    }

    public function testProcessPendingPaymentEmailSendsEmailForWaitingOnConsumerPush(): void
    {
        $instance = $this->getInstance();

        $storeMock = $this->getFakeMock('Magento\Store\Model\Store')->getMock();

        $orderMock = $this->getFakeMock('Magento\Sales\Model\Order')->getMock();
        $orderMock->method('getStore')->willReturn($storeMock);
        $orderMock->method('getEmailSent')->willReturn(false);
        $orderMock->method('isCanceled')->willReturn(false);
        $orderMock->method('getState')->willReturn(Order::STATE_NEW);

        $methodInstanceMock = $this->getFakeMock('Magento\Payment\Model\MethodInterface')->getMock();

        $paymentMock = $this->getFakeMock('Magento\Sales\Model\Order\Payment')->getMock();
        $paymentMock->method('getMethodInstance')->willReturn($methodInstanceMock);

        $pushRequestMock = $this->getFakeMock('Buckaroo\Magento2\Api\Data\PushRequestInterface')->getMock();
        $pushRequestMock->method('getStatusCode')->willReturn('792');

        $this->configAccountMock->method('getOrderConfirmationEmail')->willReturn(true);

        $this->orderRequestServiceMock->expects($this->once())
            ->method('sendOrderEmail')
            ->with($orderMock);

        $this->setProperty('order', $orderMock, $instance);
        $this->setProperty('payment', $paymentMock, $instance);
        $this->setProperty('pushRequest', $pushRequestMock, $instance);

        $this->invokeArgs('processPendingPaymentEmail', [], $instance);
    }

    public function testProcessPendingPaymentEmailSkipsEmailForCanceledOrder(): void
    {
        $instance = $this->getInstance();

        $storeMock = $this->getFakeMock('Magento\Store\Model\Store')->getMock();

        $orderMock = $this->getFakeMock('Magento\Sales\Model\Order')->getMock();
        $orderMock->method('getStore')->willReturn($storeMock);
        $orderMock->method('getEmailSent')->willReturn(false);
        $orderMock->method('isCanceled')->willReturn(true);
        $orderMock->method('getState')->willReturn(Order::STATE_CANCELED);

        $methodInstanceMock = $this->getFakeMock('Magento\Payment\Model\MethodInterface')->getMock();

        $paymentMock = $this->getFakeMock('Magento\Sales\Model\Order\Payment')->getMock();
        $paymentMock->method('getMethodInstance')->willReturn($methodInstanceMock);

        $pushRequestMock = $this->getFakeMock('Buckaroo\Magento2\Api\Data\PushRequestInterface')->getMock();
        $pushRequestMock->method('getStatusCode')->willReturn('792');

        $this->configAccountMock->method('getOrderConfirmationEmail')->willReturn(true);

        $this->orderRequestServiceMock->expects($this->never())
            ->method('sendOrderEmail');

        $this->setProperty('order', $orderMock, $instance);
        $this->setProperty('payment', $paymentMock, $instance);
        $this->setProperty('pushRequest', $pushRequestMock, $instance);

        $this->invokeArgs('processPendingPaymentEmail', [], $instance);
    }

    /**
     * The capture must be registered for the amount the gateway actually took. Registering the
     * order total for a smaller capture overstates total_paid and produces an order comment that
     * contradicts the amount sent.
     *
     * @param float|null $pushedAmount
     * @param bool       $isSameCurrency
     * @param bool       $isCaptureFinal
     * @param float      $expected
     */
    #[DataProvider('captureNotificationAmountProvider')]
    public function testCaptureNotificationUsesTheAmountTheGatewayCaptured(
        ?float $pushedAmount,
        bool $isSameCurrency,
        bool $isCaptureFinal,
        float $expected
    ): void {
        $instance = $this->getInstance();

        $pushRequestMock = $this->getFakeMock(\Buckaroo\Magento2\Test\Unit\Stubs\PushRequestInterfaceStub::class)
            ->getMock();
        $pushRequestMock->method('getAmount')->willReturn($pushedAmount);

        $paymentMock = $this->getFakeMock('Magento\Sales\Model\Order\Payment')->getMock();
        $paymentMock->method('isSameCurrency')->willReturn($isSameCurrency);
        $paymentMock->method('isCaptureFinal')->willReturn($isCaptureFinal);

        $orderMock = $this->getFakeMock('Magento\Sales\Model\Order')->getMock();
        $orderMock->method('getGrandTotal')->willReturn(209.09);
        $orderMock->method('getBaseTotalDue')->willReturn(190.00);

        $this->setProperty('order', $orderMock, $instance);
        $this->setProperty('payment', $paymentMock, $instance);
        $this->setProperty('pushRequest', $pushRequestMock, $instance);

        $this->assertEquals($expected, $this->invokeArgs('resolveCaptureNotificationAmount', [], $instance));
    }

    public static function captureNotificationAmountProvider(): array
    {
        return [
            // A partial capture must be recorded as such, not as the full order total.
            'partial capture is recorded for what was captured' => [150.00, true, false, 150.00],
            'full capture is recorded for what was captured'    => [209.09, true, true, 209.09],
            // Without an amount on the push the previous behaviour stands.
            'no pushed amount falls back to the order total'    => [null, true, true, 209.09],
            'non final capture falls back to base total due'    => [null, true, false, 190.00],
            // A differing payment currency keeps using base_total_due to avoid a fraud flag.
            'differing currency falls back to base total due'   => [150.00, false, true, 190.00],
        ];
    }

    /**
     * Buckaroo charges, and reports, the order currency, while Magento registers a capture in the
     * base currency. A payment that covers the amount due in the order currency must therefore
     * settle the base amount due, a partial one must be converted, and an amount in a currency that
     * was never charged must not count towards the order at all.
     *
     * @param float  $pushedAmount
     * @param string $pushedCurrency
     * @param string $orderCurrency
     * @param float  $expected
     */
    #[DataProvider('captureNotificationCurrencyProvider')]
    public function testCaptureNotificationAmountIsExpressedInTheBaseCurrency(
        float $pushedAmount,
        string $pushedCurrency,
        string $orderCurrency,
        float $expected
    ): void {
        $multiCurrency = $orderCurrency !== 'EUR';

        $this->helperMock->method('areEqualAmounts')
            ->willReturnCallback(function ($first, $second) {
                return abs((float)$first - (float)$second) < 0.00001;
            });

        $instance = $this->getInstance();

        $pushRequestMock = $this->getFakeMock(\Buckaroo\Magento2\Test\Unit\Stubs\PushRequestInterfaceStub::class)
            ->getMock();
        $pushRequestMock->method('getAmount')->willReturn($pushedAmount);
        $pushRequestMock->method('getCurrency')->willReturn($pushedCurrency);

        $paymentMock = $this->getFakeMock('Magento\Sales\Model\Order\Payment')->getMock();
        $paymentMock->method('isSameCurrency')->willReturn(true);

        $orderMock = $this->getFakeMock('Magento\Sales\Model\Order')->getMock();
        $orderMock->method('getBaseCurrencyCode')->willReturn('EUR');
        $orderMock->method('getOrderCurrencyCode')->willReturn($orderCurrency);
        $orderMock->method('getBaseToOrderRate')->willReturn($multiCurrency ? 1.1 : 1.0);
        $orderMock->method('getTotalDue')->willReturn($multiCurrency ? 110.00 : 100.00);
        $orderMock->method('getBaseTotalDue')->willReturn(100.00);

        $this->setProperty('order', $orderMock, $instance);
        $this->setProperty('payment', $paymentMock, $instance);
        $this->setProperty('pushRequest', $pushRequestMock, $instance);

        $this->assertEqualsWithDelta(
            $expected,
            $this->invokeArgs('resolveCaptureNotificationAmount', [], $instance),
            0.00001
        );
    }

    public static function captureNotificationCurrencyProvider(): array
    {
        return [
            'single currency amount is used as is'                 => [100.00, 'EUR', 'EUR', 100.00],
            'single currency partial amount is used as is'         => [50.00, 'EUR', 'EUR', 50.00],
            'full payment in the order currency settles base due'  => [110.00, 'USD', 'USD', 100.00],
            'partial payment in the order currency is converted'   => [55.00, 'USD', 'USD', 50.00],
            'amount reported in the base currency is used as is'   => [100.00, 'EUR', 'USD', 100.00],
            'lower case currency code is the same currency'        => [110.00, 'usd', 'USD', 100.00],
            'amount in a currency that was not charged is ignored' => [110.00, 'GBP', 'USD', 0.00],
            'single currency order paid in another currency'       => [100.00, 'USD', 'EUR', 0.00],
        ];
    }

    /**
     * Magento creates no invoice for a success push that does not settle the order and flags the
     * payment as suspected fraud instead. The order must then stay in payment review rather than
     * read as paid, while a push that settles the order still moves it to processing.
     *
     * @param bool   $isFraudDetected
     * @param string $expectedState
     * @param string $expectedStatus
     */
    #[DataProvider('succeededPushSettlementProvider')]
    public function testSucceededPushOnlyMovesTheOrderToProcessingWhenItSettlesTheOrder(
        bool $isFraudDetected,
        string $expectedState,
        string $expectedStatus
    ): void {
        $instance = $this->getFakeMock($this->instanceClass)
            ->onlyMethods([
                'setBuckarooReservationNumber',
                'getPaymentDetails',
                'setSpecificPaymentDetails',
                'isCaptureTransaction',
                'canPushInvoice',
                'saveInvoice',
                'processSucceededPushAuthorization',
                'formatCommentAmount',
            ])
            ->disableOriginalConstructor()
            ->getMock();

        $instance->method('getPaymentDetails')->willReturn(['amount' => 53.24, 'description' => 'paid in full']);
        $instance->method('isCaptureTransaction')->willReturn(false);
        $instance->method('canPushInvoice')->willReturn(true);
        $instance->method('formatCommentAmount')->willReturn('EUR 26.62');
        $instance->expects($this->once())->method('saveInvoice')->willReturn(true);

        $pushRequestMock = $this->getFakeMock(\Buckaroo\Magento2\Test\Unit\Stubs\PushRequestInterfaceStub::class)
            ->getMock();
        $pushRequestMock->method('getAmount')->willReturn(26.62);
        $pushRequestMock->method('getCurrency')->willReturn('EUR');

        $paymentMock = $this->getFakeMock('Magento\Sales\Model\Order\Payment')->getMock();
        $paymentMock->method('getIsFraudDetected')->willReturn($isFraudDetected);

        $orderMock = $this->getFakeMock('Magento\Sales\Model\Order')->getMock();
        $orderMock->method('getIncrementId')->willReturn('300000015');

        $this->orderRequestServiceMock->expects($this->once())
            ->method('updateOrderStatus')
            ->with($expectedState, $expectedStatus, $this->anything());

        $this->setProperty('order', $orderMock, $instance);
        $this->setProperty('payment', $paymentMock, $instance);
        $this->setProperty('pushRequest', $pushRequestMock, $instance);
        $this->setProperty('orderRequestService', $this->orderRequestServiceMock, $instance);
        $this->setProperty('logger', $this->loggerMock, $instance);

        $this->assertTrue($this->invokeArgs('applySucceededPush', ['processing', 'Success'], $instance));
        $this->assertTrue($this->getProperty('dontSaveOrderUponSuccessPush', $instance));
    }

    public static function succeededPushSettlementProvider(): array
    {
        return [
            'underpaid push is held in payment review' => [true, Order::STATE_PAYMENT_REVIEW, Order::STATUS_FRAUD],
            'settling push moves the order on'         => [false, Order::STATE_PROCESSING, 'processing'],
        ];
    }

    /**
     * Recording the push message must not move the order. An intermediate save later in the push
     * would commit that state, so the order would sit in processing before the push has decided
     * anything, long enough for a warehouse integration to pick it up.
     */
    public function testSuccessfulPushRecordsTheStatusMessageWithoutTouchingTheOrderState(): void
    {
        $instance = $this->getInstance();

        $pushRequestMock = $this->getFakeMock(\Buckaroo\Magento2\Test\Unit\Stubs\PushRequestInterfaceStub::class)
            ->getMock();
        $pushRequestMock->method('getStatusMessage')->willReturn('The request was successful.');
        $pushRequestMock->method('getStatusCode')->willReturn((string)BuckarooStatusCode::SUCCESS);

        $orderMock = $this->getFakeMock('Magento\Sales\Model\Order')->getMock();
        $orderMock->method('getState')->willReturn(Order::STATE_NEW);

        $orderMock->expects($this->never())->method('setState');
        $orderMock->expects($this->once())->method('addCommentToStatusHistory');

        // The fabricated "processing" status came from here, so the comment must not be stamped with it.
        $this->helperMock->expects($this->never())->method('getOrderStatusByState');

        $this->setProperty('order', $orderMock, $instance);
        $this->setProperty('pushRequest', $pushRequestMock, $instance);

        $this->invokeArgs('setOrderStatusMessage', [], $instance);
    }

    public static function statusMessageHistoryProvider(): array
    {
        return [
            'successful push is recorded'                => [BuckarooStatusCode::SUCCESS, Order::STATE_NEW, true],
            'failed push is recorded'                    => [BuckarooStatusCode::FAILED, Order::STATE_PROCESSING, true],
            'pending push on a new order is recorded'    => [BuckarooStatusCode::PENDING_PROCESSING, Order::STATE_NEW, true],
            'pending push awaiting payment is recorded'  => [BuckarooStatusCode::PENDING_PROCESSING, Order::STATE_PENDING_PAYMENT, true],
            // A 791 arriving after the order already succeeded describes the earlier attempt (BP-4716).
            'pending push after progress is not recorded' => [BuckarooStatusCode::PENDING_PROCESSING, Order::STATE_PROCESSING, false],
        ];
    }

    /**
     * @param int    $statusCode
     * @param string $orderState
     * @param bool   $expectsComment
     */
    #[DataProvider('statusMessageHistoryProvider')]
    public function testStatusMessageIsOnlyRecordedWhenItStillDescribesTheOrder(
        int $statusCode,
        string $orderState,
        bool $expectsComment
    ): void {
        $instance = $this->getInstance();

        $pushRequestMock = $this->getFakeMock(\Buckaroo\Magento2\Test\Unit\Stubs\PushRequestInterfaceStub::class)
            ->getMock();
        $pushRequestMock->method('getStatusMessage')->willReturn('Some gateway message.');
        $pushRequestMock->method('getStatusCode')->willReturn((string)$statusCode);

        $orderMock = $this->getFakeMock('Magento\Sales\Model\Order')->getMock();
        $orderMock->method('getState')->willReturn($orderState);

        $orderMock->expects($this->never())->method('setState');
        $orderMock->expects($expectsComment ? $this->once() : $this->never())
            ->method('addCommentToStatusHistory');

        $this->setProperty('order', $orderMock, $instance);
        $this->setProperty('pushRequest', $pushRequestMock, $instance);

        $this->invokeArgs('setOrderStatusMessage', [], $instance);
    }

    /**
     * The order confirmation email is gated on the order state, so it has to be sent after the push
     * settled the state rather than off the back of a state set purely to stamp a comment.
     */
    public function testOrderEmailIsSentAfterTheSucceededPushHasBeenApplied(): void
    {
        $calls = [];

        $instance = $this->getFakeMock($this->instanceClass)
            ->onlyMethods(['applySucceededPush', 'sendOrderEmail'])
            ->disableOriginalConstructor()
            ->getMock();

        $instance->method('applySucceededPush')
            ->willReturnCallback(function () use (&$calls) {
                $calls[] = 'applySucceededPush';
                return true;
            });
        $instance->method('sendOrderEmail')
            ->willReturnCallback(function () use (&$calls) {
                $calls[] = 'sendOrderEmail';
            });

        $this->assertTrue($instance->processSucceededPush('processing', 'Success'));
        $this->assertSame(['applySucceededPush', 'sendOrderEmail'], $calls);
    }

    /**
     * A push that could not be applied is retried by Buckaroo, so confirming it to the customer
     * would email them for an order that is not settled yet.
     */
    public function testOrderEmailIsNotSentWhenTheSucceededPushCouldNotBeApplied(): void
    {
        $instance = $this->getFakeMock($this->instanceClass)
            ->onlyMethods(['applySucceededPush', 'sendOrderEmail'])
            ->disableOriginalConstructor()
            ->getMock();

        $instance->method('applySucceededPush')->willReturn(false);
        $instance->expects($this->never())->method('sendOrderEmail');
        $this->setProperty('order', $this->orderWith(true, 40.05), $instance);

        $this->assertFalse($instance->processSucceededPush('processing', 'Success'));
    }

    /**
     * A successful payment that left no trace on the order (for example cancelled meanwhile) must be
     * refused so Buckaroo sends the push again; answering it would lose the payment (BTI-2031).
     */
    public function testAnUnappliedSuccessfulPushOnAnUnpaidOrderIsRefused(): void
    {
        $instance = $this->getFakeMock($this->instanceClass)
            ->onlyMethods(['applySucceededPush', 'sendOrderEmail'])
            ->disableOriginalConstructor()
            ->getMock();

        $instance->method('applySucceededPush')->willReturn(false);
        $instance->expects($this->never())->method('sendOrderEmail');
        $this->setProperty('order', $this->orderWith(false, null), $instance);

        $this->expectException(\Buckaroo\Magento2\Exception::class);

        $instance->processSucceededPush('processing', 'Success');
    }

    /**
     * @param bool       $hasInvoices
     * @param float|null $totalPaid
     *
     * @return Order|\PHPUnit\Framework\MockObject\MockObject
     */
    private function orderWith(bool $hasInvoices, ?float $totalPaid)
    {
        $order = $this->createMock(Order::class);
        $order->method('hasInvoices')->willReturn($hasInvoices);
        $order->method('getTotalPaid')->willReturn($totalPaid);
        $order->method('getIncrementId')->willReturn('300000493');

        return $order;
    }

    /**
     * A pending push that arrives after the payment was recorded (pushes come out of order once
     * Buckaroo retries) must not move the paid order back to pending payment (BTI-2031).
     */
    public function testALatePendingPushDoesNotDowngradeAPaidOrder(): void
    {
        $instance = $this->getFakeMock($this->instanceClass)
            ->onlyMethods(['canProcessPendingPush'])
            ->disableOriginalConstructor()
            ->getMock();
        $instance->method('canProcessPendingPush')->willReturn(true);
        $this->setProperty('order', $this->orderWith(true, 40.05), $instance);

        $orderRequestService = $this->createMock(\Buckaroo\Magento2\Service\Push\OrderRequestService::class);
        $orderRequestService->expects($this->never())->method('updateOrderStatus');
        $this->setProperty('orderRequestService', $orderRequestService, $instance);
        $this->setProperty('logger', $this->createMock(\Buckaroo\Magento2\Logging\BuckarooLoggerInterface::class), $instance);

        $method = new \ReflectionMethod($instance, 'processPendingPaymentPush');
        $this->assertTrue($method->invoke($instance, 'pending_payment', 'Waiting for consumer'));
    }

    public static function partialPaymentPushProvider(): array
    {
        return [
            // A part carries its related group transaction from the very first push.
            'a related partial payment marks it as a part' => [null, 'REL-KEY', true],
            // An already recorded part is recognised by its group transaction row.
            'a recorded group transaction part is a part'  => ['partialpayment', null, true],
            'both signals present'                        => ['partialpayment', 'REL-KEY', true],
            // Neither: an ordinary single payment.
            'no signals is not a part'                    => [null, null, false],
            'an empty related key is not a part'          => [null, '', false],
            // A group transaction of another type is not a part.
            'a non partialpayment group type is not'      => ['group', null, false],
        ];
    }

    /**
     * A PayLink settled in several parts went unrecorded because the processors tested this
     * differently, so the condition lives in one place and is pinned here.
     *
     * @param string|null $groupTransactionType
     * @param string|null $relatedPartialPayment
     * @param bool        $expected
     */
    #[DataProvider('partialPaymentPushProvider')]
    public function testAPushIsRecognisedAsOnePartOfASplitPayment(
        ?string $groupTransactionType,
        ?string $relatedPartialPayment,
        bool $expected
    ): void {
        $instance = $this->getInstance();

        $pushRequestMock = $this->getFakeMock(\Buckaroo\Magento2\Test\Unit\Stubs\PushRequestInterfaceStub::class)
            ->getMock();
        $pushRequestMock->method('getTransactions')
            ->willReturn($groupTransactionType === null ? null : 'TRX-1');
        $pushRequestMock->method('getRelatedtransactionPartialpayment')
            ->willReturn($relatedPartialPayment);

        // getType() is a magic getter, so a real DataObject stands in for the group transaction row.
        $this->groupTransactionMock->method('getGroupTransactionByTrxId')
            ->willReturn(new \Magento\Framework\DataObject(['type' => $groupTransactionType]));

        $this->setProperty('pushRequest', $pushRequestMock, $instance);

        $this->assertSame($expected, $this->invokeArgs('isPartialPaymentPush', [], $instance));
    }

    /**
     * The invoice a capture creates only gets its number when it is saved. Magento has already
     * linked it to the capture transaction by then, so the invoice email must not depend on the
     * transaction id being empty, or it goes out without an invoice number.
     */
    public function testNewInvoiceIsSavedBeforeItsEmailIsSent(): void
    {
        $saved = [];
        $savedBeforeEmail = null;

        $invoiceMock = $this->getInvoiceMock(null, 'TRX-PUSH');

        $this->configAccountMock->method('getInvoiceEmail')->willReturn(true);
        $this->orderRequestServiceMock->method('sendInvoiceEmail')
            ->willReturnCallback(function ($invoice) use (&$saved, &$savedBeforeEmail) {
                $savedBeforeEmail = in_array($invoice, $saved, true);
                return true;
            });

        $instance = $this->getSaveInvoiceInstance([$invoiceMock], $this->getInvoiceRepositoryMock($saved));

        $this->assertTrue($this->invokeArgs('saveInvoice', [], $instance));
        $this->assertTrue($savedBeforeEmail, 'The invoice must be saved before its email is built');
    }

    /**
     * The order save writes the invoice again. Core observers recognise a new invoice by its empty
     * original data (MSI stock deduction for virtual items, Commerce store credit, gift card and
     * reward amounts), so after the early save the invoice must record that it exists, or the
     * order save makes them process it a second time.
     */
    public function testSavedInvoiceNoLongerLooksNewToTheOrderSave(): void
    {
        $saved = [];

        $invoiceMock = $this->getInvoiceMock(null, 'TRX-PUSH');
        $invoiceMock->setData('base_customer_balance_amount', 30.00);

        $this->configAccountMock->method('getInvoiceEmail')->willReturn(true);

        $instance = $this->getSaveInvoiceInstance([$invoiceMock], $this->getInvoiceRepositoryMock($saved));

        $this->assertNull($invoiceMock->getOrigData(), 'An unsaved invoice has no original data');

        $this->assertTrue($this->invokeArgs('saveInvoice', [], $instance));
        $this->assertSame(501, $invoiceMock->getOrigData('entity_id'));
        $this->assertSame(30.00, $invoiceMock->getOrigData('base_customer_balance_amount'));
    }

    /**
     * An invoice keeps the transaction it was created for, so a later capture cannot point the
     * refund of an earlier invoice at the wrong transaction, and one without a transaction is
     * stamped with the pushed one. Otherwise a new invoice is only saved early when its email goes
     * out: without an email the order save stores it once, as before.
     *
     * @param int|null $invoiceId
     * @param string   $transactionId
     * @param bool     $invoiceEmail
     * @param bool     $expectStamp
     * @param int      $expectedSaves
     */
    #[DataProvider('invoiceSaveProvider')]
    public function testInvoiceIsOnlySavedToStampItsTransactionOrToNumberItsEmail(
        ?int $invoiceId,
        string $transactionId,
        bool $invoiceEmail,
        bool $expectStamp,
        int $expectedSaves
    ): void {
        $saved = [];

        $invoiceMock = $this->getInvoiceMock($invoiceId, $transactionId);
        $invoiceMock->expects($expectStamp ? $this->once() : $this->never())
            ->method('setTransactionId')
            ->with('TRX-PUSH');

        $this->configAccountMock->method('getInvoiceEmail')->willReturn($invoiceEmail);

        $instance = $this->getSaveInvoiceInstance([$invoiceMock], $this->getInvoiceRepositoryMock($saved));

        $this->assertTrue($this->invokeArgs('saveInvoice', [], $instance));
        $this->assertCount($expectedSaves, $saved);
    }

    public static function invoiceSaveProvider(): array
    {
        return [
            'new invoice linked to the capture, email on'      => [null, 'TRX-PUSH', true, false, 1],
            'new invoice linked to the capture, email off'     => [null, 'TRX-PUSH', false, false, 0],
            'new invoice without a transaction, email on'      => [null, '', true, true, 1],
            'earlier invoice keeps its own transaction'        => [12, 'TRX-EARLIER', true, false, 0],
            'earlier invoice without a transaction is stamped' => [12, '', false, true, 1],
        ];
    }

    /**
     * @param int|null $id
     * @param string   $transactionId
     *
     * @return Invoice|\PHPUnit\Framework\MockObject\MockObject
     */
    private function getInvoiceMock(?int $id, string $transactionId)
    {
        $invoiceMock = $this->getFakeMock(Invoice::class)
            ->onlyMethods(['getId', 'getTransactionId', 'setTransactionId', 'getEmailSent'])
            ->getMock();
        $invoiceMock->setData('entity_id', $id);
        $invoiceMock->method('getId')->willReturnCallback(function () use ($invoiceMock) {
            return $invoiceMock->getData('entity_id');
        });
        $invoiceMock->method('getTransactionId')->willReturn($transactionId);
        $invoiceMock->method('getEmailSent')->willReturn(false);

        return $invoiceMock;
    }

    /**
     * An invoice repository that records what it saved and, like the INSERT, gives a new invoice its id.
     *
     * @param array $saved
     *
     * @return InvoiceRepositoryInterface|\PHPUnit\Framework\MockObject\MockObject
     */
    private function getInvoiceRepositoryMock(array &$saved)
    {
        $invoiceRepositoryMock = $this->getFakeMock(InvoiceRepositoryInterface::class)->getMock();
        $invoiceRepositoryMock->method('save')
            ->willReturnCallback(function ($invoice) use (&$saved) {
                if (!$invoice->getId()) {
                    $invoice->setData('entity_id', 501);
                }
                $saved[] = $invoice;
                return $invoice;
            });

        return $invoiceRepositoryMock;
    }

    /**
     * A processor that runs saveInvoice() for a capture push in invoice-on-payment mode.
     *
     * @param Invoice[] $invoices
     * @param InvoiceRepositoryInterface|\PHPUnit\Framework\MockObject\MockObject $invoiceRepositoryMock
     *
     * @return \Buckaroo\Magento2\Model\Push\DefaultProcessor|\PHPUnit\Framework\MockObject\MockObject
     */
    private function getSaveInvoiceInstance(array $invoices, $invoiceRepositoryMock)
    {
        $paymentMock = $this->getFakeMock('Magento\Sales\Model\Order\Payment')->getMock();

        $orderMock = $this->getFakeMock(Order::class)->getMock();
        $orderMock->method('canInvoice')->willReturn(true);
        $orderMock->method('hasInvoices')->willReturn(false);
        $orderMock->method('getInvoiceCollection')->willReturn($invoices);

        $pushRequestMock = $this->getFakeMock(\Buckaroo\Magento2\Test\Unit\Stubs\PushRequestInterfaceStub::class)
            ->getMock();

        $instance = $this->getFakeMock($this->instanceClass)
            ->onlyMethods([
                'addTransactionData',
                'detectInvoiceHandlingMode',
                'resolveCaptureNotificationAmount',
                'getTransactionKey',
            ])
            ->getMock();
        $instance->method('addTransactionData')->willReturn($paymentMock);
        $instance->method('detectInvoiceHandlingMode')->willReturn(InvoiceHandlingOptions::PAYMENT);
        $instance->method('resolveCaptureNotificationAmount')->willReturn(100.00);
        $instance->method('getTransactionKey')->willReturn('TRX-PUSH');

        $this->setProperty('logger', $this->loggerMock, $instance);
        $this->setProperty('order', $orderMock, $instance);
        $this->setProperty('payment', $paymentMock, $instance);
        $this->setProperty('pushRequest', $pushRequestMock, $instance);
        $this->setProperty('configAccount', $this->configAccountMock, $instance);
        $this->setProperty('orderRequestService', $this->orderRequestServiceMock, $instance);
        $this->setProperty('groupTransaction', $this->groupTransactionMock, $instance);
        $this->setProperty('invoiceRepository', $invoiceRepositoryMock, $instance);
        $this->setProperty(
            'paymentRepository',
            $this->getFakeMock('Magento\Sales\Api\OrderPaymentRepositoryInterface')->getMock(),
            $instance
        );
        $this->setProperty(
            'orderRepository',
            $this->getFakeMock('Magento\Sales\Api\OrderRepositoryInterface')->getMock(),
            $instance
        );

        return $instance;
    }

    public function testDuplicatePushIsAcknowledgedInsteadOfRejected(): void
    {
        $instance = $this->getMockBuilder($this->instanceClass)
            ->disableOriginalConstructor()
            ->onlyMethods(['initializeFields', 'skipPush', 'receivePushCheckDuplicates', 'canUpdateOrderStatus'])
            ->getMock();
        $instance->method('skipPush')->willReturn(false);
        $instance->method('receivePushCheckDuplicates')->willReturn(true);
        $instance->expects($this->never())->method('canUpdateOrderStatus');

        $this->setProperty('logger', $this->loggerMock, $instance);
        $this->setProperty('pushRequest', $this->duplicatePushRequest(['brq_transactions' => 'TRX']), $instance);

        $this->assertTrue($instance->processPush($this->duplicatePushRequest([])));
    }

    public function testDuplicatePushRefreshesTheStoredTransactionDetails(): void
    {
        $instance = $this->getMockBuilder($this->instanceClass)
            ->disableOriginalConstructor()
            ->onlyMethods(['initializeFields', 'skipPush', 'receivePushCheckDuplicates'])
            ->getMock();
        $instance->method('skipPush')->willReturn(false);
        $instance->method('receivePushCheckDuplicates')->willReturn(true);

        $data = ['brq_transactions' => 'TRX', 'brq_service_ideal_consumername' => 'J Doe'];
        $payment = $this->getFakeMock(\Magento\Sales\Model\Order\Payment::class)
            ->onlyMethods(['getAdditionalInformation', 'setAdditionalInformation'])
            ->getMock();
        $payment->method('getAdditionalInformation')
            ->willReturn(['TRX' => ['brq_transactions' => 'TRX']]);
        $payment->expects($this->once())
            ->method('setAdditionalInformation')
            ->with(\Magento\Sales\Model\Order\Payment\Transaction::RAW_DETAILS, ['TRX' => $data]);
        $this->helperMock->method('getTransactionAdditionalInfo')->willReturn($data);

        $paymentRepository = $this->getFakeMock('Magento\Sales\Api\OrderPaymentRepositoryInterface')->getMock();
        $paymentRepository->expects($this->once())->method('save')->with($payment);

        $this->setProperty('logger', $this->loggerMock, $instance);
        $this->setProperty('helper', $this->helperMock, $instance);
        $this->setProperty('payment', $payment, $instance);
        $this->setProperty('paymentRepository', $paymentRepository, $instance);
        $this->setProperty('pushRequest', $this->duplicatePushRequest($data), $instance);

        $this->assertTrue($instance->processPush($this->duplicatePushRequest($data)));
    }

    public function testDuplicatePushStaysAcknowledgedWhenRefreshingTheDetailsFails(): void
    {
        $instance = $this->getMockBuilder($this->instanceClass)
            ->disableOriginalConstructor()
            ->onlyMethods(['initializeFields', 'skipPush', 'receivePushCheckDuplicates'])
            ->getMock();
        $instance->method('skipPush')->willReturn(false);
        $instance->method('receivePushCheckDuplicates')->willReturn(true);

        $payment = $this->getFakeMock(\Magento\Sales\Model\Order\Payment::class)->getMock();
        $this->helperMock->method('getTransactionAdditionalInfo')->willReturn(['a' => 'b']);
        $paymentRepository = $this->getFakeMock('Magento\Sales\Api\OrderPaymentRepositoryInterface')->getMock();
        $paymentRepository->method('save')->willThrowException(new \RuntimeException('db down'));

        $this->setProperty('logger', $this->loggerMock, $instance);
        $this->setProperty('helper', $this->helperMock, $instance);
        $this->setProperty('payment', $payment, $instance);
        $this->setProperty('paymentRepository', $paymentRepository, $instance);
        $this->setProperty('pushRequest', $this->duplicatePushRequest(['brq_transactions' => 'TRX']), $instance);

        $this->assertTrue($instance->processPush($this->duplicatePushRequest([])));
    }

    private function duplicatePushRequest(array $data)
    {
        $request = $this->getFakeMock(\Buckaroo\Magento2\Model\RequestPush\HttppostPushRequest::class)->getMock();
        $request->method('getTransactions')->willReturn($data['brq_transactions'] ?? null);
        $request->method('getData')->willReturn($data);

        return $request;
    }

    /**
     * @param array       $flags   [name, value] pairs hasAdditionalInformation answers true for
     * @param string|null $refund
     * @param bool        $hasInvoices
     * @param mixed       $alreadyCaptured
     * @param bool        $expected
     */
    #[DataProvider('magentoInitiatedPushProvider')]
    public function testPushesOfActionsStartedInMagentoAreSkipped(
        array $flags,
        ?string $refund,
        bool $hasInvoices,
        $alreadyCaptured,
        bool $expected
    ): void {
        $payment = $this->createMock(\Magento\Sales\Model\Order\Payment::class);
        $payment->method('getAdditionalInformation')->with('buckaroo_already_captured')->willReturn($alreadyCaptured);
        $order = $this->createMock(Order::class);
        $order->method('hasInvoices')->willReturn($hasInvoices);
        $order->method('getPayment')->willReturn($payment);

        $pushRequest = $this->createMock(\Buckaroo\Magento2\Test\Unit\Stubs\PushRequestInterfaceStub::class);
        $pushRequest->method('getRelatedtransactionRefund')->willReturn($refund);
        $pushRequest->method('hasAdditionalInformation')->willReturnCallback(
            static function ($name, $value) use ($flags): bool {
                foreach ($flags as [$flagName, $flagValue]) {
                    if ($flagName === $name && ($flagValue === $value || in_array($flagValue, (array)$value, true))) {
                        return true;
                    }
                }
                return false;
            }
        );

        $instance = $this->getInstance();
        $this->setProperty('order', $order, $instance);
        $this->setProperty('pushRequest', $pushRequest, $instance);

        $this->assertSame($expected, $this->invoke('skipSpecificTypesOfRequsts', $instance));
    }

    public static function magentoInitiatedPushProvider(): array
    {
        $initiated = ['initiated_by_magento', 1];

        return [
            'capture of an invoiced order' => [[$initiated, ['service_action_from_magento', 'capture']], null, true, null, true],
            'capture already recorded' => [[$initiated, ['service_action_from_magento', 'capture']], null, false, true, true],
            'capture that left no invoice' => [[$initiated, ['service_action_from_magento', 'capture']], null, false, null, false],
            'cancel authorize' => [[$initiated, ['service_action_from_magento', 'cancelauthorize']], null, false, null, true],
            'cancel authorize with a refund' => [[$initiated, ['service_action_from_magento', 'cancelauthorize']], 'REFUND', false, null, false],
            'cancel authorize not from Magento' => [[['service_action_from_magento', 'cancelauthorize']], null, false, null, false],
            'pay from Magento' => [[$initiated, ['service_action_from_magento', 'pay']], null, false, null, false],
        ];
    }

    public function testADuplicatePushKeepsTheDetailsOfOtherTransactions(): void
    {
        $data = ['brq_transactions' => 'TRX', 'brq_service_ideal_consumername' => 'J Doe'];
        $payment = $this->createMock(\Magento\Sales\Model\Order\Payment::class);
        $payment->method('getAdditionalInformation')->willReturn(['EARLIER' => ['a' => 'b']]);
        $payment->expects($this->once())->method('setAdditionalInformation')
            ->with('raw_details_info', ['EARLIER' => ['a' => 'b'], 'TRX' => $data]);
        $this->helperMock->method('getTransactionAdditionalInfo')->willReturn($data);

        $instance = $this->getInstance();
        $this->setProperty('payment', $payment, $instance);
        $this->setProperty('pushRequest', $this->duplicatePushRequest($data), $instance);

        $this->invoke('refreshStoredTransactionDetails', $instance);
    }

    public function testADuplicatePushWithoutTransactionOrPaymentStoresNothing(): void
    {
        $payment = $this->createMock(\Magento\Sales\Model\Order\Payment::class);
        $payment->expects($this->never())->method('setAdditionalInformation');

        $instance = $this->getInstance();
        $this->setProperty('payment', $payment, $instance);
        $this->setProperty('pushRequest', $this->duplicatePushRequest([]), $instance);
        $this->invoke('refreshStoredTransactionDetails', $instance);

        $this->setProperty('payment', null, $instance);
        $this->setProperty('pushRequest', $this->duplicatePushRequest(['brq_transactions' => 'TRX']), $instance);
        $this->invoke('refreshStoredTransactionDetails', $instance);
    }

    /**
     * @param mixed $methodConfig
     * @param int   $expected
     */
    #[DataProvider('invoiceHandlingProvider')]
    public function testInvoiceHandlingFollowsTheMethodBeforeTheAccount($methodConfig, int $expected): void
    {
        $methodInstance = $this->createMock(\Magento\Payment\Model\MethodInterface::class);
        $methodInstance->method('getConfigData')->with('create_invoice_after_shipment')->willReturn($methodConfig);
        $payment = $this->createMock(\Magento\Sales\Model\Order\Payment::class);
        $payment->method('getMethodInstance')->willReturn($methodInstance);
        $this->configAccountMock->method('getInvoiceHandling')->willReturn(InvoiceHandlingOptions::SHIPMENT);

        $instance = $this->getInstance();
        $this->setProperty('payment', $payment, $instance);
        $this->setProperty('order', $this->createMock(Order::class), $instance);

        $this->assertSame($expected, $this->invoke('detectInvoiceHandlingMode', $instance));
    }

    public static function invoiceHandlingProvider(): array
    {
        return [
            'method: on shipment' => ['1', 2],
            'method: on payment' => ['0', 1],
            'method empty, account decides' => ['', 2],
            'method not set, account decides' => [null, 2],
        ];
    }

    public function testAStatusMessageRefreshesTheOrderBeforeItIsRecorded(): void
    {
        $order = $this->createMock(Order::class);
        $order->expects($this->once())->method('addCommentToStatusHistory')->with('Payment successful');
        $this->orderRequestServiceMock->expects($this->once())->method('loadOrder');

        $pushRequest = $this->createMock(\Buckaroo\Magento2\Test\Unit\Stubs\PushRequestInterfaceStub::class);
        $pushRequest->method('getStatusMessage')->willReturn('Payment successful');
        $pushRequest->method('getStatusCode')->willReturn('190');

        $instance = $this->getInstance();
        $this->setProperty('order', $order, $instance);
        $this->setProperty('pushRequest', $pushRequest, $instance);

        $this->invoke('setOrderStatusMessage', $instance);
    }

    public function testARelatedPartialPaymentMakesThePushAPartOfASplitPayment(): void
    {
        $this->groupTransactionMock->method('getGroupTransactionByTrxId')->willReturn(new \Magento\Framework\DataObject());
        $pushRequest = $this->createMock(\Buckaroo\Magento2\Test\Unit\Stubs\PushRequestInterfaceStub::class);
        $pushRequest->method('getTransactions')->willReturn('PART_KEY');
        $pushRequest->method('getRelatedtransactionPartialpayment')->willReturn('GROUP_KEY');

        $instance = $this->getInstance();
        $this->setProperty('pushRequest', $pushRequest, $instance);

        $this->assertTrue($this->invoke('isPartialPaymentPush', $instance));
    }

    public function testTheNewStatusComesFromTheStatusCodeOfThePush(): void
    {
        $order = $this->createMock(Order::class);
        $pushRequest = $this->createMock(\Buckaroo\Magento2\Test\Unit\Stubs\PushRequestInterfaceStub::class);
        $pushRequest->method('getStatusCode')->willReturn('190');
        $this->orderStatusFactoryMock->method('get')->with('190', $order)->willReturn('buckaroo_paid');

        $instance = $this->getInstance();
        $this->setProperty('order', $order, $instance);
        $this->setProperty('pushRequest', $pushRequest, $instance);

        $this->assertSame('buckaroo_paid', $this->invoke('getNewStatus', $instance));
    }

    public function testAnUnappliedSuccessfulPushOnAnOrderWithACentPaidIsNotRefused(): void
    {
        $instance = $this->getFakeMock($this->instanceClass)
            ->onlyMethods(['applySucceededPush', 'sendOrderEmail'])
            ->disableOriginalConstructor()
            ->getMock();
        $instance->method('applySucceededPush')->willReturn(false);
        $instance->expects($this->never())->method('sendOrderEmail');
        $this->setProperty('order', $this->orderWith(false, 0.01), $instance);

        $this->assertFalse($instance->processSucceededPush('processing', 'Success'));
    }

    public function testASucceededPushRecordsTheInvoiceAndMovesTheOrderToProcessing(): void
    {
        $instance = $this->getAppliedPushInstance(true);
        $instance->expects($this->once())->method('setBuckarooReservationNumber');
        $instance->expects($this->once())->method('setSpecificPaymentDetails');
        $instance->expects($this->once())->method('processSucceededPushAuthorization');
        $this->orderRequestServiceMock->expects($this->once())->method('updateOrderStatus')
            ->with('processing', 'buckaroo_paid', 'Payment Push Status: Success');

        $this->assertTrue($this->invokeArgs('applySucceededPush', ['buckaroo_paid', 'Success'], $instance));
        $this->assertTrue($this->getProperty('dontSaveOrderUponSuccessPush', $instance));
    }

    public function testASucceededPushWhoseInvoiceFailsLeavesTheOrderToTheFinalSave(): void
    {
        $instance = $this->getAppliedPushInstance(false);
        $this->setProperty('dontSaveOrderUponSuccessPush', true, $instance);
        $this->orderRequestServiceMock->expects($this->never())->method('updateOrderStatus');

        $this->assertFalse($this->invokeArgs('applySucceededPush', ['buckaroo_paid', 'Success'], $instance));
        $this->assertFalse($this->getProperty('dontSaveOrderUponSuccessPush', $instance));
    }

    /**
     * @param bool $invoiceSaved
     *
     * @return \Buckaroo\Magento2\Model\Push\DefaultProcessor|\PHPUnit\Framework\MockObject\MockObject
     */
    private function getAppliedPushInstance(bool $invoiceSaved)
    {
        $instance = $this->getFakeMock($this->instanceClass)
            ->onlyMethods([
                'setBuckarooReservationNumber',
                'getPaymentDetails',
                'setSpecificPaymentDetails',
                'isCaptureTransaction',
                'canPushInvoice',
                'saveInvoice',
                'processSucceededPushAuthorization',
            ])
            ->disableOriginalConstructor()
            ->getMock();
        $instance->method('getPaymentDetails')->willReturn(['amount' => 10.0, 'description' => 'Payment Push Status: Success']);
        $instance->method('isCaptureTransaction')->willReturn(false);
        $instance->method('canPushInvoice')->willReturn(true);
        $instance->expects($this->once())->method('saveInvoice')->willReturn($invoiceSaved);

        $this->setProperty('logger', $this->loggerMock, $instance);
        $this->setProperty('orderRequestService', $this->orderRequestServiceMock, $instance);
        $this->setProperty('payment', $this->createMock(\Magento\Sales\Model\Order\Payment::class), $instance);
        $this->setProperty('order', $this->createMock(Order::class), $instance);

        return $instance;
    }

    /**
     * @param string $pushCurrency
     * @param float  $amount
     * @param float  $rate
     * @param bool   $settlesTheOrder
     * @param float  $expected
     */
    #[DataProvider('baseCurrencyAmountProvider')]
    public function testAPushedAmountIsConvertedToTheBaseCurrency(
        string $pushCurrency,
        float $amount,
        float $rate,
        bool $settlesTheOrder,
        float $expected
    ): void {
        $order = $this->createMock(Order::class);
        $order->method('getBaseCurrencyCode')->willReturn('EUR');
        $order->method('getOrderCurrencyCode')->willReturn('USD');
        $order->method('getTotalDue')->willReturn(110.0);
        $order->method('getBaseTotalDue')->willReturn(100.01);
        $order->method('getBaseToOrderRate')->willReturn($rate);
        $this->helperMock->method('areEqualAmounts')->willReturn($settlesTheOrder);

        $pushRequest = $this->createMock(\Buckaroo\Magento2\Test\Unit\Stubs\PushRequestInterfaceStub::class);
        $pushRequest->method('getCurrency')->willReturn($pushCurrency);

        $instance = $this->getInstance();
        $this->setProperty('order', $order, $instance);
        $this->setProperty('pushRequest', $pushRequest, $instance);

        $this->assertSame($expected, $this->invokeArgs('toBaseCurrencyAmount', [$amount], $instance));
    }

    public static function baseCurrencyAmountProvider(): array
    {
        return [
            'base currency in lower case' => ['eur', 40.0, 1.1, false, 40.0],
            'order currency, settles the order' => ['USD', 110.0, 1.1, true, 100.01],
            'order currency in lower case' => ['usd', 10.0, 3.0, false, 3.33],
            'order currency, rounded up' => ['USD', 20.0, 3.0, false, 6.67],
            'order currency without rate' => ['USD', 20.0, 0.0, false, 0.0],
            'unknown currency' => ['GBP', 20.0, 1.1, false, 0.0],
        ];
    }

    /**
     * @param bool $force
     * @param bool $canInvoice
     * @param bool $hasInvoices
     * @param bool $created
     */
    #[DataProvider('invoiceCreationProvider')]
    public function testAnInvoiceIsOnlyCreatedWhenTheOrderCanBeInvoiced(
        bool $force,
        bool $canInvoice,
        bool $hasInvoices,
        bool $created
    ): void {
        [$instance, $order, $payment] = $this->getInvoicingInstance('');
        $order->method('canInvoice')->willReturn($canInvoice);
        $order->method('hasInvoices')->willReturn($hasInvoices);
        $this->setProperty('forceInvoice', $force, $instance);
        $payment->expects($created ? $this->once() : $this->never())->method('registerCaptureNotification');

        $this->assertSame($created, $this->invoke('saveInvoice', $instance));
    }

    public static function invoiceCreationProvider(): array
    {
        return [
            'can be invoiced' => [false, true, false, true],
            'cannot be invoiced' => [false, false, false, false],
            'already invoiced' => [false, true, true, false],
            'forced' => [true, false, true, true],
        ];
    }

    public function testAnInvoiceWithoutTransactionKeyOnlyRegistersTheCapture(): void
    {
        [$instance, $order, $payment, $paymentRepository] = $this->getInvoicingInstance('');
        $order->method('canInvoice')->willReturn(true);
        $instance->expects($this->once())->method('addTransactionData');
        $payment->expects($this->once())->method('registerCaptureNotification')->with(100.0);
        $paymentRepository->expects($this->once())->method('save')->with($payment);
        $order->expects($this->never())->method('__call');

        $this->assertTrue($this->invoke('saveInvoice', $instance));
        $this->assertFalse($this->getProperty('dontSaveOrderUponSuccessPush', $instance));
    }

    /**
     * @param string $method
     * @param bool   $marked
     */
    #[DataProvider('klarnaCaptureProvider')]
    public function testAKlarnaCaptureIsMarkedAsCaptured(string $method, bool $marked): void
    {
        [$instance, $order, $payment, $paymentRepository] = $this->getInvoicingInstance('CAPTURE_KEY');
        $order->method('canInvoice')->willReturn(true);
        $order->method('getInvoiceCollection')->willReturn([]);
        $payment->method('getMethod')->willReturn($method);
        $set = [];
        $payment->method('setAdditionalInformation')->willReturnCallback(
            static function ($key, $value) use (&$set, $payment) {
                $set[$key] = $value;
                return $payment;
            }
        );
        $paymentRepository->expects($this->exactly($marked ? 2 : 1))->method('save')->with($payment);
        $order->expects($this->once())->method('__call')->with('setIsInProcess', [true]);
        $this->getProperty('orderRepository', $instance)->expects($this->once())->method('save')->with($order);

        $this->assertTrue($this->invoke('saveInvoice', $instance));
        $this->assertTrue($this->getProperty('dontSaveOrderUponSuccessPush', $instance));
        $this->assertSame(
            $marked ? ['buckaroo_capture_transaction_key' => 'CAPTURE_KEY', 'buckaroo_already_captured' => true] : [],
            $set
        );
    }

    public static function klarnaCaptureProvider(): array
    {
        return [
            'Klarna KP' => ['buckaroo_magento2_klarnakp', true],
            'Klarna' => ['buckaroo_magento2_klarna', true],
            'iDEAL' => ['buckaroo_magento2_ideal', false],
        ];
    }

    /**
     * @param string|null $invoiceNumber
     * @param bool        $isGroupTransaction
     * @param bool        $paid
     */
    #[DataProvider('groupTransactionInvoiceProvider')]
    public function testTheInvoiceOfAGroupTransactionIsPaid(?string $invoiceNumber, bool $isGroupTransaction, bool $paid): void
    {
        [$instance, $order] = $this->getInvoicingInstance('CAPTURE_KEY', $invoiceNumber);
        $invoice = $this->createMock(Invoice::class);
        $invoice->method('getTransactionId')->willReturn('CAPTURE_KEY');
        $invoice->method('getEmailSent')->willReturn(false);
        $invoice->method('getId')->willReturn(12);
        $invoice->expects($paid ? $this->once() : $this->never())->method('setState')->with(Invoice::STATE_PAID);
        $order->method('canInvoice')->willReturn(true);
        $order->method('getInvoiceCollection')->willReturn([$invoice]);
        $this->groupTransactionMock->method('isGroupTransaction')->willReturn($isGroupTransaction);
        $this->configAccountMock->method('getInvoiceEmail')->willReturn(true);
        $this->orderRequestServiceMock->expects($this->once())->method('sendInvoiceEmail')->with($invoice, true);

        $this->assertTrue($this->invoke('saveInvoice', $instance));
    }

    public static function groupTransactionInvoiceProvider(): array
    {
        return [
            'group transaction' => ['300000123', true, true],
            'single transaction' => ['300000123', false, false],
            'no invoice number' => [null, true, false],
        ];
    }

    /**
     * @param string      $transactionKey
     * @param string|null $invoiceNumber
     *
     * @return array
     */
    private function getInvoicingInstance(string $transactionKey, ?string $invoiceNumber = null): array
    {
        $payment = $this->createMock(\Magento\Sales\Model\Order\Payment::class);
        $order = $this->createMock(Order::class);
        $pushRequest = $this->createMock(\Buckaroo\Magento2\Test\Unit\Stubs\PushRequestInterfaceStub::class);
        $pushRequest->method('getInvoiceNumber')->willReturn($invoiceNumber);
        $paymentRepository = $this->createMock(\Magento\Sales\Api\OrderPaymentRepositoryInterface::class);

        $instance = $this->getFakeMock($this->instanceClass)
            ->onlyMethods([
                'addTransactionData',
                'detectInvoiceHandlingMode',
                'resolveCaptureNotificationAmount',
                'getTransactionKey',
            ])
            ->getMock();
        $instance->method('addTransactionData')->willReturn($payment);
        $instance->method('detectInvoiceHandlingMode')->willReturn(InvoiceHandlingOptions::PAYMENT);
        $instance->method('resolveCaptureNotificationAmount')->willReturn(100.0);
        $instance->method('getTransactionKey')->willReturn($transactionKey);

        $this->setProperty('logger', $this->loggerMock, $instance);
        $this->setProperty('order', $order, $instance);
        $this->setProperty('payment', $payment, $instance);
        $this->setProperty('pushRequest', $pushRequest, $instance);
        $this->setProperty('configAccount', $this->configAccountMock, $instance);
        $this->setProperty('orderRequestService', $this->orderRequestServiceMock, $instance);
        $this->setProperty('groupTransaction', $this->groupTransactionMock, $instance);
        $this->setProperty('invoiceRepository', $this->createMock(InvoiceRepositoryInterface::class), $instance);
        $this->setProperty('paymentRepository', $paymentRepository, $instance);
        $this->setProperty('orderRepository', $this->createMock(\Magento\Sales\Api\OrderRepositoryInterface::class), $instance);

        return [$instance, $order, $payment, $paymentRepository];
    }

    /**
     * @param bool  $canProcess
     * @param bool  $hasInvoices
     * @param float $totalPaid
     * @param bool  $updated
     */
    #[DataProvider('pendingPushProvider')]
    public function testAPendingPushMovesOnlyAnUnpaidOrderToPendingPayment(
        bool $canProcess,
        bool $hasInvoices,
        float $totalPaid,
        bool $updated
    ): void {
        $instance = $this->getFakeMock($this->instanceClass)
            ->onlyMethods(['canProcessPendingPush'])
            ->disableOriginalConstructor()
            ->getMock();
        $instance->method('canProcessPendingPush')->willReturn($canProcess);

        $order = $this->orderWith($hasInvoices, $totalPaid);
        $order->method('getEmailSent')->willReturn(false);
        $this->configAccountMock->method('getOrderConfirmationEmail')->willReturn(true);
        $this->orderRequestServiceMock->expects($updated ? $this->once() : $this->never())
            ->method('sendOrderEmail')->with($order);
        $payment = $this->createMock(\Magento\Sales\Model\Order\Payment::class);
        $payment->method('getMethodInstance')->willReturn($this->createMock(\Magento\Payment\Model\MethodInterface::class));

        $this->orderRequestServiceMock->expects($updated ? $this->once() : $this->never())
            ->method('updateOrderStatus')
            ->with('pending_payment', 'buckaroo_pending', $this->anything());
        $this->setProperty('order', $order, $instance);
        $this->setProperty('payment', $payment, $instance);
        $this->setProperty('pushRequest', $this->createMock(\Buckaroo\Magento2\Test\Unit\Stubs\PushRequestInterfaceStub::class), $instance);
        $this->setProperty('orderRequestService', $this->orderRequestServiceMock, $instance);
        $this->setProperty('logger', $this->loggerMock, $instance);
        $this->setProperty('configAccount', $this->configAccountMock, $instance);

        $method = new \ReflectionMethod($instance, 'processPendingPaymentPush');
        $this->assertTrue($method->invoke($instance, 'buckaroo_pending', 'Waiting for consumer'));
    }

    public static function pendingPushProvider(): array
    {
        return [
            'unpaid order' => [true, false, 0.0, true],
            'method without pending handling' => [false, false, 0.0, false],
            'invoiced, nothing paid' => [true, true, 0.0, false],
            'one cent paid' => [true, false, 0.01, false],
        ];
    }

    public function testNoPendingEmailForAnOrderThatIsCancelledButNotYetInTheCanceledState(): void
    {
        $order = $this->createMock(Order::class);
        $order->method('isCanceled')->willReturn(true);
        $order->method('getState')->willReturn(Order::STATE_NEW);
        $order->method('getEmailSent')->willReturn(false);
        $payment = $this->createMock(\Magento\Sales\Model\Order\Payment::class);
        $payment->method('getMethodInstance')->willReturn($this->createMock(\Magento\Payment\Model\MethodInterface::class));
        $this->configAccountMock->method('getOrderConfirmationEmail')->willReturn(true);
        $this->orderRequestServiceMock->expects($this->never())->method('sendOrderEmail');

        $instance = $this->getInstance();
        $this->setProperty('order', $order, $instance);
        $this->setProperty('payment', $payment, $instance);
        $this->setProperty('pushRequest', $this->createMock(\Buckaroo\Magento2\Test\Unit\Stubs\PushRequestInterfaceStub::class), $instance);

        $this->invoke('processPendingPaymentEmail', $instance);
    }

    /**
     * @param string|null $pushAmount
     * @param string      $paymentAction
     * @param mixed       $invoiceHandling
     * @param float       $amount
     * @param string      $description
     */
    #[DataProvider('paymentDetailsProvider')]
    public function testPaymentDetailsDescribeWhatHappenedToTheAmount(
        ?string $pushAmount,
        string $paymentAction,
        $invoiceHandling,
        float $amount,
        string $description
    ): void {
        $currency = $this->createMock(\Magento\Directory\Model\Currency::class);
        $currency->method('formatTxt')->willReturnCallback(static fn($value) => '€' . number_format((float)$value, 2));
        $payment = $this->createMock(\Magento\Sales\Model\Order\Payment::class);
        $payment->method('getAdditionalInformation')->willReturn($invoiceHandling);
        $methodInstance = $this->createMock(\Magento\Payment\Model\MethodInterface::class);
        $methodInstance->method('getConfigData')->willReturnMap([['payment_action', null, $paymentAction]]);
        $payment->method('getMethodInstance')->willReturn($methodInstance);

        $order = $this->createMock(Order::class);
        $order->method('getPayment')->willReturn($payment);
        $order->method('getTotalDue')->willReturn(50.0);
        $order->method('getBaseTotalDue')->willReturn(45.0);
        $order->method('getOrderCurrencyCode')->willReturn('EUR');
        $order->method('getBaseCurrencyCode')->willReturn('EUR');
        $order->method('getOrderCurrency')->willReturn($currency);

        $pushRequest = $this->createMock(\Buckaroo\Magento2\Test\Unit\Stubs\PushRequestInterfaceStub::class);
        $pushRequest->method('getAmount')->willReturn($pushAmount);
        $pushRequest->method('getCurrency')->willReturn('EUR');

        $instance = $this->getInstance();
        $this->setProperty('order', $order, $instance);
        $this->setProperty('payment', $payment, $instance);
        $this->setProperty('pushRequest', $pushRequest, $instance);
        $this->setProperty('dontSaveOrderUponSuccessPush', true, $instance);

        $details = $this->invokeArgs('getPaymentDetails', ['Success'], $instance);

        $this->assertSame(['amount', 'description'], array_keys($details));
        $this->assertSame($amount, $details['amount']);
        $this->assertSame($description, (string)$details['description']);
        $this->assertFalse($this->getProperty('dontSaveOrderUponSuccessPush', $instance));
    }

    public static function paymentDetailsProvider(): array
    {
        return [
            'paid, invoice on payment' => [
                '50.00', 'order', '1', 45.0,
                'Payment Push Status: <strong>Success</strong><br/>Total amount of €45.00 has been paid',
            ],
            'authorized, invoice on shipment' => [
                '40.00', 'authorize', '2', 40.0,
                'Authorization status: <strong>Success</strong><br/>Total amount of €40.00 has been authorized.'
                . ' Payment will be captured when order is shipped.',
            ],
            'authorized, no amount in the push' => [
                null, 'authorize', '1', 50.0,
                'Authorization status: <strong>Success</strong><br/>Total amount of €50.00 has been authorized.'
                . ' Please create an invoice to capture the authorized amount.',
            ],
        ];
    }

    /**
     * @param bool        $withPush
     * @param string|null $pushCurrency
     * @param string|null $orderCurrency
     * @param string      $expected
     */
    #[DataProvider('paymentCurrencyProvider')]
    public function testThePaymentCurrencyIsTheOneOfThePush(
        bool $withPush,
        ?string $pushCurrency,
        ?string $orderCurrency,
        string $expected
    ): void {
        $order = $this->createMock(Order::class);
        $order->method('getOrderCurrencyCode')->willReturn($orderCurrency);
        $order->method('getBaseCurrencyCode')->willReturn('EUR');
        $pushRequest = $this->createMock(\Buckaroo\Magento2\Test\Unit\Stubs\PushRequestInterfaceStub::class);
        $pushRequest->method('getCurrency')->willReturn($pushCurrency);

        $instance = $this->getInstance();
        $this->setProperty('order', $order, $instance);
        $this->setProperty('pushRequest', $withPush ? $pushRequest : null, $instance);

        $this->assertSame($expected, $this->invoke('getPaymentCurrencyCode', $instance));
    }

    public static function paymentCurrencyProvider(): array
    {
        return [
            'push currency' => [true, 'USD', 'GBP', 'USD'],
            'order currency' => [true, null, 'GBP', 'GBP'],
            'base currency' => [true, null, null, 'EUR'],
            'no push yet' => [false, null, 'GBP', 'GBP'],
        ];
    }
}
