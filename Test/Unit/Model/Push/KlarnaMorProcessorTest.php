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

    private function createOrder($paymentMock)
    {
        $orderMock = $this->getFakeMock(OrderStub::class)->getMock();
        $orderMock->method('getPayment')->willReturn($paymentMock);
        $orderMock->method('getIncrementId')->willReturn('100000001');
        $orderMock->method('getBuckarooDatarequestKey')->willReturn(self::DATAREQUEST_KEY);

        return $orderMock;
    }

    private function getInstanceWithState($pushRequest, $paymentMock)
    {
        $instance = $this->getInstance();
        $this->setProperty('pushRequest', $pushRequest, $instance);
        $this->setProperty('order', $this->createOrder($paymentMock), $instance);
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
}
