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

namespace Buckaroo\Magento2\Test\Unit\Block\Order\Totals;

use Buckaroo\Magento2\Block\Order\Totals\Fee;
use Buckaroo\Magento2\Helper\PaymentFee;
use Magento\Framework\DataObject;
use Magento\Framework\View\Element\Context;
use Magento\Framework\View\LayoutInterface;
use Magento\Sales\Block\Order\Creditmemo\Totals as CreditmemoTotals;
use Magento\Sales\Block\Order\Totals;
use PHPUnit\Framework\TestCase;

class FeeTest extends TestCase
{
    /**
     * @var LayoutInterface|\PHPUnit\Framework\MockObject\MockObject
     */
    private $layout;

    /**
     * @var PaymentFee|\PHPUnit\Framework\MockObject\MockObject
     */
    private $paymentFee;

    /**
     * @var Fee
     */
    private Fee $block;

    protected function setUp(): void
    {
        $this->layout = $this->createMock(LayoutInterface::class);
        $context = $this->createMock(Context::class);
        $context->method('getLayout')->willReturn($this->layout);
        $this->paymentFee = $this->createMock(PaymentFee::class);
        $this->block = new Fee($context, $this->paymentFee);
    }

    public function testAddsEveryFeeLineBeforeTheGrandTotalOfTheParentSource(): void
    {
        $creditmemo = new DataObject(['buckaroo_fee' => 2.5]);
        $parent = $this->createMock(CreditmemoTotals::class);
        $parent->method('getSource')->willReturn($creditmemo);
        $this->givenParent($parent);

        $this->paymentFee->expects($this->once())->method('getTotals')->with($creditmemo)->willReturn([
            ['code' => 'buckaroo_fee', 'value' => 2.5, 'base_value' => 2.5, 'label' => 'Payment Fee (Excl. Tax)'],
            ['code' => 'buckaroo_already_paid', 'value' => 1.0, 'base_value' => 1.0, 'label' => 'Paid with Giftcard'],
        ]);
        $added = [];
        $parent->expects($this->exactly(2))->method('addTotalBefore')
            ->willReturnCallback(function (DataObject $total, $before) use (&$added, $parent) {
                $added[] = [$total->getCode(), $before];
                return $parent;
            });

        $this->block->initTotals();

        $this->assertSame([['buckaroo_fee', 'grand_total'], ['buckaroo_already_paid', 'grand_total']], $added);
    }

    public function testDoesNothingWithoutATotalsParent(): void
    {
        $this->givenParent(false);
        $this->paymentFee->expects($this->never())->method('getTotals');

        $this->assertSame($this->block, $this->block->initTotals());
    }

    public function testAddsNoLineForADocumentWithoutAFee(): void
    {
        $invoice = new DataObject(['buckaroo_fee' => 0]);
        $parent = $this->createMock(Totals::class);
        $parent->method('getSource')->willReturn($invoice);
        $this->givenParent($parent);
        $this->paymentFee->expects($this->once())->method('getTotals')->with($invoice)->willReturn([]);
        $parent->expects($this->never())->method('addTotalBefore');

        $this->block->initTotals();
    }

    /**
     * @param Totals|false $parent
     */
    private function givenParent($parent): void
    {
        $this->layout->method('getParentName')->willReturn($parent ? 'creditmemo_totals' : false);
        $this->layout->method('getBlock')->willReturn($parent);
    }
}
