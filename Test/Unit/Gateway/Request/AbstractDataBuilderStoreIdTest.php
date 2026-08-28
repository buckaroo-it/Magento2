<?php
/**
 * NOTICE OF LICENSE
 *
 * This source file is subject to the MIT License
 * It is available through the world-wide-web at this URL:
 * https://tldrlegal.com/license/mit-license
 * If you are unable to obtain it through the world-wide-web, please email
 * to support@buckaroo.nl, so we can send you a copy immediately.
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

namespace Buckaroo\Magento2\Test\Unit\Gateway\Request;

use Buckaroo\Magento2\Gateway\Request\AbstractDataBuilder;
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
        $builder = $this->getMockBuilder(AbstractDataBuilder::class)
            ->disableOriginalConstructor()
            ->getMockForAbstractClass();

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
