<?php
declare(strict_types=1);

namespace Buckaroo\Magento2\Test\Unit\Model\Push;


use PHPUnit\Framework\Attributes\DataProvider;
use Buckaroo\Magento2\Model\BuckarooStatusCode;
use Magento\Sales\Model\Order;

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

        $this->assertFalse($instance->processSucceededPush('processing', 'Success'));
    }
}
