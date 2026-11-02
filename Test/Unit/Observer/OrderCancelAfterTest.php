<?php

declare(strict_types=1);

namespace Buckaroo\Magento2\Test\Unit\Observer;

use Buckaroo\Magento2\Gateway\Http\Client\Json;
use Buckaroo\Magento2\Logging\BuckarooLoggerInterface;
use Buckaroo\Magento2\Model\ConfigProvider\Account;
use Buckaroo\Magento2\Model\ConfigProvider\Method\PayPerEmail;
use Buckaroo\Magento2\Model\LockManagerWrapper;
use Buckaroo\Magento2\Model\Service\Order\CancelRemainingReservation;
use Buckaroo\Magento2\Observer\OrderCancelAfter;
use Magento\Framework\App\State as AppState;
use Magento\Framework\Encryption\Encryptor;
use Magento\Framework\Event;
use Magento\Framework\Event\Observer;
use Magento\Sales\Api\OrderPaymentRepositoryInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Payment;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class OrderCancelAfterTest extends TestCase
{
    private const ORDER = '300000327';

    /** @var Json|MockObject */
    private $client;

    /** @var PayPerEmail|MockObject */
    private $ppeConfig;

    /** @var LockManagerWrapper|MockObject */
    private $lockManager;

    /** @var array<int, string> */
    private array $calls = [];

    protected function setUp(): void
    {
        $this->client = $this->createMock(Json::class);
        $this->ppeConfig = $this->createMock(PayPerEmail::class);
        $this->lockManager = $this->createMock(LockManagerWrapper::class);
    }

    public function testPayPerEmailCancelIsMarkedAsARequestToBuckaroo(): void
    {
        $this->ppeConfig->method('getCancelPpe')->willReturn(true);
        $this->lockManager->expects($this->once())
            ->method('markGatewayRequestStarted')
            ->with(self::ORDER)
            ->willReturnCallback(function () {
                $this->calls[] = 'markStarted';
                return true;
            });
        $this->client->expects($this->once())
            ->method('doCancelRequest')
            ->willReturnCallback(function () {
                $this->calls[] = 'cancelRequest';
                return true;
            });
        $this->lockManager->expects($this->once())
            ->method('markGatewayRequestFinished')
            ->with(self::ORDER)
            ->willReturnCallback(function () {
                $this->calls[] = 'markFinished';
                return true;
            });

        $this->createObserver()->execute($this->createEventObserver(PayPerEmail::CODE));

        $this->assertSame(['markStarted', 'cancelRequest', 'markFinished'], $this->calls);
    }

    public function testMarkIsRemovedWhenTheCancelRequestFails(): void
    {
        $this->ppeConfig->method('getCancelPpe')->willReturn(true);
        $this->lockManager->method('markGatewayRequestStarted')->willReturn(true);
        $this->client->method('doCancelRequest')->willThrowException(new \RuntimeException('timeout'));
        $this->lockManager->expects($this->once())->method('markGatewayRequestFinished')->with(self::ORDER);

        $this->createObserver()->execute($this->createEventObserver(PayPerEmail::CODE));
    }

    public function testMarkHeldByAnOuterRequestIsLeftInPlace(): void
    {
        $this->ppeConfig->method('getCancelPpe')->willReturn(true);
        $this->lockManager->method('markGatewayRequestStarted')->willReturn(false);
        $this->client->expects($this->once())->method('doCancelRequest');
        $this->lockManager->expects($this->never())->method('markGatewayRequestFinished');

        $this->createObserver()->execute($this->createEventObserver(PayPerEmail::CODE));
    }

    public function testNoMarkWhenNothingIsSentToBuckaroo(): void
    {
        $this->ppeConfig->method('getCancelPpe')->willReturn(true);
        $this->client->expects($this->never())->method('doCancelRequest');
        $this->lockManager->expects($this->never())->method('markGatewayRequestStarted');

        $this->createObserver()->execute($this->createEventObserver('buckaroo_magento2_ideal'));
    }

    private function createObserver(): OrderCancelAfter
    {
        $encryptor = $this->createMock(Encryptor::class);
        $encryptor->method('decrypt')->willReturn('key');

        return new OrderCancelAfter(
            $this->client,
            $encryptor,
            $this->createMock(Account::class),
            $this->ppeConfig,
            $this->createMock(BuckarooLoggerInterface::class),
            $this->createMock(AppState::class),
            $this->createMock(CancelRemainingReservation::class),
            $this->createMock(OrderPaymentRepositoryInterface::class),
            $this->lockManager
        );
    }

    private function createEventObserver(string $method): Observer
    {
        $payment = $this->createMock(Payment::class);
        $payment->method('getMethod')->willReturn($method);
        $payment->method('getAdditionalInformation')->willReturn('ORIGINALKEY');

        $order = $this->createMock(Order::class);
        $order->method('getPayment')->willReturn($payment);
        $order->method('getIncrementId')->willReturn(self::ORDER);
        $order->method('getStoreId')->willReturn(1);

        $event = $this->getMockBuilder(Event::class)->addMethods(['getOrder'])->getMock();
        $event->method('getOrder')->willReturn($order);

        $observer = $this->createMock(Observer::class);
        $observer->method('getEvent')->willReturn($event);

        return $observer;
    }
}
