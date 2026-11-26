<?php
declare(strict_types=1);

namespace Buckaroo\Magento2\Test\Unit\Model\Push;

/**
 * @SuppressWarnings(PHPMD.TooManyFields)
 */
class PayPerEmailProcessorTest extends \Buckaroo\Magento2\Test\BaseTest
{
    protected $instanceClass = 'Buckaroo\Magento2\Model\Push\PayPerEmailProcessor';

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
    private $configPayPerEmailMock;
    private $currencyFactoryMock;
    private $orderRepositoryMock;
    private $paymentMethodCodeResolverMock;

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
        $this->configPayPerEmailMock = $this->getFakeMock('Buckaroo\Magento2\Model\ConfigProvider\Method\PayPerEmail')->getMock();
        $this->currencyFactoryMock = $this->getFakeMock('Magento\Directory\Model\CurrencyFactory')->getMock();
        $this->orderRepositoryMock = $this->createMock(\Magento\Sales\Api\OrderRepositoryInterface::class);
        $this->paymentMethodCodeResolverMock = $this->createMock(
            \Buckaroo\Magento2\Model\PaymentMethodCodeResolver::class
        );
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
            'configPayPerEmail' => $this->configPayPerEmailMock,
            'currencyFactory' => $this->currencyFactoryMock,
            'orderRepository' => $this->orderRepositoryMock,
            'paymentRepository' => $this->createMock(\Magento\Sales\Api\OrderPaymentRepositoryInterface::class),
            'invoiceRepository' => $this->createMock(\Magento\Sales\Api\InvoiceRepositoryInterface::class),
            'groupTransactionResource' => $this->createMock(\Buckaroo\Magento2\Model\ResourceModel\GroupTransaction::class),
            'transactionRepository' => $this->createMock(\Magento\Sales\Api\TransactionRepositoryInterface::class),
            'searchCriteriaBuilder' => $this->createMock(\Magento\Framework\Api\SearchCriteriaBuilder::class),
            'paymentMethodCodeResolver' => $this->paymentMethodCodeResolverMock,
        ] + $args);
    }

    public function testGetNewStatusConvertsInitialB2BWaitingOnConsumerPushToSuccess(): void
    {
        $instance = $this->getInstance();

        $orderMock = $this->getFakeMock('Magento\Sales\Model\Order')->getMock();
        $pushRequestMock = $this->getFakeMock('Buckaroo\Magento2\Api\Data\PushRequestInterface')->getMock();

        $pushRequestMock->method('getStatusCode')->willReturn('792');
        $pushRequestMock->method('getTransactionMethod')->willReturn('payperemail');
        $pushRequestMock->method('getAdditionalInformation')->willReturnMap([
            ['frompayperemail', '1'],
        ]);

        $this->orderStatusFactoryMock->expects($this->once())
            ->method('get')
            ->with(792, $orderMock)
            ->willReturn('pending_payment');

        $this->configPayPerEmailMock->expects($this->once())
            ->method('isEnabledB2B')
            ->willReturn(true);

        $this->pushTransactionTypeMock->method('getStatusKey')
            ->willReturn('BUCKAROO_MAGENTO2_STATUSCODE_WAITING_ON_CONSUMER');

        $this->pushTransactionTypeMock->expects($this->once())
            ->method('setStatusKey')
            ->with('BUCKAROO_MAGENTO2_STATUSCODE_SUCCESS');

        $this->configAccountMock->expects($this->once())
            ->method('getOrderStatusSuccess')
            ->willReturn('processing');

        $this->setProperty('order', $orderMock, $instance);
        $this->setProperty('pushRequest', $pushRequestMock, $instance);

        $this->assertSame('processing', $this->invoke('getNewStatus', $instance));
    }

    public function testCanProcessPendingPushReturnsFalseForAlreadyFinalizedOrder(): void
    {
        $instance = $this->getInstance();

        $orderMock = $this->getFakeMock('Magento\Sales\Model\Order')->getMock();
        $orderMock->method('getIncrementId')->willReturn('100000001');
        $orderMock->method('getState')->willReturn('processing');
        $orderMock->method('getTotalPaid')->willReturn(100.0);
        $orderMock->method('hasInvoices')->willReturn(true);

        $this->setProperty('order', $orderMock, $instance);

        $this->assertFalse($this->invoke('canProcessPendingPush', $instance));
    }

    public function testCanProcessPendingPushReturnsTrueForUnpaidOrder(): void
    {
        $instance = $this->getInstance();

        $orderMock = $this->getFakeMock('Magento\Sales\Model\Order')->getMock();
        $orderMock->method('getState')->willReturn('pending_payment');
        $orderMock->method('getTotalPaid')->willReturn(0.0);
        $orderMock->method('hasInvoices')->willReturn(false);

        $this->setProperty('order', $orderMock, $instance);

        $this->assertTrue($this->invoke('canProcessPendingPush', $instance));
    }

    public function testInvoiceShouldBeSavedB2BSecondPushRegistersCaptureWithoutIntermediateSave(): void
    {
        $instance = $this->getInstance();

        $pushRequestMock = $this->getFakeMock(\Buckaroo\Magento2\Test\Unit\Stubs\PushRequestInterfaceStub::class)
            ->getMock();
        $pushRequestMock->method('getAdditionalInformation')->willReturnMap([
            ['frompayperemail', '1'],
        ]);
        $pushRequestMock->method('getTransactionMethod')->willReturn('payperemail');
        $pushRequestMock->method('getTransactions')->willReturn('');
        $pushRequestMock->method('getDatarequest')->willReturn('');
        $pushRequestMock->method('getRelatedtransactionRefund')->willReturn('');

        $this->configPayPerEmailMock->method('isEnabledB2B')->willReturn(true);

        $paymentMock = $this->getFakeMock('Magento\Sales\Model\Order\Payment')->getMock();
        $paymentMock->method('isSameCurrency')->willReturn(true);
        $paymentMock->method('isCaptureFinal')->willReturn(true);
        $paymentMock->expects($this->once())->method('registerCaptureNotification')->with(100.0);

        $orderMock = $this->getFakeMock('Magento\Sales\Model\Order')->getMock();
        $orderMock->method('getGrandTotal')->willReturn(100.0);
        $orderMock->expects($this->once())->method('setState')->with('complete')->willReturnSelf();
        // no intermediate save — the capture, comment and state are persisted
        // by the updateOrderStatus save that always follows in processSucceededPush
        $orderMock->expects($this->never())->method('save');
        $this->orderRepositoryMock->expects($this->never())->method('save');

        $this->setProperty('order', $orderMock, $instance);
        $this->setProperty('payment', $paymentMock, $instance);
        $this->setProperty('pushRequest', $pushRequestMock, $instance);

        $paymentDetails = ['description' => 'Payment status : success'];
        $this->assertFalse($this->invokeArgs('invoiceShouldBeSaved', [&$paymentDetails], $instance));
    }

    /**
     * The B2B capture is registered for the amount the push reports as paid.
     */
    public function testInvoiceShouldBeSavedB2BRegistersThePushedAmountAndLeavesAnUnsettledOrderOpen(): void
    {
        $instance = $this->getInstance();

        $pushRequestMock = $this->getFakeMock(\Buckaroo\Magento2\Test\Unit\Stubs\PushRequestInterfaceStub::class)
            ->getMock();
        $pushRequestMock->method('getAdditionalInformation')->willReturnMap([
            ['frompayperemail', '1'],
        ]);
        $pushRequestMock->method('getTransactionMethod')->willReturn('payperemail');
        $pushRequestMock->method('getTransactions')->willReturn('');
        $pushRequestMock->method('getDatarequest')->willReturn('');
        $pushRequestMock->method('getRelatedtransactionRefund')->willReturn('');
        $pushRequestMock->method('getAmount')->willReturn('40.00');
        $pushRequestMock->method('getCurrency')->willReturn('EUR');

        $this->configPayPerEmailMock->method('isEnabledB2B')->willReturn(true);

        $paymentMock = $this->getFakeMock('Magento\Sales\Model\Order\Payment')->getMock();
        $paymentMock->method('isSameCurrency')->willReturn(true);
        $paymentMock->method('isCaptureFinal')->willReturn(true);
        $paymentMock->method('getIsFraudDetected')->willReturn(true);
        $paymentMock->expects($this->once())->method('registerCaptureNotification')->with(40.0);

        $orderMock = $this->getFakeMock('Magento\Sales\Model\Order')->getMock();
        $orderMock->method('getGrandTotal')->willReturn(100.0);
        $orderMock->method('getBaseTotalDue')->willReturn(100.0);
        $orderMock->method('getBaseCurrencyCode')->willReturn('EUR');
        $orderMock->method('getOrderCurrencyCode')->willReturn('EUR');
        $orderMock->expects($this->never())->method('setState');
        $orderMock->expects($this->never())->method('addCommentToStatusHistory');

        $this->setProperty('order', $orderMock, $instance);
        $this->setProperty('payment', $paymentMock, $instance);
        $this->setProperty('pushRequest', $pushRequestMock, $instance);

        $paymentDetails = ['description' => 'Payment status : success'];
        $this->assertFalse($this->invokeArgs('invoiceShouldBeSaved', [&$paymentDetails], $instance));
    }

    /**
     * BTI-1316: a PayLink paid in full with one giftcard is closed by a push that names no payment
     * service of its own, only the PayLink. Overwriting the giftcard method with PayLink at that
     * point left the order on a method that cannot be refunded online.
     */
    public function testPayLinkPushKeepsAnAlreadyResolvedPaymentMethod(): void
    {
        $instance = $this->getInstance();

        $pushRequestMock = $this->getFakeMock(\Buckaroo\Magento2\Test\Unit\Stubs\PushRequestInterfaceStub::class)
            ->getMock();
        $pushRequestMock->method('getAdditionalInformation')->willReturnMap([
            ['service_action_from_magento', 'frompaylink'],
        ]);
        $this->pushTransactionTypeMock->method('getStatusKey')
            ->willReturn('BUCKAROO_MAGENTO2_STATUSCODE_SUCCESS');

        $paymentMock = $this->getFakeMock('Magento\Sales\Model\Order\Payment')->getMock();
        $paymentMock->method('getAdditionalInformation')
            ->with('buckaroo_actual_payment_method')
            ->willReturn('vvvgiftcard');
        $paymentMock->method('getMethod')->willReturn('buckaroo_magento2_giftcards');
        $paymentMock->expects($this->never())->method('setMethod');

        $this->paymentMethodCodeResolverMock->method('resolve')
            ->with('vvvgiftcard')
            ->willReturn('buckaroo_magento2_giftcards');

        $this->orderRepositoryMock->expects($this->never())->method('save');

        $this->setProperty('order', $this->getFakeMock('Magento\Sales\Model\Order')->getMock(), $instance);
        $this->setProperty('payment', $paymentMock, $instance);
        $this->setProperty('pushRequest', $pushRequestMock, $instance);

        $this->invoke('receivePushCheckPayLink', $instance);
    }

    public function testPayLinkPushStampsPayPerEmailWhenNoMethodResolvedYet(): void
    {
        $instance = $this->getInstance();

        $pushRequestMock = $this->getFakeMock(\Buckaroo\Magento2\Test\Unit\Stubs\PushRequestInterfaceStub::class)
            ->getMock();
        $pushRequestMock->method('getAdditionalInformation')->willReturnMap([
            ['service_action_from_magento', 'frompaylink'],
        ]);
        $this->pushTransactionTypeMock->method('getStatusKey')
            ->willReturn('BUCKAROO_MAGENTO2_STATUSCODE_SUCCESS');

        $paymentMock = $this->getFakeMock('Magento\Sales\Model\Order\Payment')->getMock();
        $paymentMock->method('getAdditionalInformation')->willReturn(null);
        $paymentMock->expects($this->once())->method('setMethod')->with('buckaroo_magento2_payperemail');

        $this->orderRepositoryMock->expects($this->once())->method('save');

        $this->setProperty('order', $this->getFakeMock('Magento\Sales\Model\Order')->getMock(), $instance);
        $this->setProperty('payment', $paymentMock, $instance);
        $this->setProperty('pushRequest', $pushRequestMock, $instance);

        $this->invoke('receivePushCheckPayLink', $instance);
    }

    /**
     * A PayLink push carries brq_SERVICE_general_paylink alongside the service actually paid with.
     * Matching on the key shape alone picked "general" and wrote that onto the order.
     */
    public function testFindServiceInPushDataSkipsServiceKeysThatAreNotPaymentMethods(): void
    {
        $instance = $this->getInstance();

        $pushRequestMock = $this->getFakeMock(\Buckaroo\Magento2\Test\Unit\Stubs\PushRequestInterfaceStub::class)
            ->getMock();
        $pushRequestMock->method('getData')->willReturn([
            'brq_amount'                                  => '30.25',
            'brq_service_general_paylink'                 => 'https://testcheckout.buckaroo.nl/html/',
            'brq_service_payperemail_expirationdate'      => '2027-09-02',
            'brq_service_vvvgiftcard_maskedgiftcardnumber' => '00000000*******0001',
        ]);

        $this->paymentMethodCodeResolverMock->method('resolve')->willReturnMap([
            ['general', null],
            ['vvvgiftcard', 'buckaroo_magento2_giftcards'],
        ]);

        $this->setProperty('pushRequest', $pushRequestMock, $instance);

        $this->assertSame('vvvgiftcard', $this->invoke('findServiceInPushData', $instance));
    }

    public function testUnresolvableServiceCodeIsNotWrittenOntoThePayment(): void
    {
        $instance = $this->getInstance();

        $paymentMock = $this->getFakeMock('Magento\Sales\Model\Order\Payment')->getMock();
        $paymentMock->expects($this->never())->method('setAdditionalInformation');
        $paymentMock->expects($this->never())->method('setMethod');

        $orderMock = $this->getFakeMock('Magento\Sales\Model\Order')->getMock();
        $orderMock->method('getIncrementId')->willReturn('300000013');

        $this->paymentMethodCodeResolverMock->method('resolve')->with('general')->willReturn(null);
        $this->orderRepositoryMock->expects($this->never())->method('save');

        $this->setProperty('order', $orderMock, $instance);
        $this->setProperty('payment', $paymentMock, $instance);

        $this->assertFalse($this->invokeArgs(
            'saveActualPaymentMethodAndKeyForRefund',
            ['SOME_GROUP_TRANSACTION_KEY', 'general'],
            $instance
        ));
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

        $pushRequest = $this->getFakeMock(\Buckaroo\Magento2\Model\RequestPush\HttppostPushRequest::class)
            ->getMock();
        $pushRequest->method('getAdditionalInformation')->willReturn(null);

        $this->setProperty('logger', $this->loggerMock, $instance);
        $this->setProperty('pushRequest', $pushRequest, $instance);
        $this->setProperty('pushTransactionType', $this->pushTransactionTypeMock, $instance);
        $this->setProperty('order', $this->getFakeMock('Magento\Sales\Model\Order')->getMock(), $instance);

        $this->assertTrue($instance->processPush($pushRequest));
    }

    public function testAPayPerEmailCancelIsSkippedWhenTheCronCancelsInstead(): void
    {
        $order = $this->createOrder('complete');
        $order->expects($this->never())->method('getState');
        $this->buckarooStatusCodeMock->method('getFailedStatuses')
            ->willReturn(['BUCKAROO_MAGENTO2_STATUSCODE_FAILED']);
        $this->configPayPerEmailMock->method('getEnabledCronCancelPPE')->willReturn(true);
        $this->pushTransactionTypeMock->method('getStatusKey')->willReturn('BUCKAROO_MAGENTO2_STATUSCODE_FAILED');
        $this->orderRequestServiceMock->method('getOrderByRequest')->willReturn($order);
        $this->orderRequestServiceMock->expects($this->never())->method('setOrderNotificationNote');
        $this->orderRepositoryMock->expects($this->never())->method('save');

        $pushRequest = $this->createPushRequest(['getStatusCode' => '490', 'getTransactionMethod' => 'ideal']);

        $this->assertTrue($this->getInstance()->processPush($pushRequest));
    }

    public function testAPaidPayLinkOnACompleteOrderIsStampedAsPayPerEmailAndOtherwiseIgnored(): void
    {
        $payment = $this->createPayment([], 'buckaroo_magento2_payperemail');
        $payment->expects($this->once())->method('setMethod')->with('buckaroo_magento2_payperemail');
        $order = $this->createOrder('complete', $payment);
        $order->expects($this->never())->method('setState');
        $this->pushTransactionTypeMock->method('getStatusKey')->willReturn('BUCKAROO_MAGENTO2_STATUSCODE_SUCCESS');
        $this->orderRequestServiceMock->method('getOrderByRequest')->willReturn($order);
        $this->orderRequestServiceMock->expects($this->once())->method('setOrderNotificationNote')
            ->with($this->callback(static fn($note) => (string)$note === 'The order has already been processed.'));
        $this->orderRepositoryMock->expects($this->once())->method('save')->with($order);

        $pushRequest = $this->createPushRequest(
            ['getStatusCode' => '190', 'getTransactions' => 'PPE_KEY'],
            ['service_action_from_magento' => 'frompaylink']
        );

        $this->assertTrue($this->getInstance()->processPush($pushRequest));
    }

    public function testASecondB2BPushWithAnotherMethodReopensACompleteOrder(): void
    {
        $payment = $this->createPayment(
            ['buckaroo_original_transaction_key' => 'PPE_KEY'],
            'buckaroo_magento2_payperemail'
        );
        $calls = [];
        $payment->method('setAdditionalInformation')->willReturnCallback(
            function ($key, $value) use (&$calls, $payment) {
                $calls[$key] = $value;
                return $payment;
            }
        );
        $payment->expects($this->once())->method('setMethod')->with('buckaroo_magento2_ideal');
        $order = $this->createOrder('complete', $payment);
        $order->expects($this->once())->method('setState')->with('processing');
        $this->pushTransactionTypeMock->method('getStatusKey')->willReturn('BUCKAROO_MAGENTO2_STATUSCODE_SUCCESS');
        $this->paymentMethodCodeResolverMock->method('resolve')->with('ideal')->willReturn('buckaroo_magento2_ideal');
        $this->configPayPerEmailMock->method('isEnabledB2B')->willReturn(true);
        $this->orderRequestServiceMock->method('getOrderByRequest')->willReturn($order);
        $this->orderRequestServiceMock->expects($this->never())->method('setOrderNotificationNote');
        $this->orderRepositoryMock->expects($this->exactly(2))->method('save')->with($order);

        $pushRequest = $this->createPushRequest([
            'getStatusCode' => '190',
            'getTransactions' => 'IDEAL_KEY',
            'getTransactionMethod' => 'iDEAL',
        ]);

        $this->assertTrue($this->getInstance()->processPush($pushRequest));
        $this->assertSame(
            ['buckaroo_actual_payment_method' => 'ideal', 'buckaroo_actual_payment_transaction_key' => 'IDEAL_KEY'],
            $calls
        );
    }

    public function testAnUnsuccessfulPushOnACompleteB2BOrderLeavesItComplete(): void
    {
        $payment = $this->createPayment([], 'buckaroo_magento2_payperemail');
        $payment->expects($this->never())->method('setMethod');
        $order = $this->createOrder('complete', $payment);
        $order->expects($this->never())->method('setState');
        $this->pushTransactionTypeMock->method('getStatusKey')
            ->willReturn('BUCKAROO_MAGENTO2_STATUSCODE_WAITING_ON_CONSUMER');
        $this->configPayPerEmailMock->method('isEnabledB2B')->willReturn(true);
        $this->orderRequestServiceMock->method('getOrderByRequest')->willReturn($order);
        $this->orderRequestServiceMock->expects($this->once())->method('setOrderNotificationNote');
        $this->orderRepositoryMock->expects($this->never())->method('save');

        $pushRequest = $this->createPushRequest(['getStatusCode' => '792', 'getTransactionMethod' => 'ideal']);

        $this->assertTrue($this->getInstance()->processPush($pushRequest));
    }

    public function testThePayPerEmailMethodIsDerivedFromTheServiceInTheRawPush(): void
    {
        $payment = $this->createPayment(
            ['buckaroo_original_transaction_key' => 'PPE_KEY'],
            'buckaroo_magento2_payperemail'
        );
        $payment->expects($this->once())->method('setMethod')->with('buckaroo_magento2_ideal');
        $order = $this->createOrder('new', $payment);
        $this->pushTransactionTypeMock->method('getStatusKey')->willReturn('BUCKAROO_MAGENTO2_STATUSCODE_SUCCESS');
        $this->paymentMethodCodeResolverMock->method('resolve')->willReturnMap([
            ['bancontact', 'buckaroo_magento2_bancontact'],
            ['ideal', 'buckaroo_magento2_ideal'],
        ]);
        $this->orderRepositoryMock->expects($this->once())->method('save')->with($order);

        $instance = $this->getInstance();
        $this->setProperty('order', $order, $instance);
        $this->setProperty('payment', $payment, $instance);
        $this->setProperty('pushRequest', $this->createPushRequest(
            ['getTransactions' => 'IDEAL_KEY', 'getTransactionMethod' => 'payperemail'],
            [],
            ['xbrq_service_bancontact_x' => '1', 'brq_SERVICE_iDEAL_consumerIssuer' => 'ABN AMRO']
        ), $instance);

        $this->assertTrue($this->invoke('setPaymentMethodIfDifferent', $instance));
    }

    public function testTheStoredActualMethodIsUsedWhenTheRawPushNamesNone(): void
    {
        $payment = $this->createPayment(
            ['buckaroo_original_transaction_key' => 'PPE_KEY', 'isPayPerEmail' => '1', 'buckaroo_actual_payment_method' => 'IDEAL'],
            'buckaroo_magento2_ideal'
        );
        $payment->expects($this->once())->method('setMethod')->with('buckaroo_magento2_ideal');
        $payment->expects($this->exactly(2))->method('setAdditionalInformation')->willReturnCallback(
            function ($key, $value) use ($payment) {
                $expected = [
                    'buckaroo_actual_payment_method' => 'ideal',
                    'buckaroo_actual_payment_transaction_key' => 'IDEAL_KEY',
                ];
                $this->assertSame($expected[$key], $value);
                return $payment;
            }
        );
        $this->pushTransactionTypeMock->method('getStatusKey')->willReturn('BUCKAROO_MAGENTO2_STATUSCODE_SUCCESS');
        $this->paymentMethodCodeResolverMock->method('resolve')->willReturnMap([
            ['IDEAL', 'buckaroo_magento2_ideal'],
            ['ideal', 'buckaroo_magento2_ideal'],
        ]);

        $instance = $this->getInstance();
        $this->setProperty('order', $this->createOrder('new', $payment), $instance);
        $this->setProperty('payment', $payment, $instance);
        $this->setProperty('pushRequest', $this->createPushRequest(['getTransactions' => 'IDEAL_KEY']), $instance);

        $this->assertTrue($this->invoke('setPaymentMethodIfDifferent', $instance));
    }

    public function testThePayPerEmailPushItselfDoesNotChangeTheMethod(): void
    {
        $payment = $this->createPayment(
            ['buckaroo_original_transaction_key' => 'PPE_KEY'],
            'buckaroo_magento2_payperemail'
        );
        $payment->expects($this->never())->method('setMethod');
        $this->pushTransactionTypeMock->method('getStatusKey')->willReturn('BUCKAROO_MAGENTO2_STATUSCODE_SUCCESS');

        $instance = $this->getInstance();
        $this->setProperty('order', $this->createOrder('new', $payment), $instance);
        $this->setProperty('payment', $payment, $instance);
        $this->setProperty('pushRequest', $this->createPushRequest(['getTransactions' => 'PPE_KEY']), $instance);

        $this->assertFalse($this->invoke('setPaymentMethodIfDifferent', $instance));
    }

    /**
     * @param array  $additional
     * @param string $statusKey
     * @param bool   $stamped
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('payLinkProvider')]
    public function testOnlyAPaidPayLinkIsStampedAsPayPerEmail(array $additional, string $statusKey, bool $stamped): void
    {
        $payment = $this->createPayment([]);
        $payment->expects($stamped ? $this->once() : $this->never())->method('setMethod')
            ->with('buckaroo_magento2_payperemail');
        $this->pushTransactionTypeMock->method('getStatusKey')->willReturn($statusKey);
        // An empty stored method is never treated as resolved
        $this->paymentMethodCodeResolverMock->method('resolve')->willReturn('buckaroo_magento2_ideal');

        $instance = $this->getInstance();
        $this->setProperty('order', $this->createOrder('new', $payment), $instance);
        $this->setProperty('payment', $payment, $instance);
        $this->setProperty('pushRequest', $this->createPushRequest([], $additional), $instance);

        $this->invoke('receivePushCheckPayLink', $instance);
    }

    public static function payLinkProvider(): array
    {
        $success = 'BUCKAROO_MAGENTO2_STATUSCODE_SUCCESS';

        return [
            'frompaylink flag' => [['frompaylink' => '1'], $success, true],
            'service action' => [['service_action_from_magento' => 'frompaylink'], $success, true],
            'not a PayLink' => [[], $success, false],
            'unpaid PayLink' => [['frompaylink' => '1'], 'BUCKAROO_MAGENTO2_STATUSCODE_FAILED', false],
        ];
    }

    /**
     * @param string|null $fromPayPerEmail
     * @param string|null $method
     * @param bool        $b2b
     * @param bool        $expected
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('b2bProvider')]
    public function testRecognisesAB2BPayPerEmailPush(?string $fromPayPerEmail, ?string $method, bool $b2b, bool $expected): void
    {
        $this->configPayPerEmailMock->method('isEnabledB2B')->willReturn($b2b);

        $instance = $this->getInstance();
        $this->setProperty('order', $this->createOrder('new'), $instance);
        $this->setProperty('pushRequest', $this->createPushRequest(
            ['getTransactionMethod' => $method],
            ['frompayperemail' => $fromPayPerEmail]
        ), $instance);

        $this->assertSame($expected, $instance->isPayPerEmailB2BModePush());
    }

    public static function b2bProvider(): array
    {
        return [
            'B2B push' => ['1', 'payperemail', true, true],
            'not from Pay Per Email' => [null, 'payperemail', true, false],
            'other method' => ['1', 'ideal', true, false],
            'no method' => ['1', null, true, false],
            'B2B disabled' => ['1', 'payperemail', false, false],
        ];
    }

    public function testAWaitingPushOutsideB2BIsNotTheInitialB2BPush(): void
    {
        $this->pushTransactionTypeMock->method('getStatusKey')
            ->willReturn('BUCKAROO_MAGENTO2_STATUSCODE_WAITING_ON_CONSUMER');

        $instance = $this->getInstance();
        $this->setProperty('order', $this->createOrder('new'), $instance);
        $this->setProperty('pushRequest', $this->createPushRequest(['getTransactionMethod' => 'ideal']), $instance);

        $this->assertFalse($instance->isPayPerEmailB2BModePushInitial());
    }

    public function testAnOrdinaryPushSavesTheInvoice(): void
    {
        $payment = $this->createPayment([]);
        $payment->expects($this->never())->method('registerCaptureNotification');

        $instance = $this->getInstance();
        $this->setProperty('order', $this->createOrder('new', $payment), $instance);
        $this->setProperty('payment', $payment, $instance);
        $this->setProperty('pushRequest', $this->createPushRequest(['getTransactionMethod' => 'ideal']), $instance);

        $paymentDetails = ['description' => 'Payment status : success'];
        $this->assertTrue($this->invokeArgs('invoiceShouldBeSaved', [&$paymentDetails], $instance));
    }

    public function testASettledB2BPushCompletesTheOrderAndLinksItsInvoices(): void
    {
        $this->configPayPerEmailMock->method('isEnabledB2B')->willReturn(true);

        $payment = $this->createPayment([]);
        $payment->method('isSameCurrency')->willReturn(true);
        $payment->method('isCaptureFinal')->willReturn(true);
        $payment->expects($this->once())->method('registerCaptureNotification')->with(100.0);

        $invoice = $this->createMock(\Magento\Sales\Model\Order\Invoice::class);
        $invoice->expects($this->once())->method('setTransactionId')->with('B2B_KEY');
        $invoiceRepository = $this->createMock(\Magento\Sales\Api\InvoiceRepositoryInterface::class);
        $invoiceRepository->expects($this->once())->method('save')->with($invoice);

        $order = $this->createOrder('new', $payment);
        $order->method('getGrandTotal')->willReturn(100.0);
        $order->method('getInvoiceCollection')->willReturn([$invoice]);
        $order->expects($this->once())->method('setState')->with('complete');
        $order->expects($this->once())->method('addCommentToStatusHistory')
            ->with('Payment status : success', 'complete');

        $instance = $this->getInstance();
        $this->setProperty('invoiceRepository', $invoiceRepository, $instance);
        $this->setProperty('order', $order, $instance);
        $this->setProperty('payment', $payment, $instance);
        $this->setProperty('pushRequest', $this->createPushRequest(
            ['getTransactionMethod' => 'payperemail', 'getTransactions' => 'B2B_KEY'],
            ['frompayperemail' => '1']
        ), $instance);

        $paymentDetails = ['description' => 'Payment status : success'];
        $this->assertFalse($this->invokeArgs('invoiceShouldBeSaved', [&$paymentDetails], $instance));
    }

    /**
     * @param string $state
     * @param float  $totalPaid
     * @param bool   $hasInvoices
     * @param bool   $expected
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('pendingPushProvider')]
    public function testAPendingPushIsIgnoredOnceTheOrderIsPaid(
        string $state,
        float $totalPaid,
        bool $hasInvoices,
        bool $expected
    ): void {
        $order = $this->createOrder($state);
        $order->method('getTotalPaid')->willReturn($totalPaid);
        $order->method('hasInvoices')->willReturn($hasInvoices);

        $instance = $this->getInstance();
        $this->setProperty('order', $order, $instance);

        $this->assertSame($expected, $this->invoke('canProcessPendingPush', $instance));
    }

    public static function pendingPushProvider(): array
    {
        return [
            'complete' => ['complete', 0.0, false, false],
            'closed' => ['closed', 0.0, false, false],
            'new with an invoice' => ['new', 0.0, true, false],
            'new and paid' => ['new', 50.0, false, false],
            'new with a fraction paid' => ['new', 0.0001, false, true],
            'new and unpaid' => ['new', 0.0, false, true],
        ];
    }

    public function testPaymentDetailsOfAnInvoicedPaymentUseTheBaseAmountDue(): void
    {
        $order = $this->createOrder('new', $this->createPayment([]));
        $order->method('getTotalDue')->willReturn(110.0);
        $order->method('getBaseTotalDue')->willReturn(100.0);
        $order->method('getBaseCurrencyCode')->willReturn('USD');
        $order->method('getOrderCurrencyCode')->willReturn('EUR');
        $currency = $this->createMock(\Magento\Directory\Model\Currency::class);
        $currency->method('formatTxt')->with(100.0)->willReturn('$100.00');
        $order->method('getBaseCurrency')->willReturn($currency);

        $instance = $this->getInstance();
        $this->setProperty('order', $order, $instance);
        $this->setProperty('payment', $this->createPaymentWithAction('order'), $instance);
        $this->setProperty('pushRequest', $this->createPushRequest(['getAmount' => '110.00']), $instance);

        $this->setProperty('dontSaveOrderUponSuccessPush', true, $instance);

        $details = $this->invokeArgs('getPaymentDetails', ['Success'], $instance);

        $this->assertFalse($this->getProperty('dontSaveOrderUponSuccessPush', $instance));
        $this->assertSame(100.0, $details['amount']);
        $this->assertSame(
            'Payment Push Status: <strong>Success</strong><br/>Total amount of $100.00 has been paid',
            (string)$details['description']
        );
    }

    public function testPaymentDetailsOfAnAuthorizationUseThePushedAmount(): void
    {
        $order = $this->createOrder('new', $this->createPayment(['invoice_handling' => '1']));
        $order->method('getTotalDue')->willReturn(110.0);
        $order->method('getOrderCurrencyCode')->willReturn('EUR');
        $currency = $this->createMock(\Magento\Directory\Model\Currency::class);
        $currency->method('formatTxt')->with(40.0)->willReturn('€40.00');
        $order->method('getOrderCurrency')->willReturn($currency);

        $instance = $this->getInstance();
        $this->setProperty('order', $order, $instance);
        $this->setProperty('payment', $this->createPaymentWithAction('authorize'), $instance);
        $this->setProperty('pushRequest', $this->createPushRequest(['getAmount' => '40.00', 'getCurrency' => 'EUR']), $instance);

        $details = $this->invokeArgs('getPaymentDetails', ['Success'], $instance);

        $this->assertSame(40.0, $details['amount']);
        $this->assertSame(
            'Authorization status: <strong>Success</strong><br/>Total amount of €40.00 has been authorized.'
            . ' Please create an invoice to capture the authorized amount.',
            (string)$details['description']
        );
    }

    public function testPaymentDetailsOfTheInitialB2BPushHaveNoDescription(): void
    {
        $order = $this->createOrder('new');
        $order->method('getTotalDue')->willReturn(110.0);
        $order->method('getBaseTotalDue')->willReturn(110.0);
        $order->method('getBaseCurrencyCode')->willReturn('EUR');
        $currency = $this->createMock(\Magento\Directory\Model\Currency::class);
        $currency->method('formatTxt')->willReturn('€110.00');
        $order->method('getBaseCurrency')->willReturn($currency);

        $instance = $this->getInstance();
        $this->setProperty('order', $order, $instance);
        $this->setProperty('payment', $this->createPaymentWithAction('order'), $instance);
        $this->setProperty('pushRequest', $this->createPushRequest([]), $instance);
        $this->setProperty('isPayPerEmailB2BModePushInitial', true, $instance);

        $details = $this->invokeArgs('getPaymentDetails', ['Success'], $instance);

        $this->assertSame(['amount' => 110.0, 'description' => ''], $details);
    }

    /**
     * @param array $getters
     * @param array $additional getAdditionalInformation key => value
     * @param array $data       raw push data
     */
    private function createPushRequest(array $getters, array $additional = [], array $data = [])
    {
        $defaults = [
            'getStatusCode' => null,
            'getTransactions' => null,
            'getTransactionMethod' => null,
            'getDatarequest' => null,
            'getRelatedtransactionRefund' => null,
            'getAmount' => null,
            'getCurrency' => null,
            'getStatusMessage' => null,
        ];
        $pushRequest = $this->getFakeMock(\Buckaroo\Magento2\Test\Unit\Stubs\PushRequestInterfaceStub::class)
            ->getMock();
        foreach ($getters + $defaults as $method => $value) {
            $pushRequest->method($method)->willReturn($value);
        }
        $pushRequest->method('getAdditionalInformation')->willReturnCallback(
            static fn($name) => $additional[$name] ?? null
        );
        $pushRequest->method('hasAdditionalInformation')->willReturn(false);
        $pushRequest->method('getData')->willReturn($data);

        return $pushRequest;
    }

    /**
     * @param array       $info
     * @param string|null $method
     */
    private function createPayment(array $info, ?string $method = null)
    {
        $payment = $this->getFakeMock('Magento\Sales\Model\Order\Payment')->getMock();
        $payment->method('getAdditionalInformation')->willReturnCallback(
            static fn($key) => $info[$key] ?? null
        );
        $payment->method('getMethod')->willReturn($method);

        return $payment;
    }

    /**
     * @param string $paymentAction
     */
    private function createPaymentWithAction(string $paymentAction)
    {
        $methodInstance = $this->createMock(\Magento\Payment\Model\MethodInterface::class);
        $methodInstance->method('getConfigData')->with('payment_action')->willReturn($paymentAction);

        $payment = $this->createPayment([]);
        $payment->method('getMethodInstance')->willReturn($methodInstance);

        return $payment;
    }

    /**
     * @param string     $state
     * @param mixed|null $payment
     */
    private function createOrder(string $state, $payment = null)
    {
        $order = $this->getFakeMock('Magento\Sales\Model\Order')->getMock();
        $order->method('getState')->willReturn($state);
        $order->method('getIncrementId')->willReturn('300000013');
        $order->method('getPayment')->willReturn($payment ?? $this->createPayment([]));

        return $order;
    }

    /**
     * @param string $state
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('finalStateProvider')]
    public function testAPushOnAFinalOrderIsAcknowledged(string $state): void
    {
        $order = $this->createOrder($state, $this->createPayment([], 'buckaroo_magento2_payperemail'));
        $this->pushTransactionTypeMock->method('getStatusKey')->willReturn('BUCKAROO_MAGENTO2_STATUSCODE_FAILED');
        $this->orderRequestServiceMock->method('getOrderByRequest')->willReturn($order);
        $this->orderRequestServiceMock->expects($this->once())->method('setOrderNotificationNote');
        $this->orderRepositoryMock->expects($this->never())->method('save');

        $pushRequest = $this->createPushRequest(['getStatusCode' => '490']);

        $this->assertTrue($this->getInstance()->processPush($pushRequest));
    }

    public static function finalStateProvider(): array
    {
        return [
            'canceled' => ['canceled'],
            'complete' => ['complete'],
            'closed' => ['closed'],
            'holded' => ['holded'],
        ];
    }

    /**
     * @param string|null $method
     * @param string      $statusKey
     * @param bool        $cronCancel
     * @param bool        $skipped
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('cronCancelProvider')]
    public function testOnlyAFailedPushIsLeftToTheCancelCron(
        ?string $method,
        string $statusKey,
        bool $cronCancel,
        bool $skipped
    ): void {
        $this->buckarooStatusCodeMock->method('getFailedStatuses')
            ->willReturn(['BUCKAROO_MAGENTO2_STATUSCODE_FAILED']);
        $this->configPayPerEmailMock->method('getEnabledCronCancelPPE')->willReturn($cronCancel);
        $this->pushTransactionTypeMock->method('getStatusKey')->willReturn($statusKey);

        $payment = $this->createPayment([]);
        $instance = $this->getInstance();
        $this->setProperty('order', $this->createOrder('new', $payment), $instance);
        $this->setProperty('payment', $payment, $instance);
        $this->setProperty('pushRequest', $this->createPushRequest(['getTransactionMethod' => $method]), $instance);

        $this->assertSame($skipped, $this->invoke('skipPush', $instance));
    }

    public static function cronCancelProvider(): array
    {
        $failed = 'BUCKAROO_MAGENTO2_STATUSCODE_FAILED';

        return [
            'failed, cron cancels' => ['ideal', $failed, true, true],
            'failed, cron disabled' => ['ideal', $failed, false, false],
            'paid' => ['ideal', 'BUCKAROO_MAGENTO2_STATUSCODE_SUCCESS', true, false],
            'no transaction method' => [null, $failed, true, false],
        ];
    }

    /**
     * @param array       $info
     * @param string|null $method
     * @param string      $transactionKey
     * @param string|null $pushMethod
     * @param array       $rawData
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('methodUnchangedProvider')]
    public function testThePaymentMethodIsNotChanged(
        array $info,
        ?string $method,
        string $transactionKey,
        ?string $pushMethod,
        array $rawData = []
    ): void {
        $payment = $this->createPayment($info, $method);
        $payment->expects($this->never())->method('setMethod');
        $this->pushTransactionTypeMock->method('getStatusKey')->willReturn('BUCKAROO_MAGENTO2_STATUSCODE_SUCCESS');
        $this->paymentMethodCodeResolverMock->method('resolve')->willReturn('buckaroo_magento2_something');

        $instance = $this->getInstance();
        $this->setProperty('order', $this->createOrder('new', $payment), $instance);
        $this->setProperty('payment', $payment, $instance);
        $this->setProperty('pushRequest', $this->createPushRequest(
            ['getTransactions' => $transactionKey, 'getTransactionMethod' => $pushMethod],
            [],
            $rawData
        ), $instance);

        $this->assertFalse($this->invoke('setPaymentMethodIfDifferent', $instance));
    }

    public static function methodUnchangedProvider(): array
    {
        $original = ['buckaroo_original_transaction_key' => 'PPE_KEY'];

        return [
            'Pay Per Email push in other letter case' => [$original, 'buckaroo_magento2_payperemail', 'PPE_KEY', 'PayPerEmail'],
            'not a Pay Per Email order' => [
                $original, 'buckaroo_magento2_ideal', 'OTHER_KEY', null, ['brq_service_ideal_consumerIssuer' => 'ING'],
            ],
            'nothing in the push or stored' => [$original, 'buckaroo_magento2_payperemail', 'OTHER_KEY', null],
        ];
    }

    public function testAPayPerEmailFlaggedOrderTakesTheMethodFromTheRawPush(): void
    {
        $payment = $this->createPayment(
            ['buckaroo_original_transaction_key' => 'PPE_KEY', 'isPayPerEmail' => '1'],
            'buckaroo_magento2_payperemail'
        );
        $payment->expects($this->once())->method('setMethod')->with('buckaroo_magento2_ideal');
        $this->pushTransactionTypeMock->method('getStatusKey')->willReturn('BUCKAROO_MAGENTO2_STATUSCODE_SUCCESS');
        $this->paymentMethodCodeResolverMock->method('resolve')->with('ideal')->willReturn('buckaroo_magento2_ideal');

        $instance = $this->getInstance();
        $this->setProperty('order', $this->createOrder('new', $payment), $instance);
        $this->setProperty('payment', $payment, $instance);
        $this->setProperty('pushRequest', $this->createPushRequest(
            ['getTransactions' => 'IDEAL_KEY'],
            [],
            ['brq_service_ideal_consumerIssuer' => 'ING']
        ), $instance);

        $this->assertTrue($this->invoke('setPaymentMethodIfDifferent', $instance));
    }
}
