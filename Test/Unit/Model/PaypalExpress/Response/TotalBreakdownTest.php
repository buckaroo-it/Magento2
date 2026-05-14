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

namespace Buckaroo\Magento2\Test\Unit\Model\PaypalExpress\Response;

use Buckaroo\Magento2\Api\Data\BreakdownItemInterface;
use Buckaroo\Magento2\Api\Data\BreakdownItemInterfaceFactory;
use Buckaroo\Magento2\Model\PaypalExpress\Response\BreakdownItem;
use Buckaroo\Magento2\Model\PaypalExpress\Response\TotalBreakdown;
use Buckaroo\Magento2\Test\BaseTest;
use Magento\Quote\Api\CartTotalRepositoryInterface;
use Magento\Quote\Api\Data\TotalsInterface;
use Magento\Quote\Api\Data\TotalSegmentInterface;
use Magento\Quote\Model\Quote;

/**
 * Covers:
 *  - Fix C: item_total derived via integer-cent arithmetic (no number_format string-base bug).
 *  - Fix C: segment cache — CartTotalRepository::get() called exactly once per breakdown request.
 *
 * PHPUnit mock-builder note:
 *  - onlyMethods()  → methods that are real PHP methods on the class.
 *  - addMethods()   → Magento magic __call methods (getGrandTotal, getQuoteCurrencyCode, etc.)
 *                     that exist only via AbstractModel::__call() / DataObject::getData().
 */
class TotalBreakdownTest extends BaseTest
{
    protected $instanceClass = TotalBreakdown::class;

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * Build a TotalBreakdown wired to a quote with the given totals.
     *
     * getGrandTotal() and getQuoteCurrencyCode() are Magento magic __call methods
     * on Quote — they must be registered via addMethods(), not onlyMethods().
     * getId() is a real method from AbstractModel, so it goes in onlyMethods().
     */
    private function makeBreakdown(
        float $grandTotal,
        float $shippingSegment,
        float $taxSegment,
        string $currency = 'EUR'
    ): TotalBreakdown {
        $quoteMock = $this->getFakeMock(Quote::class)
            ->onlyMethods(['getId'])
            ->addMethods(['getGrandTotal', 'getQuoteCurrencyCode'])
            ->getMock();
        $quoteMock->method('getGrandTotal')->willReturn($grandTotal);
        $quoteMock->method('getQuoteCurrencyCode')->willReturn($currency);
        $quoteMock->method('getId')->willReturn(1);

        $segments = $this->buildSegments($shippingSegment, $taxSegment);

        $totalsMock = $this->getFakeMock(TotalsInterface::class)
            ->onlyMethods(['getTotalSegments'])
            ->getMockForAbstractClass();
        $totalsMock->method('getTotalSegments')->willReturn($segments);

        $repoMock = $this->getFakeMock(CartTotalRepositoryInterface::class)
            ->onlyMethods(['get'])
            ->getMockForAbstractClass();
        $repoMock->method('get')->with(1)->willReturn($totalsMock);

        $factoryMock = $this->getMockBuilder(BreakdownItemInterfaceFactory::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['create'])
            ->getMock();
        $factoryMock->method('create')->willReturnCallback(
            function (array $args): BreakdownItemInterface {
                return new BreakdownItem((float)$args['total'], $args['currencyCode']);
            }
        );

        return $this->getObject(TotalBreakdown::class, [
            'quote'                => $quoteMock,
            'breakdownItemFactory' => $factoryMock,
            'cartTotalRepository'  => $repoMock,
        ]);
    }

    /**
     * Build a segment map keyed by segment code.
     * Segments with a zero value are simply absent (getTotalsOfType must return 0 safely).
     *
     * @return TotalSegmentInterface[]
     */
    private function buildSegments(float $shipping, float $tax): array
    {
        $segments = [];

        foreach (['shipping' => $shipping, 'tax' => $tax] as $code => $value) {
            if ($value === 0.0) {
                continue;
            }
            $seg = $this->getFakeMock(TotalSegmentInterface::class)
                ->onlyMethods(['getValue'])
                ->getMockForAbstractClass();
            $seg->method('getValue')->willReturn((string)$value);
            $segments[$code] = $seg;
        }

        return $segments;
    }

    // -------------------------------------------------------------------------
    // getItemTotal() correctness
    // -------------------------------------------------------------------------

    /**
     * @dataProvider breakdownProvider
     */
    public function testGetItemTotalReturnsGrandTotalMinusShippingMinusTax(
        float $grandTotal,
        float $shipping,
        float $tax,
        string $expectedItemTotal
    ): void {
        $breakdown = $this->makeBreakdown($grandTotal, $shipping, $tax);
        $this->assertSame($expectedItemTotal, $breakdown->getItemTotal()->getValue());
    }

    public static function breakdownProvider(): array
    {
        return [
            'EU store, tax-inclusive, no shipping'     => [29.98, 0.00, 4.79, '25.19'],
            'EU store with shipping cost'              => [34.98, 5.00, 4.79, '25.19'],
            'Zero tax (tax-exclusive, no tax rate)'    => [25.00, 5.00, 0.00, '20.00'],
            'Free shipping / virtual product'          => [29.98, 0.00, 4.79, '25.19'],
            'With coupon applied (reduced total)'      => [14.99, 0.00, 2.39, '12.60'],
            'Buckaroo fee absorbed into item_total'    => [31.37, 0.00, 4.37, '27.00'],
            'Float precision edge case: exact cents'   => [10.00, 3.40, 1.60,  '5.00'],
            'High-value cart'                          => [999.99, 0.00, 159.66, '840.33'],
        ];
    }

    // -------------------------------------------------------------------------
    // Invariant: item_total + shipping + tax_total == grand_total (no off-by-1c)
    // -------------------------------------------------------------------------

    /**
     * @dataProvider breakdownProvider
     */
    public function testBreakdownComponentsSumExactlyToGrandTotal(
        float $grandTotal,
        float $shipping,
        float $tax
    ): void {
        $breakdown = $this->makeBreakdown($grandTotal, $shipping, $tax);

        $sum = round(
            (float)$breakdown->getItemTotal()->getValue()
            + (float)$breakdown->getShipping()->getValue()
            + (float)$breakdown->getTaxTotal()->getValue(),
            2
        );

        $this->assertSame(round($grandTotal, 2), $sum);
    }

    // -------------------------------------------------------------------------
    // Repository caching — CartTotalRepository::get() called exactly once
    // -------------------------------------------------------------------------

    public function testCartTotalRepositoryIsCalledOnlyOncePerBreakdownRequest(): void
    {
        $quoteMock = $this->getFakeMock(Quote::class)
            ->onlyMethods(['getId'])
            ->addMethods(['getGrandTotal', 'getQuoteCurrencyCode'])
            ->getMock();
        $quoteMock->method('getGrandTotal')->willReturn(29.98);
        $quoteMock->method('getQuoteCurrencyCode')->willReturn('EUR');
        $quoteMock->method('getId')->willReturn(42);

        $taxSeg = $this->getFakeMock(TotalSegmentInterface::class)
            ->onlyMethods(['getValue'])->getMockForAbstractClass();
        $taxSeg->method('getValue')->willReturn('4.79');

        $totalsMock = $this->getFakeMock(TotalsInterface::class)
            ->onlyMethods(['getTotalSegments'])->getMockForAbstractClass();
        $totalsMock->method('getTotalSegments')->willReturn(['tax' => $taxSeg]);

        $repoMock = $this->getFakeMock(CartTotalRepositoryInterface::class)
            ->onlyMethods(['get'])->getMockForAbstractClass();

        // THE KEY ASSERTION: exactly 1 repo call even though 3 getters are invoked
        $repoMock->expects($this->once())
            ->method('get')
            ->with(42)
            ->willReturn($totalsMock);

        $factoryMock = $this->getMockBuilder(BreakdownItemInterfaceFactory::class)
            ->disableOriginalConstructor()->onlyMethods(['create'])->getMock();
        $factoryMock->method('create')->willReturnCallback(
            fn(array $a) => new BreakdownItem((float)$a['total'], $a['currencyCode'])
        );

        $breakdown = $this->getObject(TotalBreakdown::class, [
            'quote'                => $quoteMock,
            'breakdownItemFactory' => $factoryMock,
            'cartTotalRepository'  => $repoMock,
        ]);

        $breakdown->getItemTotal();
        $breakdown->getShipping();
        $breakdown->getTaxTotal();
    }

    // -------------------------------------------------------------------------
    // Missing segments handled gracefully
    // -------------------------------------------------------------------------

    public function testMissingShippingSegmentTreatedAsZero(): void
    {
        $breakdown = $this->makeBreakdown(29.98, 0.00, 4.79);
        $this->assertSame('0.00', $breakdown->getShipping()->getValue());
    }

    public function testMissingTaxSegmentTreatedAsZero(): void
    {
        $breakdown = $this->makeBreakdown(25.00, 5.00, 0.00);
        $this->assertSame('0.00', $breakdown->getTaxTotal()->getValue());
    }

    // -------------------------------------------------------------------------
    // Currency code propagates to every BreakdownItem
    // -------------------------------------------------------------------------

    public function testCurrencyCodeIsProvidedOnAllBreakdownItems(): void
    {
        $breakdown = $this->makeBreakdown(29.98, 5.00, 4.79, 'USD');

        $this->assertSame('USD', $breakdown->getItemTotal()->getCurrencyCode());
        $this->assertSame('USD', $breakdown->getShipping()->getCurrencyCode());
        $this->assertSame('USD', $breakdown->getTaxTotal()->getCurrencyCode());
    }
}
