<?php
declare(strict_types=1);

namespace Buckaroo\Magento2\Test\Unit\Model;

use Buckaroo\Magento2\Api\Data\PushRequestInterface;
use Buckaroo\Magento2\Exception as BuckarooException;
use Buckaroo\Magento2\Logging\BuckarooLoggerInterface;
use Buckaroo\Magento2\Model\LockManagerWrapper;
use Buckaroo\Magento2\Model\Push;
use Buckaroo\Magento2\Model\Push\PushProcessorsFactory;
use Buckaroo\Magento2\Model\Push\PushProcessorInterface;
use Buckaroo\Magento2\Model\Push\PushTransactionType;
use Buckaroo\Magento2\Model\RequestPush\RequestPushFactory;
use Buckaroo\Magento2\Service\Push\OrderRequestService;
use Magento\Sales\Model\Order;
use Magento\Store\Model\Store;
use PHPUnit\Framework\MockObject\MockObject;

class PushTest extends \Buckaroo\Magento2\Test\BaseTest
{
    protected $instanceClass = Push::class;

    private MockObject|BuckarooLoggerInterface $loggerMock;

    private MockObject|RequestPushFactory $requestPushFactoryMock;

    private MockObject|PushProcessorsFactory $pushProcessorsFactoryMock;

    private MockObject|OrderRequestService $orderRequestServiceMock;

    private MockObject|PushTransactionType $pushTransactionTypeMock;

    private MockObject|LockManagerWrapper $lockManagerMock;

    private MockObject|PushRequestInterface $pushRequestMock;

    public function setUp(): void
    {
        parent::setUp();

        $this->loggerMock = $this->getFakeMock(BuckarooLoggerInterface::class)->getMock();
        $this->requestPushFactoryMock = $this->getFakeMock(RequestPushFactory::class)->getMock();
        $this->pushProcessorsFactoryMock = $this->getFakeMock(PushProcessorsFactory::class)->getMock();
        $this->orderRequestServiceMock = $this->getFakeMock(OrderRequestService::class)->getMock();
        $this->pushTransactionTypeMock = $this->getFakeMock(PushTransactionType::class)->getMock();
        $this->lockManagerMock = $this->getFakeMock(LockManagerWrapper::class)->getMock();

        $this->pushRequestMock = $this->getFakeMock(PushRequestInterface::class)->getMock();
        $this->requestPushFactoryMock->method('create')->willReturn($this->pushRequestMock);
    }

    public function getInstance(array $args = []): Push
    {
        return parent::getInstance([
            'logger' => $this->loggerMock,
            'requestPushFactory' => $this->requestPushFactoryMock,
            'pushProcessorsFactory' => $this->pushProcessorsFactoryMock,
            'orderRequestService' => $this->orderRequestServiceMock,
            'pushTransactionType' => $this->pushTransactionTypeMock,
            'lockManager' => $this->lockManagerMock,
        ] + $args);
    }

    public function testReceivePushSuccess()
    {
        $storeMock = $this->getFakeMock(Store::class)->getMock();

        $orderMock = $this->getFakeMock(Order::class)->getMock();
        $orderMock->method('getIncrementId')->willReturn('123456');
        $orderMock->method('getStore')->willReturn($storeMock);

        $this->orderRequestServiceMock->expects($this->once())
            ->method('getOrderByRequest')
            ->with($this->pushRequestMock)
            ->willReturn($orderMock);

        $this->lockManagerMock->expects($this->once())
            ->method('lockOrder')
            ->with('123456', 5)
            ->willReturn(true);

        $this->pushRequestMock->expects($this->once())
            ->method('validate')
            ->with($storeMock)
            ->willReturn(true);

        $this->pushTransactionTypeMock->expects($this->once())
            ->method('getPushTransactionType')
            ->with($this->pushRequestMock, $orderMock)
            ->willReturn($this->getFakeMock(PushTransactionType::class)->getMock());

        $processorMock = $this->getFakeMock(PushProcessorInterface::class)->getMock();
        $processorMock->expects($this->once())
            ->method('processPush')
            ->with($this->pushRequestMock)
            ->willReturn(true);

        $this->pushProcessorsFactoryMock->expects($this->once())
            ->method('get')
            ->with($this->anything())  // since it's the mock type object
            ->willReturn($processorMock);

        $this->lockManagerMock->expects($this->once())
            ->method('unlockOrder')
            ->with('123456');

        $instance = $this->getInstance();
        $result = $instance->receivePush();

        $this->assertTrue($result);
    }

    public function testReceivePushInvalidSignature()
    {
        $storeMock = $this->getFakeMock(Store::class)->getMock();

        $orderMock = $this->getFakeMock(Order::class)->getMock();
        $orderMock->method('getIncrementId')->willReturn('123456');
        $orderMock->method('getStore')->willReturn($storeMock);

        $this->orderRequestServiceMock->method('getOrderByRequest')->willReturn($orderMock);
        $this->lockManagerMock->method('lockOrder')->willReturn(true);

        $this->pushRequestMock->method('validate')->with($storeMock)->willReturn(false);

        $this->lockManagerMock->expects($this->once())->method('unlockOrder')->with('123456');

        $instance = $this->getInstance();

        $this->expectException(BuckarooException::class);
        $this->expectExceptionMessage('Signature from push is incorrect');

        $instance->receivePush();
    }

    public function testReceivePushLockNotAcquired()
    {
        $orderMock = $this->getFakeMock(Order::class)->getMock();
        $orderMock->method('getIncrementId')->willReturn('123456');

        $this->orderRequestServiceMock->method('getOrderByRequest')->willReturn($orderMock);
        $this->lockManagerMock->method('lockOrder')->willReturn(false);

        $this->lockManagerMock->expects($this->never())->method('unlockOrder');

        $instance = $this->getInstance();

        $this->expectException(BuckarooException::class);
        $this->expectExceptionMessage('Lock push not acquired');

        $instance->receivePush();
    }

    public function testReceivePushExceptionHandling()
    {
        $storeMock = $this->getFakeMock(Store::class)->getMock();

        $orderMock = $this->getFakeMock(Order::class)->getMock();
        $orderMock->method('getIncrementId')->willReturn('123456');
        $orderMock->method('getStore')->willReturn($storeMock);

        $this->orderRequestServiceMock->method('getOrderByRequest')->willReturn($orderMock);
        $this->lockManagerMock->method('lockOrder')->willReturn(true);

        $this->pushRequestMock->method('validate')->with($storeMock)->willReturn(true);

        $this->pushTransactionTypeMock->method('getPushTransactionType')
            ->willThrowException(new BuckarooException(__('Test exception')));

        $this->lockManagerMock->expects($this->once())->method('unlockOrder')->with('123456');

        $instance = $this->getInstance();

        $this->expectException(BuckarooException::class);
        $this->expectExceptionMessage('Test exception');

        $instance->receivePush();
    }
}
