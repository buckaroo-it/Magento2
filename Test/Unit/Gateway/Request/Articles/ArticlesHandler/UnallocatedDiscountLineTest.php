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

namespace Buckaroo\Magento2\Test\Unit\Gateway\Request\Articles\ArticlesHandler;

use Buckaroo\Magento2\Gateway\Request\Articles\ArticlesHandler\BillinkHandler;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Invoice;
use Magento\Sales\Model\Order\Item as OrderItem;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * BTI-1312 — when the item lines carry the discount per item (no global discount line), any
 * order-level discount a third-party module never allocated to the items is missing from the
 * request. It must be added back as a NEGATIVE discount line.
 *
 * The reconciliation this replaced had the sign inverted: it added a POSITIVE "Extra Fees"
 * article, pushing the capture above the authorized amount, which Klarna refused with
 * CAPTURE_NOT_ALLOWED.
 */
class UnallocatedDiscountLineTest extends TestCase
{
    /**
     * @param float      $orderDiscount        Negative, as stored on the order.
     * @param float      $orderCompensation
     * @param array      $itemDiscounts        [discount, compensation] per item.
     * @param float      $orderSubtotal
     * @param float      $invoiceSubtotal
     * @param bool       $pricesIncludeTax
     * @param float|null $expectedLineAmount   Null when no line must be produced.
     */
    #[DataProvider('unallocatedDiscountProvider')]
    public function testUnallocatedOrderDiscountIsAddedBackAsANegativeLine(
        float $orderDiscount,
        float $orderCompensation,
        array $itemDiscounts,
        float $orderSubtotal,
        float $invoiceSubtotal,
        bool $pricesIncludeTax,
        ?float $expectedLineAmount
    ) {
        $handler = $this->buildHandler($pricesIncludeTax);

        $items = [];
        foreach ($itemDiscounts as [$discount, $compensation]) {
            $item = $this->createMock(OrderItem::class);
            $item->method('getDiscountAmount')->willReturn($discount);
            $item->method('getDiscountTaxCompensationAmount')->willReturn($compensation);
            $item->method('getRowTotal')->willReturn(100.0);
            $item->method('getTaxPercent')->willReturn(21.0);
            $items[] = $item;
        }

        $order = $this->createMock(Order::class);
        $order->method('getDiscountAmount')->willReturn($orderDiscount);
        $order->method('getDiscountTaxCompensationAmount')->willReturn($orderCompensation);
        $order->method('getAllVisibleItems')->willReturn($items);
        $order->method('getSubtotal')->willReturn($orderSubtotal);

        $invoice = $this->createMock(Invoice::class);
        $invoice->method('getSubtotal')->willReturn($invoiceSubtotal);

        $handler->setOrder($order);

        $line = $this->invokeUnallocatedDiscountLine($handler, $invoice);

        if ($expectedLineAmount === null) {
            $this->assertSame([], $line, 'No discount line must be produced');
            return;
        }

        $this->assertNotEmpty($line);
        $this->assertSame('unallocated-discount', $line['identifier']);
        $this->assertEquals(-$expectedLineAmount, $line['price'], 'The line must be negative');
        $this->assertSame(1, $line['quantity']);
    }

    public static function unallocatedDiscountProvider(): array
    {
        return [
            // The merchant's shape: order discount 6.05 (5.00 + 1.05 compensation), items
            // received 4.89, so 1.16 is missing.
            'unallocated remainder is added back' =>
                [-5.00, 1.05, [[0.85, 0.15], [3.19, 0.70]], 100.0, 100.0, false, 1.16],
            'fully allocated discount produces no line' =>
                [-5.00, 1.05, [[1.00, 0.20], [4.00, 0.85]], 100.0, 100.0, false, null],
            'order without a discount produces no line' =>
                [0.0, 0.0, [[0.0, 0.0]], 100.0, 100.0, false, null],
            'a sub-cent remainder is ignored' =>
                [-5.00, 0.0, [[4.995, 0.0]], 100.0, 100.0, true, null],
            // Compensation only counts as discount when catalog prices exclude tax, matching
            // getInvoiceItemsLines() and getDiscountAmount().
            'compensation is ignored when prices include tax' =>
                [-6.05, 1.05, [[4.89, 0.85]], 100.0, 100.0, true, 1.16],
            // A partial invoice may only carry its share of the remainder.
            'partial invoice carries a pro-rated share' =>
                [-5.00, 1.05, [[0.85, 0.15], [3.19, 0.70]], 100.0, 50.0, false, 0.58],
            'invoice subtotal above the order subtotal is clamped' =>
                [-5.00, 1.05, [[0.85, 0.15], [3.19, 0.70]], 100.0, 250.0, false, 1.16],
        ];
    }

    /**
     * @param bool $pricesIncludeTax
     *
     * @return BillinkHandler
     */
    private function buildHandler(bool $pricesIncludeTax): BillinkHandler
    {
        $reflection = new \ReflectionClass(BillinkHandler::class);
        /** @var BillinkHandler $handler */
        $handler = $reflection->newInstanceWithoutConstructor();

        $scopeConfig = $this->createMock(\Magento\Framework\App\Config\ScopeConfigInterface::class);
        $scopeConfig->method('getValue')->willReturn($pricesIncludeTax ? '1' : '0');

        $this->setPrivateProperty($handler, 'scopeConfig', $scopeConfig);
        $this->setPrivateProperty(
            $handler,
            'buckarooLog',
            $this->createMock(\Buckaroo\Magento2\Logging\BuckarooLoggerInterface::class)
        );

        return $handler;
    }

    /**
     * @param object $object
     * @param string $name
     * @param mixed  $value
     *
     * @return void
     */
    private function setPrivateProperty(object $object, string $name, $value): void
    {
        $reflection = new \ReflectionClass($object);
        while (!$reflection->hasProperty($name) && $reflection->getParentClass()) {
            $reflection = $reflection->getParentClass();
        }
        $property = $reflection->getProperty($name);
        $property->setAccessible(true);
        $property->setValue($object, $value);
    }

    /**
     * @param BillinkHandler $handler
     * @param Invoice        $invoice
     *
     * @return array
     */
    private function invokeUnallocatedDiscountLine(BillinkHandler $handler, Invoice $invoice): array
    {
        $method = new \ReflectionMethod($handler, 'getUnallocatedDiscountLine');
        $method->setAccessible(true);

        return $method->invoke($handler, $invoice);
    }
}
