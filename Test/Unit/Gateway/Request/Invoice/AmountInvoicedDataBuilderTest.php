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

namespace Buckaroo\Magento2\Test\Unit\Gateway\Request\Invoice;

use Buckaroo\Magento2\Gateway\Request\Articles\ArticleTotalRegistry;
use Buckaroo\Magento2\Gateway\Request\Invoice\AmountInvoicedDataBuilder;
use Buckaroo\Magento2\Helper\Data as BuckarooHelper;
use Buckaroo\Magento2\Test\Unit\Gateway\Request\AbstractDataBuilderTest;
use Magento\Sales\Model\Order\Invoice;
use Magento\Sales\Model\ResourceModel\Order\Invoice\Collection as InvoiceCollection;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * BTI-1312 — the amount sent with a capture must equal the article lines that accompany it.
 *
 * The gateway rejects a request where the two disagree ("Sum of given articles (209,08) is not
 * equal to the given amount (209,09)"), and grand totals are ROUNDED values that per-line sums
 * cannot always reproduce, so the amount is taken from the lines.
 *
 * Magento also derives invoice totals from the order items
 * (Magento\Sales\Model\Order\Invoice\Total\Discount), so a third-party module applying a
 * cart-level discount without allocating it to the items yields an invoice grand total ABOVE
 * the order grand total; capturing that is refused with CAPTURE_NOT_ALLOWED and the
 * reservation expires uncaptured. Hence the cap at the remaining authorized amount.
 */
class AmountInvoicedDataBuilderTest extends AbstractDataBuilderTest
{
    private const INCREMENT_ID = '000000163';

    /**
     * @var AmountInvoicedDataBuilder
     */
    private $builder;

    /**
     * @var ArticleTotalRegistry
     */
    private $articleTotalRegistry;

    /**
     * @inheritDoc
     */
    protected function setUp(): void
    {
        parent::setUp();

        $helperMock = $this->createMock(BuckarooHelper::class);
        $helperMock->method('areEqualAmounts')
            ->willReturnCallback(
                static fn ($left, $right): bool => abs((float)$left - (float)$right) < 0.01
            );

        $this->articleTotalRegistry = new ArticleTotalRegistry();
        $this->builder = new AmountInvoicedDataBuilder($helperMock, $this->articleTotalRegistry);
    }

    /**
     * @param float      $orderGrandTotal
     * @param float      $totalPaid
     * @param float      $invoiceGrandTotal
     * @param float|null $articleSum        Null when no articles were built this request.
     * @param float      $expectedAmount
     *
     * @throws \Exception
     */
    #[DataProvider('captureAmountDataProvider')]
    public function testCaptureAmountFollowsTheArticleLinesAndNeverExceedsTheAuthorizedAmount(
        float $orderGrandTotal,
        float $totalPaid,
        float $invoiceGrandTotal,
        ?float $articleSum,
        float $expectedAmount
    ) {
        if ($articleSum !== null) {
            $this->articleTotalRegistry->set(self::INCREMENT_ID, $articleSum);
        }

        $invoiceMock = $this->createMock(Invoice::class);
        $invoiceMock->method('getGrandTotal')->willReturn($invoiceGrandTotal);

        $collectionMock = $this->createMock(InvoiceCollection::class);
        $collectionMock->method('count')->willReturn(1);
        $collectionMock->method('getLastItem')->willReturn($invoiceMock);

        $this->orderMock->method('getIncrementId')->willReturn(self::INCREMENT_ID);
        $this->orderMock->method('getGrandTotal')->willReturn($orderGrandTotal);
        $this->orderMock->method('getTotalPaid')->willReturn($totalPaid);
        $this->orderMock->method('getInvoiceCollection')->willReturn($collectionMock);

        $result = $this->builder->build([
            'payment' => $this->getPaymentDOMock()
        ]);

        $this->assertEquals($expectedAmount, $result['amountDebit']);
    }

    public static function captureAmountDataProvider(): array
    {
        return [
            // Verified end to end on order 000000163: reserved 209.09, invoice inflated to
            // 210.25 by 1.16 of unallocated cart discount, lines summed to 209.08, and 209.08
            // was captured successfully (brq_statuscode 190).
            'article sum is sent even when the invoice total is inflated' =>
                [209.09, 0.0, 210.25, 209.08, 209.08],
            // The cent below the rounded grand total is exactly why the amount follows the lines.
            'article sum one cent below the rounded grand total is sent as-is' =>
                [255.55, 0.0, 255.55, 255.54, 255.54],
            'article sum above the authorized amount is capped' =>
                [41.90, 0.0, 43.06, 43.06, 41.90],
            'second partial capture is capped at what is left' =>
                [100.00, 70.00, 40.00, 40.00, 30.00],
            'partial capture below the authorized amount is untouched' =>
                [100.00, 0.0, 40.00, 40.00, 40.00],
            // Without articles (other capture requests) the invoice total is still used.
            'falls back to the invoice total when no articles were built' =>
                [41.90, 0.0, 41.90, null, 41.90],
            'fallback invoice total is capped too' =>
                [41.90, 0.0, 43.06, null, 41.90],
            'nothing left to capture leaves the amount alone' =>
                [100.00, 100.00, 40.00, 40.00, 40.00],
        ];
    }
}
