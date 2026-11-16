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

namespace Buckaroo\Magento2\Test\Unit\Gateway\Request\Articles\ArticlesHandler;

use Buckaroo\Magento2\Gateway\Request\Articles\ArticlesHandler\DefaultHandler;
use Buckaroo\Magento2\Service\PayReminderService;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Payment;

/**
 * The handler is shared (ArticlesHandlerFactory gets it from the object manager), so the refund of a
 * pay-remainder order must use the order it is given, not one left behind by an earlier call.
 */
class CreditmemoPayRemainderTest extends \Buckaroo\Magento2\Test\BaseTest
{
    protected $instanceClass = DefaultHandler::class;

    public function testPayRemainderRefundOnAFreshHandlerUsesTheGivenOrder(): void
    {
        $order = $this->getFakeMock(Order::class)->getMock();
        $handler = $this->buildHandler([[$order, 12.5]]);

        $articles = $handler->getCreditMemoArticlesData($order, $this->getFakeMock(Payment::class)->getMock());

        $this->assertSame('PayRemainder', $articles['articles'][0]['description']);
        $this->assertEqualsWithDelta(12.5, $articles['articles'][0]['price'], 0.001);
        $this->assertSame($order, $handler->getOrder());
    }

    public function testPayRemainderRefundDoesNotReuseThePreviousOrder(): void
    {
        $previousOrder = $this->getFakeMock(Order::class)->getMock();
        $order = $this->getFakeMock(Order::class)->getMock();
        $handler = $this->buildHandler([[$previousOrder, 99.0], [$order, 12.5]]);
        $handler->setOrder($previousOrder);

        $articles = $handler->getCreditMemoArticlesData($order, $this->getFakeMock(Payment::class)->getMock());

        $this->assertEqualsWithDelta(12.5, $articles['articles'][0]['price'], 0.001);
    }

    /**
     * @param array $payRemainderByOrder [[Order, float], ...]
     *
     * @return DefaultHandler
     */
    private function buildHandler(array $payRemainderByOrder)
    {
        $handler = $this->getFakeMock(DefaultHandler::class)
            ->onlyMethods(['getTaxCategory'])
            ->getMock();
        $handler->method('getTaxCategory')->willReturn(21);

        $payReminderService = $this->getFakeMock(PayReminderService::class)->getMock();
        $payReminderService->method('isPayRemainder')->willReturn(true);
        $payReminderService->method('getPayRemainder')->willReturnMap($payRemainderByOrder);
        $this->setProperty('payReminderService', $payReminderService, $handler);

        return $handler;
    }
}
