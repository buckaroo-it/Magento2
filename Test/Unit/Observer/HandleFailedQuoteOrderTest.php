<?php
/**
 * Buckaroo Magento 2 payment module (https://www.buckaroo.eu/)
 *
 * Copyright (c) Buckaroo B.V.
 * See LICENSE for license details.
 *
 * Support: support@buckaroo.nl
 *
 * @copyright Copyright (c) Buckaroo B.V.
 * @license   MIT
 */
declare(strict_types=1);

namespace Buckaroo\Magento2\Test\Unit\Observer;

use Buckaroo\Magento2\Logging\BuckarooLoggerInterface;
use Buckaroo\Magento2\Model\Session as BuckarooSession;
use Buckaroo\Magento2\Observer\HandleFailedQuoteOrder;
use Buckaroo\Magento2\Service\Order\CancelGuard;
use Magento\Framework\Event;
use Magento\Framework\Event\Observer;
use Magento\Framework\Module\Manager;
use Magento\Payment\Gateway\Command\CommandException;
use Magento\Sales\Api\OrderManagementInterface;
use Magento\Sales\Api\OrderPaymentRepositoryInterface;
use Magento\Sales\Model\Order;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class HandleFailedQuoteOrderTest extends TestCase
{
    /**
     * @var CancelGuard|MockObject
     */
    private $cancelGuard;

    protected function setUp(): void
    {
        $this->cancelGuard = $this->createMock(CancelGuard::class);
    }

    public function testCancelsThroughTheGuard(): void
    {
        $this->cancelGuard->expects($this->once())->method('cancel')->with(7, $this->anything())->willReturn(true);

        $this->observer()->execute($this->event(new CommandException(__('declined'))));
    }

    public function testIgnoresAnOrderThatWasNeverSaved(): void
    {
        $this->cancelGuard->expects($this->never())->method('cancel');

        $this->observer()->execute($this->event(new \RuntimeException('x'), null));
    }

    /**
     * @return HandleFailedQuoteOrder
     */
    private function observer(): HandleFailedQuoteOrder
    {
        return new HandleFailedQuoteOrder(
            $this->createMock(BuckarooSession::class),
            $this->createMock(BuckarooLoggerInterface::class),
            $this->createMock(Manager::class),
            $this->createMock(OrderManagementInterface::class),
            $this->createMock(OrderPaymentRepositoryInterface::class),
            $this->cancelGuard
        );
    }

    /**
     * @param \Throwable $exception
     * @param int|null   $orderId
     *
     * @return Observer
     */
    private function event(\Throwable $exception, ?int $orderId = 7): Observer
    {
        $order = $this->createMock(Order::class);
        $order->method('getId')->willReturn($orderId);
        $order->method('getIncrementId')->willReturn('300000490');

        $event = new Event(['order' => $order, 'exception' => $exception]);

        return new Observer(['event' => $event]);
    }
}
