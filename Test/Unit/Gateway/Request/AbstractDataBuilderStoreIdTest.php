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

namespace Buckaroo\Magento2\Test\Unit\Gateway\Request;

use Buckaroo\Magento2\Gateway\Request\AbstractDataBuilder;
use Buckaroo\Magento2\Test\Unit\Stubs\AbstractDataBuilderStub;
use Magento\Sales\Model\Order;
use PHPUnit\Framework\TestCase;

/**
 * A request can be built from the push webapi route, an admin action or a cron job, none of which
 * sit in the order's store view. Every builder in ticket D scopes its config reads through this.
 */
class AbstractDataBuilderStoreIdTest extends TestCase
{
    private function builderForOrder(?Order $order): AbstractDataBuilder
    {
        $builder = new AbstractDataBuilderStub();

        $property = new \ReflectionProperty(AbstractDataBuilder::class, 'order');
        $property->setAccessible(true);
        $property->setValue($builder, $order);

        return $builder;
    }

    public function testCastsTheOrdersStringStoreIdToAnInt(): void
    {
        $order = $this->createMock(Order::class);
        $order->method('getStoreId')->willReturn('2');

        $this->assertSame(2, $this->builderForOrder($order)->getStoreId());
    }

    public function testReturnsNullWhenNoOrderHasBeenSet(): void
    {
        $this->assertNull($this->builderForOrder(null)->getStoreId());
    }

    public function testReturnsNullWhenTheOrderHasNoStore(): void
    {
        $order = $this->createMock(Order::class);
        $order->method('getStoreId')->willReturn(null);

        $this->assertNull($this->builderForOrder($order)->getStoreId());
    }
}
