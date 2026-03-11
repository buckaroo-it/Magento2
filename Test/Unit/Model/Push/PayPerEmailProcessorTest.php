<?php
declare(strict_types=1);

namespace Buckaroo\Magento2\Test\Unit\Model\Push;

use Buckaroo\Magento2\Helper\Data;
use Buckaroo\Magento2\Helper\PaymentGroupTransaction;
use Buckaroo\Magento2\Logging\BuckarooLoggerInterface;
use Buckaroo\Magento2\Model\BuckarooStatusCode;
use Buckaroo\Magento2\Model\ConfigProvider\Account;
use Buckaroo\Magento2\Model\ConfigProvider\Method\PayPerEmail;
use Buckaroo\Magento2\Model\OrderStatusFactory;
use Buckaroo\Magento2\Model\Push\PayPerEmailProcessor;
use Buckaroo\Magento2\Model\Push\PushTransactionType;
use Buckaroo\Magento2\Model\ResourceModel\Giftcard\Collection as GiftcardCollection;
use Buckaroo\Magento2\Model\Service\GiftCardRefundService;
use Buckaroo\Magento2\Service\Order\Uncancel;
use Buckaroo\Magento2\Service\Push\OrderRequestService;
use Buckaroo\Magento2\Test\BaseTest;
use Magento\Framework\App\ResourceConnection;
use Magento\Sales\Api\Data\TransactionInterface;
use Magento\Sales\Model\Order;

class PayPerEmailProcessorTest extends BaseTest
{
    protected $instanceClass = PayPerEmailProcessor::class;

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

    public function setUp(): void
    {
        parent::setUp();

        $this->orderRequestServiceMock = $this->getFakeMock(OrderRequestService::class)->getMock();
        $this->pushTransactionTypeMock = $this->getFakeMock(PushTransactionType::class)->getMock();
        $this->loggerMock = $this->getFakeMock(BuckarooLoggerInterface::class)->getMock();
        $this->helperMock = $this->getFakeMock(Data::class)->getMock();
        $this->transactionMock = $this->getFakeMock(TransactionInterface::class)->getMock();
        $this->groupTransactionMock = $this->getFakeMock(PaymentGroupTransaction::class)->getMock();
        $this->buckarooStatusCodeMock = $this->getFakeMock(BuckarooStatusCode::class)->getMock();
        $this->orderStatusFactoryMock = $this->getFakeMock(OrderStatusFactory::class)->getMock();
        $this->configAccountMock = $this->getFakeMock(Account::class)->getMock();
        $this->giftCardRefundServiceMock = $this->getFakeMock(GiftCardRefundService::class)->getMock();
        $this->uncancelServiceMock = $this->getFakeMock(Uncancel::class)->getMock();
        $this->resourceConnectionMock = $this->getFakeMock(ResourceConnection::class)->getMock();
        $this->giftcardCollectionMock = $this->getFakeMock(GiftcardCollection::class)->getMock();
        $this->configPayPerEmailMock = $this->getFakeMock(PayPerEmail::class)->getMock();
    }

    public function getInstance(array $args = []): PayPerEmailProcessor
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
        ] + $args);
    }

    public function testGetNewStatusConvertsInitialB2BWaitingOnConsumerPushToSuccess(): void
    {
        $instance = $this->getInstance();

        $orderMock = $this->getFakeMock(Order::class)->getMock();
        $pushRequestMock = $this->getFakeMock(\Buckaroo\Magento2\Api\Data\PushRequestInterface::class)->getMock();

        $pushRequestMock->method('getStatusCode')->willReturn((string)BuckarooStatusCode::WAITING_ON_CONSUMER);
        $pushRequestMock->method('getTransactionMethod')->willReturn('payperemail');
        $pushRequestMock->method('getAdditionalInformation')->willReturnMap([
            ['frompayperemail', '1'],
        ]);

        $this->orderStatusFactoryMock->expects($this->once())
            ->method('get')
            ->with(BuckarooStatusCode::WAITING_ON_CONSUMER, $orderMock)
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

        $orderMock = $this->getFakeMock(Order::class)->getMock();
        $orderMock->method('getIncrementId')->willReturn('100000001');
        $orderMock->method('getState')->willReturn(Order::STATE_PROCESSING);
        $orderMock->method('getTotalPaid')->willReturn(100.0);
        $orderMock->method('hasInvoices')->willReturn(true);

        $this->setProperty('order', $orderMock, $instance);

        $this->assertFalse($this->invoke('canProcessPendingPush', $instance));
    }

    public function testCanProcessPendingPushReturnsTrueForUnpaidOrder(): void
    {
        $instance = $this->getInstance();

        $orderMock = $this->getFakeMock(Order::class)->getMock();
        $orderMock->method('getState')->willReturn(Order::STATE_PENDING_PAYMENT);
        $orderMock->method('getTotalPaid')->willReturn(0.0);
        $orderMock->method('hasInvoices')->willReturn(false);

        $this->setProperty('order', $orderMock, $instance);

        $this->assertTrue($this->invoke('canProcessPendingPush', $instance));
    }
}
