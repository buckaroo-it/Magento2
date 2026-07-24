<?php
declare(strict_types=1);

namespace Buckaroo\Magento2\Test\Unit\Model\Push;

use Buckaroo\Magento2\Model\Method\BuckarooAdapter;
use Buckaroo\Magento2\Test\Unit\Stubs\OrderStub;
use Buckaroo\Magento2\Test\Unit\Stubs\PushRequestInterfaceStub;
use Magento\Sales\Model\Order;

/**
 * Minimal stand-in for a Buckaroo payment method instance, mirroring the
 * public static $requestOnVoid flag on BuckarooAdapter that the processor toggles.
 */
class FakeKlarnaMethodInstance
{
    public static $requestOnVoid = true;
}

/**
 * @SuppressWarnings(PHPMD.TooManyFields)
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class KlarnaKpProcessorTest extends \Buckaroo\Magento2\Test\BaseTest
{
    protected $instanceClass = 'Buckaroo\Magento2\Model\Push\KlarnaKpProcessor';

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
    private $klarnakpConfigMock;
    private $escaperMock;
    private $orderRepositoryMock;
    private $paymentRepositoryMock;

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
        $this->klarnakpConfigMock = $this->getFakeMock('Buckaroo\Magento2\Model\ConfigProvider\Method\Klarnakp')->getMock();
        $this->escaperMock = $this->getFakeMock('Magento\Framework\Escaper')->getMock();
        $this->orderRepositoryMock = $this->createMock(\Magento\Sales\Api\OrderRepositoryInterface::class);
        $this->paymentRepositoryMock = $this->createMock(\Magento\Sales\Api\OrderPaymentRepositoryInterface::class);

        // The processor constructor does not receive a CurrencyFactory and falls back to
        // ObjectManager::getInstance(); provide one for the duration of the test.
        $appObjectManagerMock = $this->getFakeMock('Magento\Framework\ObjectManagerInterface')->getMock();
        $appObjectManagerMock->method('get')->willReturn(
            $this->getFakeMock('Magento\Directory\Model\CurrencyFactory')->getMock()
        );
        \Magento\Framework\App\ObjectManager::setInstance($appObjectManagerMock);

        FakeKlarnaMethodInstance::$requestOnVoid = true;
    }

    public function tearDown(): void
    {
        $property = new \ReflectionProperty(\Magento\Framework\App\ObjectManager::class, '_instance');
        $property->setValue(null, null);

        parent::tearDown();
    }

    public function getInstance(array $args = [])
    {
        return parent::getInstance($args + [
            'orderRequestService'   => $this->orderRequestServiceMock,
            'pushTransactionType'   => $this->pushTransactionTypeMock,
            'logger'                => $this->loggerMock,
            'helper'                => $this->helperMock,
            'transaction'           => $this->transactionMock,
            'groupTransaction'      => $this->groupTransactionMock,
            'buckarooStatusCode'    => $this->buckarooStatusCodeMock,
            'orderStatusFactory'    => $this->orderStatusFactoryMock,
            'configAccount'         => $this->configAccountMock,
            'giftCardRefundService' => $this->giftCardRefundServiceMock,
            'uncancelService'       => $this->uncancelServiceMock,
            'resourceConnection'    => $this->resourceConnectionMock,
            'giftcardCollection'    => $this->giftcardCollectionMock,
            'klarnakpConfig'        => $this->klarnakpConfigMock,
            'escaper'               => $this->escaperMock,
            'orderRepository'       => $this->orderRepositoryMock,
            'paymentRepository'     => $this->paymentRepositoryMock,
            'invoiceRepository'     => $this->createMock(\Magento\Sales\Api\InvoiceRepositoryInterface::class),
            'groupTransactionResource' => $this->createMock(\Buckaroo\Magento2\Model\ResourceModel\GroupTransaction::class),
        ]);
    }

    /**
     * Build a push request double.
     *
     * @param array $getters        getter name => return value
     * @param array $trueAdditional list of [name, value] pairs for which
     *                              hasAdditionalInformation() returns true
     * @param array $truePostData   list of [name, value] pairs for which hasPostData() returns true
     */
    private function createPushRequest(
        array $getters = [],
        array $trueAdditional = [],
        array $truePostData = []
    ) {
        $defaults = [
            'getStatusCode'                           => null,
            'getDatarequest'                          => null,
            'getInvoiceNumber'                        => null,
            'getTransactions'                         => null,
            'getRelatedtransactionRefund'             => null,
            'getServiceKlarnakpCaptureid'             => null,
            'getServiceKlarnakpAutopaytransactionkey' => null,
            'getServiceKlarnakpReservationnumber'     => null,
        ];

        $mock = $this->getFakeMock(PushRequestInterfaceStub::class)->getMock();
        foreach (array_merge($defaults, $getters) as $method => $value) {
            $mock->method($method)->willReturn($value);
        }
        $mock->method('hasAdditionalInformation')->willReturnCallback(
            function ($name, $value) use ($trueAdditional) {
                return in_array([$name, $value], $trueAdditional, true);
            }
        );
        $mock->method('hasPostData')->willReturnCallback(
            function ($name, $value) use ($truePostData) {
                return in_array([$name, $value], $truePostData, true);
            }
        );

        return $mock;
    }

    public function testGetTransactionKeyReturnsTransactionsFromPush(): void
    {
        $instance = $this->getInstance();

        $pushRequest = $this->createPushRequest(['getTransactions' => 'TRX-123']);
        $this->setProperty('pushRequest', $pushRequest, $instance);

        $this->assertSame('TRX-123', $this->invoke('getTransactionKey', $instance));
    }

    public function testGetTransactionKeyPrefersAutoPayTransactionKey(): void
    {
        $instance = $this->getInstance();

        $pushRequest = $this->createPushRequest([
            'getTransactions'                         => 'TRX-123',
            'getServiceKlarnakpAutopaytransactionkey' => 'AUTOPAY-456',
        ]);
        $this->setProperty('pushRequest', $pushRequest, $instance);

        $this->assertSame('AUTOPAY-456', $this->invoke('getTransactionKey', $instance));
    }

    private function createOrderWithPayment(
        $paymentMock,
        bool $hasInvoices = false,
        string $state = Order::STATE_NEW
    ) {
        $orderMock = $this->getFakeMock(OrderStub::class)->getMock();
        $orderMock->method('getPayment')->willReturn($paymentMock);
        $orderMock->method('hasInvoices')->willReturn($hasInvoices);
        $orderMock->method('getState')->willReturn($state);
        $orderMock->method('getIncrementId')->willReturn('100000001');

        return $orderMock;
    }

    public function testSkipPushSkipsMagentoCapturePushWhenInvoiceExists(): void
    {
        $instance = $this->getInstance();

        $pushRequest = $this->createPushRequest(
            ['getServiceKlarnakpCaptureid' => 'CAPTURE-1'],
            [['initiated_by_magento', 1], ['service_action_from_magento', 'pay']]
        );
        $paymentMock = $this->getFakeMock('Magento\Sales\Model\Order\Payment')->getMock();
        $paymentMock->method('getAdditionalInformation')->willReturn(null);

        $this->setProperty('pushRequest', $pushRequest, $instance);
        $this->setProperty('order', $this->createOrderWithPayment($paymentMock, true), $instance);

        $this->assertTrue($this->invoke('skipPush', $instance));
    }

    public function testSkipPushSkipsMagentoCapturePushWhenAlreadyCaptured(): void
    {
        $instance = $this->getInstance();

        $pushRequest = $this->createPushRequest(
            ['getServiceKlarnakpCaptureid' => 'CAPTURE-1'],
            [['initiated_by_magento', 1], ['service_action_from_magento', 'pay']]
        );
        $paymentMock = $this->getFakeMock('Magento\Sales\Model\Order\Payment')->getMock();
        $paymentMock->method('getAdditionalInformation')
            ->with('buckaroo_already_captured')
            ->willReturn('1');

        $this->setProperty('pushRequest', $pushRequest, $instance);
        $this->setProperty('order', $this->createOrderWithPayment($paymentMock, false), $instance);

        $this->assertTrue($this->invoke('skipPush', $instance));
    }

    public function testSkipPushProcessesMagentoCapturePushWhenCaptureWasNotProcessed(): void
    {
        $instance = $this->getInstance();

        $pushRequest = $this->createPushRequest(
            ['getServiceKlarnakpCaptureid' => 'CAPTURE-1'],
            [['initiated_by_magento', 1], ['service_action_from_magento', 'pay']]
        );
        $paymentMock = $this->getFakeMock('Magento\Sales\Model\Order\Payment')->getMock();
        $paymentMock->method('getAdditionalInformation')->willReturn(null);

        $this->setProperty('pushRequest', $pushRequest, $instance);
        $this->setProperty('order', $this->createOrderWithPayment($paymentMock, false), $instance);

        $this->assertFalse($this->invoke('skipPush', $instance));
    }

    public function testSkipPushSkipsRedundantAutoPayConfirmationPush(): void
    {
        $instance = $this->getInstance();

        $pushRequest = $this->createPushRequest(
            ['getServiceKlarnakpAutopaytransactionkey' => 'AUTOPAY-456']
        );
        $paymentMock = $this->getFakeMock('Magento\Sales\Model\Order\Payment')->getMock();
        $paymentMock->method('getAdditionalInformation')->willReturn(null);

        $this->setProperty('pushRequest', $pushRequest, $instance);
        $this->setProperty('order', $this->createOrderWithPayment($paymentMock, true), $instance);

        $this->assertTrue($this->invoke('skipPush', $instance));
    }

    public function testSkipPushFallsBackToParentWhenNoKlarnaConditionApplies(): void
    {
        $instance = $this->getInstance();

        $pushRequest = $this->createPushRequest(
            ['getServiceKlarnakpAutopaytransactionkey' => 'AUTOPAY-456']
        );
        // No invoice, not captured, no skip_push counter: nothing to skip.
        $paymentMock = $this->getFakeMock('Magento\Sales\Model\Order\Payment')->getMock();
        $paymentMock->method('getAdditionalInformation')->willReturn(null);

        $this->setProperty('pushRequest', $pushRequest, $instance);
        $this->setProperty('order', $this->createOrderWithPayment($paymentMock, false), $instance);
        $this->setProperty('payment', $paymentMock, $instance);

        $this->assertFalse($this->invoke('skipPush', $instance));
    }

    public function testSetBuckarooReservationNumberSavesNumberFromPush(): void
    {
        $instance = $this->getInstance();

        $pushRequest = $this->createPushRequest(
            ['getServiceKlarnakpReservationnumber' => 'RESERVATION-1']
        );

        $orderMock = $this->getFakeMock(OrderStub::class)->getMock();
        $orderMock->method('getIncrementId')->willReturn('100000001');
        $orderMock->expects($this->once())
            ->method('setBuckarooReservationNumber')
            ->with('RESERVATION-1')
            ->willReturnSelf();
        $orderMock->expects($this->never())->method('save');
        $this->orderRepositoryMock->expects($this->once())->method('save')->with($orderMock);

        $this->setProperty('pushRequest', $pushRequest, $instance);
        $this->setProperty('order', $orderMock, $instance);

        $this->assertTrue($this->invoke('setBuckarooReservationNumber', $instance));
    }

    public function testSetBuckarooReservationNumberReturnsFalseWithoutNumberInPush(): void
    {
        $instance = $this->getInstance();

        $pushRequest = $this->createPushRequest();

        $orderMock = $this->getFakeMock(OrderStub::class)->getMock();
        $orderMock->method('getIncrementId')->willReturn('100000001');
        $orderMock->expects($this->never())->method('setBuckarooReservationNumber');
        $orderMock->expects($this->never())->method('save');

        $this->setProperty('pushRequest', $pushRequest, $instance);
        $this->setProperty('order', $orderMock, $instance);

        $this->assertFalse($this->invoke('setBuckarooReservationNumber', $instance));
    }

    public function testInvoiceShouldBeSavedDefersInvoiceWhenCreatedAfterShipment(): void
    {
        $instance = $this->getInstance();

        $pushRequest = $this->createPushRequest(
            [],
            [['initiated_by_magento', 1], ['service_action_from_magento', 'pay']],
            [['transaction_method', 'KlarnaKp']]
        );
        $this->klarnakpConfigMock->method('isInvoiceCreatedAfterShipment')->willReturn(true);

        $this->setProperty('pushRequest', $pushRequest, $instance);

        $paymentDetails = [];
        $result = $this->invokeArgs('invoiceShouldBeSaved', [&$paymentDetails], $instance);

        $this->assertFalse($result);
        $this->assertTrue($this->getProperty('dontSaveOrderUponSuccessPush', $instance));
    }

    public function testInvoiceShouldBeSavedWhenInvoiceAfterShipmentIsDisabled(): void
    {
        $instance = $this->getInstance();

        $pushRequest = $this->createPushRequest(
            [],
            [['initiated_by_magento', 1], ['service_action_from_magento', 'pay']],
            [['transaction_method', 'KlarnaKp']]
        );
        $this->klarnakpConfigMock->method('isInvoiceCreatedAfterShipment')->willReturn(false);

        $this->setProperty('pushRequest', $pushRequest, $instance);

        $paymentDetails = [];
        $result = $this->invokeArgs('invoiceShouldBeSaved', [&$paymentDetails], $instance);

        $this->assertTrue($result);
        $this->assertFalse($this->getProperty('dontSaveOrderUponSuccessPush', $instance));
    }

    public function testInvoiceShouldBeSavedForReservePushWithReservationNumber(): void
    {
        $instance = $this->getInstance();

        $pushRequest = $this->createPushRequest(
            ['getServiceKlarnakpReservationnumber' => 'RESERVATION-1'],
            [['initiated_by_magento', 1], ['service_action_from_magento', 'pay']],
            [['transaction_method', 'KlarnaKp']]
        );
        $this->klarnakpConfigMock->method('isInvoiceCreatedAfterShipment')->willReturn(true);

        $this->setProperty('pushRequest', $pushRequest, $instance);

        $paymentDetails = [];
        $result = $this->invokeArgs('invoiceShouldBeSaved', [&$paymentDetails], $instance);

        $this->assertTrue($result);
        $this->assertFalse($this->getProperty('dontSaveOrderUponSuccessPush', $instance));
    }

    private function preparePushAuthorization($instance, string $statusCode, string $orderState)
    {
        $pushRequest = $this->createPushRequest(['getStatusCode' => $statusCode]);

        $paymentMock = $this->getFakeMock('Magento\Sales\Model\Order\Payment')->getMock();
        $paymentMock->method('getMethod')->willReturn('buckaroo_magento2_klarnakp');

        $orderMock = $this->getFakeMock(OrderStub::class)->getMock();
        $orderMock->method('getState')->willReturn($orderState);

        $this->setProperty('pushRequest', $pushRequest, $instance);
        $this->setProperty('order', $orderMock, $instance);
        $this->setProperty('payment', $paymentMock, $instance);

        return $orderMock;
    }

    public function testProcessSucceededPushAuthorizationMovesNewOrderToProcessing(): void
    {
        $instance = $this->getInstance();

        $orderMock = $this->preparePushAuthorization($instance, '190', Order::STATE_NEW);
        $orderMock->expects($this->once())->method('setState')->with(Order::STATE_PROCESSING)->willReturnSelf();
        $orderMock->expects($this->never())->method('save');
        $this->orderRepositoryMock->expects($this->once())->method('save')->with($orderMock);

        $this->invoke('processSucceededPushAuthorization', $instance);
    }

    public function testProcessSucceededPushAuthorizationLeavesCanceledOrderStateUntouched(): void
    {
        $instance = $this->getInstance();

        // Canceled orders are accepted (Klarna allows completion within 48 hours) but the
        // canceled -> new transition is delegated to canUpdateOrderStatus, not done here.
        $orderMock = $this->preparePushAuthorization($instance, '190', Order::STATE_CANCELED);
        $orderMock->expects($this->never())->method('setState');
        $orderMock->expects($this->never())->method('save');

        $this->invoke('processSucceededPushAuthorization', $instance);
    }

    public function testProcessSucceededPushAuthorizationSkipsOrderInFinalState(): void
    {
        $instance = $this->getInstance();

        $orderMock = $this->preparePushAuthorization($instance, '190', Order::STATE_COMPLETE);
        $orderMock->expects($this->never())->method('setState');
        $orderMock->expects($this->never())->method('save');

        $this->invoke('processSucceededPushAuthorization', $instance);
    }

    public function testProcessSucceededPushAuthorizationIgnoresNonSuccessStatus(): void
    {
        $instance = $this->getInstance();

        $orderMock = $this->preparePushAuthorization($instance, '490', Order::STATE_NEW);
        $orderMock->expects($this->never())->method('setState');
        $orderMock->expects($this->never())->method('save');

        $this->invoke('processSucceededPushAuthorization', $instance);
    }

    /**
     * Build the fixture for a Plaza-originated cancel reservation push:
     * status 190, not initiated by Magento, no invoice number, order in processing,
     * and a datarequest transaction that Magento does not know.
     */
    private function preparePlazaCancelPush($instance, string $incomingTrx, array $knownTransactions)
    {
        $pushRequest = $this->createPushRequest([
            'getStatusCode'  => '190',
            'getDatarequest' => $incomingTrx,
        ]);

        $paymentMock = $this->getFakeMock('Magento\Sales\Model\Order\Payment')->getMock();
        $paymentMock->method('getAdditionalInformation')->willReturnMap([
            [BuckarooAdapter::BUCKAROO_ALL_TRANSACTIONS, $knownTransactions],
        ]);

        $orderMock = $this->getFakeMock(OrderStub::class)->getMock();
        $orderMock->method('getState')->willReturn(Order::STATE_PROCESSING);
        $orderMock->method('getIncrementId')->willReturn('100000001');
        $orderMock->method('getPayment')->willReturn($paymentMock);

        $this->setProperty('pushRequest', $pushRequest, $instance);
        $this->setProperty('order', $orderMock, $instance);
        $this->setProperty('payment', $paymentMock, $instance);

        return [$orderMock, $paymentMock];
    }

    public function testProcessPushByStatusCancelsOrderForPlazaCancelReservationPush(): void
    {
        $instance = $this->getInstance();

        [$orderMock, ] = $this->preparePlazaCancelPush($instance, 'PLAZA-TRX', ['KNOWN-TRX' => ['C021']]);
        $orderMock->method('canCancel')->willReturn(false);

        $this->orderStatusFactoryMock->expects($this->once())
            ->method('get')
            ->with(891, $orderMock)
            ->willReturn('buckaroo_cancelled');

        $this->escaperMock->method('escapeHtml')->willReturnArgument(0);

        $expectedDescription = 'Order cancelled via Buckaroo Plaza (KlarnaKp reservation released).'
            . ' Cancel transaction: <a href="https://plaza.buckaroo.nl/Transaction/DataRequest/Details/PLAZA-TRX"'
            . ' target="_blank">PLAZA-TRX</a>.';

        $this->orderRequestServiceMock->expects($this->once())
            ->method('updateOrderStatus')
            ->with(Order::STATE_CANCELED, 'buckaroo_cancelled', $expectedDescription);

        $this->assertTrue($this->invoke('processPushByStatus', $instance));
    }

    public function testProcessPushByStatusPlazaCancelDisablesVoidRequestWhileCancelling(): void
    {
        $instance = $this->getInstance();

        [$orderMock, $paymentMock] = $this->preparePlazaCancelPush(
            $instance,
            'PLAZA-TRX',
            ['KNOWN-TRX' => ['C021']]
        );
        $orderMock->method('canCancel')->willReturn(true);

        $paymentMock->method('getMethodInstance')->willReturn(new FakeKlarnaMethodInstance());
        $paymentMock->expects($this->once())
            ->method('setAdditionalInformation')
            ->with('buckaroo_failed_authorize', 1)
            ->willReturnSelf();
        $paymentMock->expects($this->never())->method('save');
        $this->paymentRepositoryMock->expects($this->once())->method('save')->with($paymentMock);

        $orderMock->expects($this->once())
            ->method('cancel')
            ->willReturnCallback(function () use ($orderMock) {
                // The static void-request flag must be off while the order is cancelled,
                // otherwise cancel() would trigger a void request back to Buckaroo.
                $this->assertFalse(FakeKlarnaMethodInstance::$requestOnVoid);
                return $orderMock;
            });
        $orderMock->expects($this->once())->method('save')->willReturnSelf();

        $this->orderStatusFactoryMock->method('get')->willReturn('buckaroo_cancelled');
        $this->escaperMock->method('escapeHtml')->willReturnArgument(0);
        $this->orderRequestServiceMock->expects($this->once())->method('updateOrderStatus');

        $this->assertTrue($this->invoke('processPushByStatus', $instance));

        // And it must be restored afterwards.
        $this->assertTrue(FakeKlarnaMethodInstance::$requestOnVoid);
    }

    public function testProcessPushByStatusFallsThroughToParentForKnownTransaction(): void
    {
        $instance = $this->getInstance();

        // The incoming datarequest transaction is already known to Magento,
        // so this is not a Plaza cancel reservation push.
        $this->preparePlazaCancelPush($instance, 'KNOWN-TRX', ['KNOWN-TRX' => ['C021']]);

        $this->pushTransactionTypeMock->method('getStatusKey')->willReturn('BUCKAROO_MAGENTO2_STATUSCODE_NEUTRAL');
        $this->pushTransactionTypeMock->method('getStatusMessage')->willReturn('Neutral status message');
        $this->orderStatusFactoryMock->method('get')->willReturn('some_status');

        $this->orderRequestServiceMock->expects($this->never())->method('updateOrderStatus');
        $this->orderRequestServiceMock->expects($this->once())
            ->method('setOrderNotificationNote')
            ->with('Neutral status message');

        $this->assertTrue($this->invoke('processPushByStatus', $instance));
    }
}
