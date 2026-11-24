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

namespace Buckaroo\Magento2\Test\Unit\Service\Order;

use Buckaroo\Magento2\Logging\BuckarooLoggerInterface;
use Buckaroo\Magento2\Service\Order\Uncancel;
use Magento\Framework\Event\ManagerInterface;
use Magento\Framework\Module\Manager;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Item;
use PHPUnit\Framework\TestCase;

class UncancelStockTest extends TestCase
{
    /**
     * The quantities the cancel gave back are taken again, per simple product (BTI-2031).
     */
    public function testCollectsTheReleasedQuantityOfSimpleProducts(): void
    {
        $child = $this->item('MH01-XS-Black', 11, 2, 'simple');
        $parent = $this->item('MH01', 10, 2, 'configurable', [$child]);
        $simple = $this->item('24-MB01', 5, 1, 'simple');
        $shipped = $this->item('24-MB02', 6, 0, 'simple');

        $order = $this->createMock(Order::class);
        $order->method('getAllItems')->willReturn([$parent, $child, $simple, $shipped]);

        $released = (new \ReflectionMethod(Uncancel::class, 'getReleasedQuantities'))
            ->invoke($this->uncancel(), $order);

        $this->assertSame([
            'MH01-XS-Black' => [11, 2.0, 'simple'],
            '24-MB01'       => [5, 1.0, 'simple'],
        ], $released);
    }

    /**
     * @param string $sku
     * @param int    $productId
     * @param float  $qtyCanceled
     * @param string $type
     * @param array  $children
     *
     * @return Item
     */
    private function item(string $sku, int $productId, float $qtyCanceled, string $type, array $children = []): Item
    {
        $item = $this->createMock(Item::class);
        $item->method('getSku')->willReturn($sku);
        $item->method('getProductId')->willReturn($productId);
        $item->method('getQtyCanceled')->willReturn($qtyCanceled);
        $item->method('getProductType')->willReturn($type);
        $item->method('getChildrenItems')->willReturn($children);

        return $item;
    }

    /**
     * @return Uncancel
     */
    private function uncancel(): Uncancel
    {
        return new Uncancel(
            $this->createMock(OrderRepositoryInterface::class),
            $this->createMock(ManagerInterface::class),
            $this->createMock(Manager::class),
            $this->createMock(BuckarooLoggerInterface::class)
        );
    }
}
