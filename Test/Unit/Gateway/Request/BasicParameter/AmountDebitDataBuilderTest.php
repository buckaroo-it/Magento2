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

namespace Buckaroo\Magento2\Test\Unit\Gateway\Request\BasicParameter;


use PHPUnit\Framework\Attributes\DataProvider;
use Buckaroo\Magento2\Gateway\Request\Articles\ArticleTotalRegistry;
use Buckaroo\Magento2\Gateway\Request\BasicParameter\AmountDebitDataBuilder;
use Buckaroo\Magento2\Test\Unit\Gateway\Request\AbstractDataBuilderTest;

class AmountDebitDataBuilderTest extends AbstractDataBuilderTest
{
    /**
     * @var AmountDebitDataBuilder
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

        $this->articleTotalRegistry = new ArticleTotalRegistry();
        $this->builder = new AmountDebitDataBuilder($this->articleTotalRegistry);
    }

    /**
     *
     * @param float|null $grandTotal
     * @param float      $expectedAmount
     *
     * @throws \Exception
     */
    #[DataProvider('amountDataProvider')]
    public function testBuild(?float $grandTotal, float $expectedAmount)
    {
        $this->orderMock->method('getGrandTotal')
            ->willReturn($grandTotal);

        if ($expectedAmount == 0) {
            $this->expectException(\Exception::class);
            $this->expectExceptionMessage('Total of the order can not be empty.');
        }

        $result = $this->builder->build([
            'payment' => $this->getPaymentDOMock()
        ]);

        $this->assertEquals($expectedAmount, $result[AmountDebitDataBuilder::AMOUNT_DEBIT]);
    }

    public static function amountDataProvider(): array
    {
        return [
            'order grand total is sent as-is'    => [
                'grandTotal'     => 100,
                'expectedAmount' => 100
            ],
            'PLN order sends the PLN grand total' => [
                'grandTotal'     => 167.67,
                'expectedAmount' => 167.67
            ],
            'missing grand total throws'         => [
                'grandTotal'     => null,
                'expectedAmount' => 0
            ],
            'zero grand total throws'            => [
                'grandTotal'     => 0.0,
                'expectedAmount' => 0
            ],
        ];
    }

    /**
     * When article lines accompany the request the amount must equal their sum, or
     * the gateway rejects it (Error 500108 Payment_OrderAmount_ValueInvalid: "OrderAmount
     * should be equal to sum of order items price incl."). A grand total is rounded and cannot
     * always be reproduced by per-line sums.
     *
     * @throws \Exception
     */
    public function testArticleLineTotalIsPreferredOverTheRoundedGrandTotal()
    {
        $this->orderMock->method('getIncrementId')->willReturn('000000165');
        $this->orderMock->method('getGrandTotal')->willReturn(209.09);

        $this->articleTotalRegistry->set(ArticleTotalRegistry::CONTEXT_ORDER, '000000165', 209.08);

        $result = $this->builder->build(['payment' => $this->getPaymentDOMock()]);

        $this->assertEquals(209.08, $result[AmountDebitDataBuilder::AMOUNT_DEBIT]);
    }

    /**
     * Methods that send no article lines keep using the grand total.
     *
     * @throws \Exception
     */
    public function testGrandTotalIsUsedWhenNoArticlesWereBuilt()
    {
        $this->orderMock->method('getIncrementId')->willReturn('000000166');
        $this->orderMock->method('getGrandTotal')->willReturn(209.09);

        $result = $this->builder->build(['payment' => $this->getPaymentDOMock()]);

        $this->assertEquals(209.09, $result[AmountDebitDataBuilder::AMOUNT_DEBIT]);
    }

    public function testGetAmount()
    {
        $this->orderMock->method('getGrandTotal')
            ->willReturn(100.00);

        $this->assertEquals(100.00, $this->builder->getAmount($this->orderMock));
    }
}
