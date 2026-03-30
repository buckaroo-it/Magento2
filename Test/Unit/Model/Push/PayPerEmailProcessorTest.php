<?php
declare(strict_types=1);

namespace Buckaroo\Magento2\Test\Unit\Model\Push;

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
}
