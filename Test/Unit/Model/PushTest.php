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
use Buckaroo\Magento2\Service\Push\KlarnaMorDataRequestPushDetector;
use Buckaroo\Magento2\Service\Push\OrderRequestService;
use Buckaroo\Magento2\Service\Store\StoreEmulator;
use Magento\Sales\Model\Order;
use Magento\Store\Model\Store;
use PHPUnit\Framework\MockObject\MockObject;

/**
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class PushTest extends \Buckaroo\Magento2\Test\BaseTest
{
    protected $instanceClass = Push::class;

    /** @var MockObject|BuckarooLoggerInterface */
    private $loggerMock;

    /** @var MockObject|RequestPushFactory */
    private $requestPushFactoryMock;

    /** @var MockObject|PushProcessorsFactory */
    private $pushProcessorsFactoryMock;

    /** @var MockObject|OrderRequestService */
    private $orderRequestServiceMock;

    /** @var MockObject|PushTransactionType */
    private $pushTransactionTypeMock;

    /** @var MockObject|LockManagerWrapper */
    private $lockManagerMock;

    /** @var MockObject|KlarnaMorDataRequestPushDetector */
    private $klarnaMorDataRequestPushDetectorMock;

    /** @var MockObject|PushRequestInterface */
    private $pushRequestMock;

    /** @var MockObject|StoreEmulator */
    private $storeEmulatorMock;

    /** @var mixed The store StoreEmulator::emulate() was asked to emulate */
    private $emulatedStore;

    public function setUp(): void
    {
        parent::setUp();

        $this->loggerMock = $this->getFakeMock(BuckarooLoggerInterface::class)->getMock();
        $this->requestPushFactoryMock = $this->getFakeMock(RequestPushFactory::class)->getMock();
        $this->pushProcessorsFactoryMock = $this->getFakeMock(PushProcessorsFactory::class)->getMock();
        $this->orderRequestServiceMock = $this->getFakeMock(OrderRequestService::class)->getMock();
        $this->pushTransactionTypeMock = $this->getFakeMock(PushTransactionType::class)->getMock();
        $this->lockManagerMock = $this->getFakeMock(LockManagerWrapper::class)->getMock();
        $this->klarnaMorDataRequestPushDetectorMock = $this->getFakeMock(KlarnaMorDataRequestPushDetector::class)->getMock();
        $this->klarnaMorDataRequestPushDetectorMock->method('shouldAcknowledgeWithoutOrder')->willReturn(false);

        $this->pushRequestMock = $this->getFakeMock(PushRequestInterface::class)->getMock();
        $this->requestPushFactoryMock->method('create')->willReturn($this->pushRequestMock);

        // Run the callback straight through, so these tests exercise the processor chain rather
        // than the emulation itself. Without an explicit stub the auto-generated mock would return
        // null and never invoke the callback.
        $this->emulatedStore = false;
        $this->storeEmulatorMock = $this->getFakeMock(StoreEmulator::class)->getMock();
        $this->storeEmulatorMock->method('emulate')->willReturnCallback(
            function ($store, callable $callback) {
                $this->emulatedStore = $store;
                return $callback();
            }
        );
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
            'klarnaMorDataRequestPushDetector' => $this->klarnaMorDataRequestPushDetectorMock,
            'storeEmulator' => $this->storeEmulatorMock,
        ] + $args);
    }

    public function testReceivePushSuccess()
    {
        $storeMock = $this->getFakeMock(Store::class)->getMock();

        $orderMock = $this->getFakeMock(Order::class)->getMock();
        $orderMock->method('getIncrementId')->willReturn('123456');
        $orderMock->method('getStore')->willReturn($storeMock);
        // Deliberately a string: Order::getStoreId() is annotated int|null but returns a string.
        $orderMock->method('getStoreId')->willReturn('2');

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
        // The processor chain must run in the order's own store, not the ambient one. The push
        // route carries no store code, so without this the whole chain reads the default view.
        $this->assertSame('2', $this->emulatedStore);
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
