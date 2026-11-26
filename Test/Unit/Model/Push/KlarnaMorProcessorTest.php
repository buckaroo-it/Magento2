<?php
declare(strict_types=1);

namespace Buckaroo\Magento2\Test\Unit\Model\Push;

use Buckaroo\Magento2\Test\Unit\Stubs\OrderStub;
use Buckaroo\Magento2\Test\Unit\Stubs\PushRequestInterfaceStub;

/**
 * @SuppressWarnings(PHPMD.TooManyFields)
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class KlarnaMorProcessorTest extends \Buckaroo\Magento2\Test\BaseTest
{
    protected $instanceClass = 'Buckaroo\Magento2\Model\Push\KlarnaMorProcessor';

    private const DATAREQUEST_KEY = 'DATAREQUEST_RESERVE_KEY_0000000001';
    private const PAY_KEY = 'PAY_TRANSACTION_KEY_00000000000001';
    private const STATUS_SUCCESS = '190';
    private const STATUS_FAILED = '490';

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
    private $escaperMock;
    private $orderRepositoryMock;
    private $paymentRepositoryMock;
    private $orderManagementMock;

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
        $this->escaperMock = $this->getFakeMock('Magento\Framework\Escaper')->getMock();
        $this->orderRepositoryMock = $this->createMock(\Magento\Sales\Api\OrderRepositoryInterface::class);
        $this->paymentRepositoryMock = $this->createMock(\Magento\Sales\Api\OrderPaymentRepositoryInterface::class);
        $this->orderManagementMock = $this->createMock(\Magento\Sales\Api\OrderManagementInterface::class);
    }

    public function getInstance(array $args = [])
    {
        return parent::getInstance($args + [
            'orderRequestService'      => $this->orderRequestServiceMock,
            'pushTransactionType'      => $this->pushTransactionTypeMock,
            'logger'                   => $this->loggerMock,
            'helper'                   => $this->helperMock,
            'transaction'              => $this->transactionMock,
            'groupTransaction'         => $this->groupTransactionMock,
            'buckarooStatusCode'       => $this->buckarooStatusCodeMock,
            'orderStatusFactory'       => $this->orderStatusFactoryMock,
            'configAccount'            => $this->configAccountMock,
            'giftCardRefundService'    => $this->giftCardRefundServiceMock,
            'uncancelService'          => $this->uncancelServiceMock,
            'resourceConnection'       => $this->resourceConnectionMock,
            'giftcardCollection'       => $this->giftcardCollectionMock,
            'currencyFactory'          => $this->currencyFactoryMock,
            'orderRepository'          => $this->orderRepositoryMock,
            'paymentRepository'        => $this->paymentRepositoryMock,
            'invoiceRepository'        => $this->createMock(\Magento\Sales\Api\InvoiceRepositoryInterface::class),
            'groupTransactionResource' => $this->createMock(\Buckaroo\Magento2\Model\ResourceModel\GroupTransaction::class),
            'transactionRepository'    => $this->createMock(\Magento\Sales\Api\TransactionRepositoryInterface::class),
            'searchCriteriaBuilder'    => $this->createMock(\Magento\Framework\Api\SearchCriteriaBuilder::class),
            'orderManagement'          => $this->orderManagementMock,
            'escaper'                  => $this->escaperMock,
        ]);
    }

    /**
     * Build a push request double for a Magento-initiated Klarna MOR Pay push.
     */
    private function createPayPushRequest(string $statusCode, ?string $transactions)
    {
        $mock = $this->getFakeMock(PushRequestInterfaceStub::class)->getMock();
        $mock->method('getStatusCode')->willReturn($statusCode);
        $mock->method('getTransactions')->willReturn($transactions);
        $mock->method('hasAdditionalInformation')->willReturnCallback(
            function ($name, $value) {
                return in_array(
                    [$name, $value],
                    [['initiated_by_magento', 1], ['service_action_from_magento', 'pay']],
                    true
                );
            }
        );

        return $mock;
    }

    /**
     * @param array $additionalInformation existing payment additional information
     */
    private function createPayment(array $additionalInformation)
    {
        $paymentMock = $this->getFakeMock('Magento\Sales\Model\Order\Payment')->getMock();
        $paymentMock->method('getAdditionalInformation')->willReturnCallback(
            function ($key) use ($additionalInformation) {
                return $additionalInformation[$key] ?? null;
            }
        );

        return $paymentMock;
    }

    private function createOrder($paymentMock, ?string $dataRequestKey = self::DATAREQUEST_KEY)
    {
        $orderMock = $this->getFakeMock(OrderStub::class)->getMock();
        $orderMock->method('getPayment')->willReturn($paymentMock);
        $orderMock->method('getIncrementId')->willReturn('100000001');
        $orderMock->method('getBuckarooDatarequestKey')->willReturn($dataRequestKey);

        return $orderMock;
    }

    private function getInstanceWithState($pushRequest, $paymentMock, $orderMock = null)
    {
        $instance = $this->getInstance();
        $this->setProperty('pushRequest', $pushRequest, $instance);
        $this->setProperty('order', $orderMock ?? $this->createOrder($paymentMock), $instance);
        $this->setProperty('payment', $paymentMock, $instance);

        return $instance;
    }

    public function testSkipPushPersistsCaptureKeyFromSkippedPayPush(): void
    {
        $pushRequest = $this->createPayPushRequest(self::STATUS_SUCCESS, self::PAY_KEY);
        $paymentMock = $this->createPayment([
            'buckaroo_datarequest_key' => self::DATAREQUEST_KEY,
        ]);

        $additionalInformationCalls = [];
        $paymentMock->method('setAdditionalInformation')->willReturnCallback(
            function ($key, $value) use (&$additionalInformationCalls, $paymentMock) {
                $additionalInformationCalls[$key] = $value;
                return $paymentMock;
            }
        );
        $this->paymentRepositoryMock->expects($this->once())->method('save')->with($paymentMock);

        $instance = $this->getInstanceWithState($pushRequest, $paymentMock);

        $this->assertTrue($this->invoke('skipPush', $instance));
        $this->assertSame(self::PAY_KEY, $additionalInformationCalls['buckaroo_capture_transaction_key'] ?? null);
        $this->assertTrue($additionalInformationCalls['buckaroo_already_captured'] ?? null);
    }

    public function testSkipPushRepairsCaptureKeyEqualToDataRequestKey(): void
    {
        $pushRequest = $this->createPayPushRequest(self::STATUS_SUCCESS, self::PAY_KEY);
        $paymentMock = $this->createPayment([
            'buckaroo_datarequest_key' => self::DATAREQUEST_KEY,
            'buckaroo_capture_transaction_key' => self::DATAREQUEST_KEY,
            'buckaroo_already_captured' => true,
        ]);

        $additionalInformationCalls = [];
        $paymentMock->method('setAdditionalInformation')->willReturnCallback(
            function ($key, $value) use (&$additionalInformationCalls, $paymentMock) {
                $additionalInformationCalls[$key] = $value;
                return $paymentMock;
            }
        );
        $this->paymentRepositoryMock->expects($this->once())->method('save')->with($paymentMock);

        $instance = $this->getInstanceWithState($pushRequest, $paymentMock);

        $this->assertTrue($this->invoke('skipPush', $instance));
        $this->assertSame(self::PAY_KEY, $additionalInformationCalls['buckaroo_capture_transaction_key'] ?? null);
    }

    public function testSkipPushKeepsValidExistingCaptureKey(): void
    {
        $pushRequest = $this->createPayPushRequest(self::STATUS_SUCCESS, 'ANOTHER_PAY_KEY_000000000000000002');
        $paymentMock = $this->createPayment([
            'buckaroo_datarequest_key' => self::DATAREQUEST_KEY,
            'buckaroo_capture_transaction_key' => self::PAY_KEY,
            'buckaroo_already_captured' => true,
        ]);

        $paymentMock->expects($this->never())->method('setAdditionalInformation');
        $this->paymentRepositoryMock->expects($this->never())->method('save');

        $instance = $this->getInstanceWithState($pushRequest, $paymentMock);

        $this->assertTrue($this->invoke('skipPush', $instance));
    }

    public function testSkipPushIgnoresFailedPayPush(): void
    {
        $pushRequest = $this->createPayPushRequest(self::STATUS_FAILED, self::PAY_KEY);
        $paymentMock = $this->createPayment([
            'buckaroo_datarequest_key' => self::DATAREQUEST_KEY,
        ]);

        $paymentMock->expects($this->never())->method('setAdditionalInformation');
        $this->paymentRepositoryMock->expects($this->never())->method('save');

        $instance = $this->getInstanceWithState($pushRequest, $paymentMock);

        $this->assertTrue($this->invoke('skipPush', $instance));
    }

    public function testSkipPushIgnoresPayPushWithoutTransactionKey(): void
    {
        $pushRequest = $this->createPayPushRequest(self::STATUS_SUCCESS, null);
        $paymentMock = $this->createPayment([
            'buckaroo_datarequest_key' => self::DATAREQUEST_KEY,
        ]);

        $paymentMock->expects($this->never())->method('setAdditionalInformation');
        $this->paymentRepositoryMock->expects($this->never())->method('save');

        $instance = $this->getInstanceWithState($pushRequest, $paymentMock);

        $this->assertTrue($this->invoke('skipPush', $instance));
    }

    public function testSkipPushDoesNotRecoverACaptureKeyForOtherMagentoActions(): void
    {
        $pushRequest = $this->createPushRequest(
            ['statusCode' => self::STATUS_SUCCESS, 'transactions' => self::PAY_KEY],
            [['initiated_by_magento', 1], ['service_action_from_magento', 'refund']]
        );
        $paymentMock = $this->createPayment(['buckaroo_datarequest_key' => self::DATAREQUEST_KEY]);
        $paymentMock->expects($this->never())->method('setAdditionalInformation');
        $this->paymentRepositoryMock->expects($this->never())->method('save');

        $instance = $this->getInstanceWithState($pushRequest, $paymentMock);

        $this->assertFalse($this->invoke('skipPush', $instance));
    }

    public function testSkipPushComparesTheCaptureKeyWithThePaymentDataRequestKeyFirst(): void
    {
        $pushRequest = $this->createPayPushRequest(self::STATUS_SUCCESS, self::PAY_KEY);
        $paymentMock = $this->createPayment([
            'buckaroo_datarequest_key' => self::DATAREQUEST_KEY,
            'buckaroo_capture_transaction_key' => self::DATAREQUEST_KEY,
        ]);
        $paymentMock->expects($this->exactly(2))->method('setAdditionalInformation');
        $this->paymentRepositoryMock->expects($this->once())->method('save')->with($paymentMock);

        $orderMock = $this->createOrder($paymentMock, 'ORDER_LEVEL_DATAREQUEST_KEY');
        $instance = $this->getInstanceWithState($pushRequest, $paymentMock, $orderMock);

        $this->assertTrue($this->invoke('skipPush', $instance));
    }

    public function testAPlazaReservationUpdateOnlyAddsACommentToTheOrder(): void
    {
        $pushRequest = $this->createPushRequest(['statusCode' => self::STATUS_SUCCESS, 'datarequest' => 'EXTEND_KEY']);
        $paymentMock = $this->createPayment([
            'buckaroo_mor_pending_datarequest_keys' => ['EXTEND_KEY' => true],
        ]);
        $orderMock = $this->createOrder($paymentMock);
        $orderMock->expects($this->once())->method('addCommentToStatusHistory')
            ->with('Buckaroo: Klarna reservation updated via Buckaroo Plaza (DataRequest: EXTEND_KEY).');
        $this->orderRepositoryMock->expects($this->once())->method('save')->with($orderMock);
        $this->orderRequestServiceMock->expects($this->never())->method('updateOrderStatus');

        $instance = $this->getInstanceWithState($pushRequest, $paymentMock, $orderMock);

        $this->assertTrue($this->invoke('processPushByStatus', $instance));
    }

    /**
     * @param array $push
     * @param array $flags
     * @param array $paymentInfo
     * @param bool  $expected
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('plazaSecondaryProvider')]
    public function testDetectsAPlazaSecondaryDataRequestPush(
        array $push,
        array $flags,
        array $paymentInfo,
        bool $expected
    ): void {
        $paymentMock = $this->createPayment($paymentInfo);
        $instance = $this->getInstanceWithState($this->createPushRequest($push, $flags), $paymentMock);

        $this->assertSame($expected, $this->invoke('isPlazaSecondaryDataRequestPush', $instance));
    }

    public static function plazaSecondaryProvider(): array
    {
        $pending = ['buckaroo_mor_pending_datarequest_keys' => ['EXTEND_KEY' => true]];

        return [
            'pending secondary key' => [['statusCode' => '190', 'datarequest' => 'EXTEND_KEY'], [], $pending, true],
            'unknown other key' => [['statusCode' => '190', 'datarequest' => 'EXTEND_KEY'], [], [], false],
            'same key as the reservation' => [
                ['statusCode' => '190', 'datarequest' => self::DATAREQUEST_KEY],
                [],
                ['buckaroo_mor_pending_datarequest_keys' => [self::DATAREQUEST_KEY => true]],
                false,
            ],
            'failed push' => [['statusCode' => '490', 'datarequest' => 'EXTEND_KEY'], [], $pending, false],
            'initiated by Magento' => [
                ['statusCode' => '190', 'datarequest' => 'EXTEND_KEY'],
                [['initiated_by_magento', 1]],
                $pending,
                false,
            ],
            'no data request' => [['statusCode' => '190', 'datarequest' => null], [], $pending, false],
        ];
    }

    public function testAMagentoExtendReservationKeepsTheExistingKey(): void
    {
        $pushRequest = $this->createPushRequest(
            ['datarequest' => 'EXTEND_KEY'],
            [['initiated_by_magento', 1], ['service_action_from_magento', ['extendreservation', 'updatereservation']]]
        );
        $instance = $this->getInstanceWithState($pushRequest, $this->createPayment([]));

        $this->assertTrue($this->invokeArgs('shouldPreserveExistingDataRequestKey', ['EXTEND_KEY'], $instance));
    }

    public function testTheReservationKeyIsTakenFromThePaymentWhenTheOrderHasNone(): void
    {
        $paymentMock = $this->createPayment([
            'buckaroo_datarequest_key' => self::DATAREQUEST_KEY,
            'buckaroo_mor_pending_datarequest_keys' => ['EXTEND_KEY' => true],
        ]);
        $orderMock = $this->createOrder($paymentMock, null);
        $instance = $this->getInstanceWithState($this->createPushRequest([]), $paymentMock, $orderMock);

        $this->assertTrue($this->invokeArgs('shouldPreserveExistingDataRequestKey', ['EXTEND_KEY'], $instance));
    }

    public function testTheFirstDataRequestKeyIsSavedOnOrderAndPayment(): void
    {
        $paymentMock = $this->createPayment([]);
        $paymentMock->expects($this->once())->method('setAdditionalInformation')
            ->with('buckaroo_datarequest_key', 'RESERVE_KEY');
        $orderMock = $this->createOrder($paymentMock, null);
        $orderMock->expects($this->once())->method('__call')
            ->with('setBuckarooDatarequestKey', ['RESERVE_KEY']);
        $this->orderRepositoryMock->expects($this->once())->method('save')->with($orderMock);

        $instance = $this->getInstanceWithState(
            $this->createPushRequest(['datarequest' => 'RESERVE_KEY']),
            $paymentMock,
            $orderMock
        );

        $this->assertTrue($this->invoke('setBuckarooReservationNumber', $instance));
    }

    /**
     * @param string|null $dataRequest
     * @param array       $paymentInfo
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('notSavedDataRequestProvider')]
    public function testADataRequestKeyIsNotSaved(?string $dataRequest, array $paymentInfo): void
    {
        $paymentMock = $this->createPayment($paymentInfo);
        $paymentMock->expects($this->never())->method('setAdditionalInformation');
        $this->orderRepositoryMock->expects($this->never())->method('save');

        $instance = $this->getInstanceWithState(
            $this->createPushRequest(['datarequest' => $dataRequest]),
            $paymentMock
        );

        $this->assertFalse($this->invoke('saveBuckarooDataRequestKey', $instance));
    }

    public static function notSavedDataRequestProvider(): array
    {
        return [
            'no key in the push' => [null, []],
            'secondary key' => ['EXTEND_KEY', ['buckaroo_mor_pending_datarequest_keys' => ['EXTEND_KEY' => true]]],
        ];
    }

    /**
     * @param array  $postData
     * @param string $transactions
     * @param string $expected
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('transactionKeyProvider')]
    public function testTheTransactionKeyOfACaptureIsTheCaptureTransaction(
        array $postData,
        string $transactions,
        string $expected
    ): void {
        $pushRequest = $this->createPushRequest(
            ['transactions' => $transactions, 'datarequest' => self::DATAREQUEST_KEY],
            [],
            $postData
        );
        $instance = $this->getInstanceWithState($pushRequest, $this->createPayment([]));

        $this->assertSame($expected, $this->invoke('getTransactionKey', $instance));
    }

    public static function transactionKeyProvider(): array
    {
        return [
            'C800 capture' => [[['transaction_type', 'C800']], 'CAPTURE_KEY', 'CAPTURE_KEY'],
            'collecting mutation' => [[['mutationtype', 'collecting']], 'CAPTURE_KEY', 'CAPTURE_KEY'],
            'Collecting mutation' => [[['mutationtype', 'Collecting']], 'CAPTURE_KEY', 'CAPTURE_KEY'],
            'capture without transaction' => [[['transaction_type', 'C800']], '', self::DATAREQUEST_KEY],
            'reservation push' => [[], 'CAPTURE_KEY', self::DATAREQUEST_KEY],
        ];
    }

    public function testAPlazaCaptureOnAnInvoicedOrderStoresTheCaptureKeyOnly(): void
    {
        $pushRequest = $this->createPushRequest(
            ['transactions' => 'CAPTURE_KEY'],
            [],
            [['transaction_type', 'C800']]
        );
        $paymentMock = $this->createPayment([]);
        $calls = [];
        $paymentMock->method('setAdditionalInformation')->willReturnCallback(
            function ($key, $value) use (&$calls, $paymentMock) {
                $calls[$key] = $value;
                return $paymentMock;
            }
        );
        $this->paymentRepositoryMock->expects($this->once())->method('save')->with($paymentMock);
        $orderMock = $this->createOrder($paymentMock);
        $orderMock->method('hasInvoices')->willReturn(true);
        $orderMock->expects($this->never())->method('prepareInvoice');

        $instance = $this->getInstanceWithState($pushRequest, $paymentMock, $orderMock);

        $this->assertTrue($this->invoke('saveInvoice', $instance));
        $this->assertSame(
            ['buckaroo_capture_transaction_key' => 'CAPTURE_KEY', 'buckaroo_already_captured' => true],
            $calls
        );
    }

    /**
     * @param string      $statusCode
     * @param string      $state
     * @param string|null $newState
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('authorizationProvider')]
    public function testASucceededAuthorizationMovesTheOrderToProcessing(
        string $statusCode,
        string $state,
        ?string $newState
    ): void {
        $paymentMock = $this->createPayment([]);
        $orderMock = $this->createOrder($paymentMock);
        $orderMock->method('getState')->willReturn($state);
        if ($newState === null) {
            $orderMock->expects($this->never())->method('setState');
        } else {
            $orderMock->expects($this->once())->method('setState')->with($newState);
        }

        $instance = $this->getInstanceWithState(
            $this->createPushRequest(['statusCode' => $statusCode]),
            $paymentMock,
            $orderMock
        );

        $this->invoke('processSucceededPushAuthorization', $instance);
    }

    public static function authorizationProvider(): array
    {
        return [
            'new' => ['190', 'new', 'processing'],
            'pending payment' => ['190', 'pending_payment', 'processing'],
            'payment review' => ['190', 'payment_review', 'processing'],
            'canceled stays canceled' => ['190', 'canceled', null],
            'already processing' => ['190', 'processing', null],
            'complete' => ['190', 'complete', null],
            'not successful' => ['490', 'new', null],
        ];
    }

    /**
     * @param array $data     statusCode, transactions, datarequest
     * @param array $flags    [name, value] pairs hasAdditionalInformation answers true for
     * @param array $postData [name, value] pairs hasPostData answers true for
     */
    private function createPushRequest(array $data, array $flags = [], array $postData = [])
    {
        $mock = $this->getFakeMock(PushRequestInterfaceStub::class)->getMock();
        $mock->method('getStatusCode')->willReturn($data['statusCode'] ?? self::STATUS_SUCCESS);
        $mock->method('getTransactions')->willReturn($data['transactions'] ?? null);
        $mock->method('getDatarequest')->willReturn($data['datarequest'] ?? null);
        $mock->method('hasAdditionalInformation')->willReturnCallback(
            static fn($name, $value) => in_array([$name, $value], $flags, true)
        );
        $mock->method('hasPostData')->willReturnCallback(
            static fn($name, $value) => in_array([$name, $value], $postData, true)
        );

        return $mock;
    }

    public function testTheOrderReservationKeyWinsOverThePaymentCopy(): void
    {
        $paymentMock = $this->createPayment([
            'buckaroo_datarequest_key' => 'EXTEND_KEY',
            'buckaroo_mor_pending_datarequest_keys' => ['EXTEND_KEY' => true],
        ]);
        $instance = $this->getInstanceWithState($this->createPushRequest([]), $paymentMock);

        $this->assertTrue($this->invokeArgs('shouldPreserveExistingDataRequestKey', ['EXTEND_KEY'], $instance));
    }

    public function testAMagentoPayPushWithAnotherKeyDoesNotKeepTheExistingKey(): void
    {
        $pushRequest = $this->createPushRequest(
            ['datarequest' => 'NEW_KEY'],
            [['initiated_by_magento', 1], ['service_action_from_magento', 'pay']]
        );
        $instance = $this->getInstanceWithState($pushRequest, $this->createPayment([]));

        $this->assertFalse($this->invokeArgs('shouldPreserveExistingDataRequestKey', ['NEW_KEY'], $instance));
    }

    /**
     * @param array $postData
     * @param bool  $expected
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('captureOnInvoicedOrderProvider')]
    public function testOnlyACapturePushOnAnInvoicedOrderCountsAsInvoiced(array $postData, bool $expected): void
    {
        $paymentMock = $this->createPayment([]);
        $orderMock = $this->createOrder($paymentMock);
        $orderMock->method('hasInvoices')->willReturn(true);
        $orderMock->method('canInvoice')->willReturn(true);
        $instance = $this->getInstanceWithState(
            $this->createPushRequest(['transactions' => 'CAPTURE_KEY'], [], $postData),
            $paymentMock,
            $orderMock
        );

        $this->assertSame($expected, $this->invoke('saveInvoice', $instance));
    }

    public static function captureOnInvoicedOrderProvider(): array
    {
        return [
            'C800' => [[['transaction_type', 'C800']], true],
            'collecting' => [[['mutationtype', 'collecting']], true],
            'Collecting' => [[['mutationtype', 'Collecting']], true],
            'not a capture' => [[], false],
        ];
    }
}
