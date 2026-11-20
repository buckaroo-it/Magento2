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

namespace Buckaroo\Magento2\Test\Unit\Block\Adminhtml\Sales;

use Buckaroo\Magento2\Block\Adminhtml\Sales\Totals;
use Buckaroo\Magento2\Helper\PaymentFee;
use Magento\Framework\DataObject;
use Magento\Framework\Pricing\PriceCurrencyInterface;
use Magento\Framework\View\Element\AbstractBlock;
use Magento\Framework\View\Element\Template\Context;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Creditmemo;
use Magento\Sales\Model\Order\Invoice;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class TotalsTest extends TestCase
{
    private const FEE_TOTAL = [
        'code'       => 'buckaroo_fee',
        'value'      => 2.5,
        'base_value' => 2.5,
        'label'      => 'Payment Fee (Excl. Tax)',
        'extra_info' => [],
    ];

    private const ALREADY_PAID_TOTAL = [
        'code'           => 'buckaroo_already_paid',
        'value'          => -10.0,
        'base_value'     => -10.0,
        'label'          => 'Paid with Voucher',
        'extra_info'     => [],
        'block_name'     => 'buckaroo_already_paid',
        'transaction_id' => 'TX1|voucher|10',
    ];

    /** @var PaymentFee&MockObject */
    private $helper;

    /** @var Totals&MockObject */
    private $block;

    /** @var array<int, DataObject> */
    private $addedTotals = [];

    protected function setUp(): void
    {
        $this->helper = $this->createMock(PaymentFee::class);

        $this->block = $this->getMockBuilder(Totals::class)
            ->setConstructorArgs([
                $this->createMock(Context::class),
                $this->helper,
                $this->createMock(PriceCurrencyInterface::class),
            ])
            ->onlyMethods(['getParentBlock'])
            ->getMock();
    }

    /**
     * Second credit memo of an order whose fee the first credit memo already refunded
     */
    public function testSavedCreditmemoWithoutRefundedFeeShowsNoFee(): void
    {
        [$creditmemo, $invoice] = $this->creditmemo(42);
        $this->helperTotals([
            [$creditmemo, [self::ALREADY_PAID_TOTAL]],
            [$invoice, [self::FEE_TOTAL]],
        ]);

        $totals = $this->renderedTotals($creditmemo);

        $this->assertSame([self::ALREADY_PAID_TOTAL], $totals);
    }

    /**
     * The fee a saved credit memo refunded has no block_name: Magento's totals block formats it
     */
    public function testSavedCreditmemoShowsItsRefundedFeeAsPlainTotal(): void
    {
        [$creditmemo, $invoice] = $this->creditmemo(42);
        $this->helperTotals([
            [$creditmemo, [self::FEE_TOTAL]],
            [$invoice, [self::FEE_TOTAL]],
        ]);

        $totals = $this->renderedTotals($creditmemo);

        $this->assertSame([self::FEE_TOTAL], $totals);
        $this->assertArrayNotHasKey('block_name', $totals[0]);
    }

    public function testInitTotalsAddsSavedCreditmemoFeeBeforeGrandTotal(): void
    {
        [$creditmemo, $invoice] = $this->creditmemo(42);
        $this->helperTotals([
            [$creditmemo, [self::FEE_TOTAL]],
            [$invoice, [self::FEE_TOTAL]],
        ]);
        $this->block->method('getParentBlock')->willReturn($this->parentBlock($creditmemo));

        $this->block->initTotals();

        $this->assertCount(1, $this->addedTotals);
        $this->assertSame('buckaroo_fee', $this->addedTotals[0]->getCode());
        $this->assertNull($this->addedTotals[0]->getBlockName());
    }

    /**
     * New credit memo form: the invoice fee is offered as a checked refund checkbox
     */
    public function testNewCreditmemoOffersInvoiceFeeSelectedWhenRefunded(): void
    {
        [$creditmemo, $invoice] = $this->creditmemo(null);
        $this->helperTotals([
            [$creditmemo, [self::FEE_TOTAL, self::ALREADY_PAID_TOTAL]],
            [$invoice, [self::FEE_TOTAL]],
        ]);

        $totals = array_values($this->renderedTotals($creditmemo));

        $this->assertCount(2, $totals);
        $this->assertSame('buckaroo_already_paid', $totals[0]['code']);
        $this->assertSame('buckaroo_fee', $totals[1]['code']);
        $this->assertSame('buckaroo_fee', $totals[1]['block_name']);
        $this->assertTrue($totals[1]['is_selected']);
    }

    /**
     * New credit memo form after the fee was unticked (or already refunded): offered, not selected
     */
    public function testNewCreditmemoOffersInvoiceFeeUnselectedWhenNotRefunded(): void
    {
        [$creditmemo, $invoice] = $this->creditmemo(null);
        $this->helperTotals([
            [$creditmemo, []],
            [$invoice, [self::FEE_TOTAL]],
        ]);

        $totals = array_values($this->renderedTotals($creditmemo));

        $this->assertCount(1, $totals);
        $this->assertSame('buckaroo_fee', $totals[0]['block_name']);
        $this->assertFalse($totals[0]['is_selected']);
        $this->assertSame(2.5, $totals[0]['value']);
    }

    public function testNewCreditmemoWithoutInvoiceOffersOrderFee(): void
    {
        $order = $this->createMock(Order::class);
        $creditmemo = $this->createMock(Creditmemo::class);
        $creditmemo->method('getId')->willReturn(null);
        $creditmemo->method('getOrder')->willReturn($order);
        $creditmemo->method('getInvoice')->willReturn(null);
        $this->helperTotals([
            [$creditmemo, [self::FEE_TOTAL]],
            [$order, [self::FEE_TOTAL]],
        ]);

        $totals = array_values($this->renderedTotals($creditmemo));

        $this->assertSame('buckaroo_fee', $totals[0]['block_name']);
        $this->assertTrue($totals[0]['is_selected']);
    }

    public function testOrderAndInvoiceUseTheirOwnTotals(): void
    {
        $invoice = $this->createMock(Invoice::class);
        $this->helperTotals([[$invoice, [self::FEE_TOTAL]]]);

        $this->assertSame([self::FEE_TOTAL], $this->renderedTotals($invoice));
    }

    /**
     * @param int|null $id
     *
     * @return array{0: Creditmemo&MockObject, 1: Invoice&MockObject}
     */
    private function creditmemo(?int $id): array
    {
        $invoice = $this->createMock(Invoice::class);
        $creditmemo = $this->createMock(Creditmemo::class);
        $creditmemo->method('getId')->willReturn($id);
        $creditmemo->method('getOrder')->willReturn($this->createMock(Order::class));
        $creditmemo->method('getInvoice')->willReturn($invoice);

        return [$creditmemo, $invoice];
    }

    /**
     * @param array<int, array{0: object, 1: array}> $map
     */
    private function helperTotals(array $map): void
    {
        $this->helper->method('getTotals')->willReturnCallback(
            function ($source) use ($map) {
                foreach ($map as [$object, $totals]) {
                    if ($object === $source) {
                        return $totals;
                    }
                }
                $this->fail('Unexpected totals source');
            }
        );
    }

    /**
     * @param object $source
     *
     * @return AbstractBlock&MockObject
     */
    private function parentBlock($source)
    {
        $parent = $this->getMockBuilder(AbstractBlock::class)
            ->disableOriginalConstructor()
            ->addMethods(['getSource', 'addTotalBefore'])
            ->getMock();
        $parent->method('getSource')->willReturn($source);
        $parent->method('addTotalBefore')->willReturnCallback(
            function (DataObject $total, $before) use ($parent) {
                $this->assertSame('grand_total', $before);
                $this->addedTotals[] = $total;
                return $parent;
            }
        );

        return $parent;
    }

    /**
     * @param object $source
     */
    private function renderedTotals($source): array
    {
        $this->block->method('getParentBlock')->willReturn($this->parentBlock($source));

        return $this->block->getTotals();
    }
}
