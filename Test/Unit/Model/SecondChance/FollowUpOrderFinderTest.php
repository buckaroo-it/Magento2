<?php
/**
 * NOTICE OF LICENSE
 *
 * This source file is subject to the MIT License
 * It is available through the world-wide-web at this URL:
 * https://tldrlegal.com/license/mit-license
 * If you are unable to obtain it through the world-wide-web, please send an email
 * to support@buckaroo.nl so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade this module to newer
 * versions in the future. If you wish to customize this module for your
 * needs please contact support@buckaroo.nl for more information.
 *
 * @copyright Copyright (c) Buckaroo B.V.
 * @license   https://tldrlegal.com/license/mit-license
 */
declare(strict_types=1);

namespace Buckaroo\Magento2\Test\Unit\Model\SecondChance;

use Buckaroo\Magento2\Model\SecondChance\FollowUpOrderFinder;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\ResourceModel\Order\Collection;
use Magento\Sales\Model\ResourceModel\Order\CollectionFactory;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class FollowUpOrderFinderTest extends TestCase
{
    /**
     * Timestamps of the merchant case in BTI-1602 (UTC).
     */
    private const ABANDONED_AT = '2026-09-23 20:00:29';

    private const NOW = 1790194287; // 2026-09-23 20:11:27

    /**
     * Every addFieldToFilter() call, in order, as [field, condition].
     *
     * @var array
     */
    private $filters = [];

    /**
     * @var Order|MockObject
     */
    private $foundOrder;

    protected function setUp(): void
    {
        $this->filters = [];
        $this->foundOrder = $this->createMock(Order::class);
    }

    /**
     * BTI-1602: the follow-up order is measured from the abandoned order's own created_at, so an order paid in the
     * same second as the record - the browser-back case - is still found.
     */
    public function testPaidOrderIsSearchedFromTheAbandonedOrderCreationTime(): void
    {
        $this->foundOrder->method('getId')->willReturn(15826141);

        $paidOrder = $this->createFinder()->getPaidOrder('klant@buckaroo.nl', $this->createAbandonedOrder());

        $this->assertSame($this->foundOrder, $paidOrder);
        $this->assertSame(
            [
                ['customer_email', 'klant@buckaroo.nl'],
                ['entity_id', ['neq' => 15826140]],
                ['created_at', ['gteq' => self::ABANDONED_AT]],
                ['state', ['in' => [Order::STATE_PROCESSING, Order::STATE_COMPLETE]]],
            ],
            $this->filters
        );
    }

    public function testNoPaidOrderReturnsNull(): void
    {
        $this->foundOrder->method('getId')->willReturn(null);

        $this->assertNull($this->createFinder()->getPaidOrder('klant@buckaroo.nl', $this->createAbandonedOrder()));
    }

    /**
     * A newer order only counts as "still being paid" while it is younger than the grace period.
     */
    public function testOrderAwaitingPaymentIsLimitedToTheGracePeriod(): void
    {
        $this->foundOrder->method('getId')->willReturn(15826141);

        $order = $this->createFinder()->getOrderAwaitingPayment(
            'klant@buckaroo.nl',
            $this->createAbandonedOrder(),
            3600
        );

        $this->assertSame($this->foundOrder, $order);
        $this->assertSame(
            [
                ['customer_email', 'klant@buckaroo.nl'],
                ['entity_id', ['neq' => 15826140]],
                ['created_at', ['gteq' => self::ABANDONED_AT]],
                ['state', ['in' => [Order::STATE_NEW, Order::STATE_PENDING_PAYMENT]]],
                ['created_at', ['gteq' => gmdate('Y-m-d H:i:s', self::NOW - 3600)]],
            ],
            $this->filters
        );
    }

    public function testNoOrderAwaitingPaymentReturnsNull(): void
    {
        $this->foundOrder->method('getId')->willReturn(null);

        $this->assertNull(
            $this->createFinder()->getOrderAwaitingPayment('klant@buckaroo.nl', $this->createAbandonedOrder(), 3600)
        );
    }

    /**
     * @return Order|MockObject
     */
    private function createAbandonedOrder()
    {
        $order = $this->createMock(Order::class);
        $order->method('getEntityId')->willReturn(15826140);
        $order->method('getCreatedAt')->willReturn(self::ABANDONED_AT);

        return $order;
    }

    /**
     * @return FollowUpOrderFinder
     */
    private function createFinder(): FollowUpOrderFinder
    {
        $collection = $this->createMock(Collection::class);
        $collection->method('addFieldToFilter')->willReturnCallback(
            function ($field, $condition) use ($collection) {
                $this->filters[] = [$field, $condition];
                return $collection;
            }
        );
        $collection->method('setPageSize')->willReturnSelf();
        $collection->method('getFirstItem')->willReturn($this->foundOrder);

        $collectionFactory = $this->getMockBuilder(CollectionFactory::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['create'])
            ->getMock();
        $collectionFactory->method('create')->willReturn($collection);

        $dateTime = $this->createMock(DateTime::class);
        $dateTime->method('gmtTimestamp')->willReturn(self::NOW);
        $dateTime->method('gmtDate')->willReturnCallback(
            function ($format, $timestamp) {
                return gmdate($format, $timestamp);
            }
        );

        return new FollowUpOrderFinder($collectionFactory, $dateTime);
    }
}
