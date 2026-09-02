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
}
