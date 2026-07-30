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

namespace Buckaroo\Magento2\Test\Unit\Gateway\Request\BasicParameter;


use PHPUnit\Framework\Attributes\DataProvider;
use Buckaroo\Magento2\Gateway\Request\BasicParameter\AmountDebitDataBuilder;
use Buckaroo\Magento2\Test\Unit\Gateway\Request\AbstractDataBuilderTest;

class AmountDebitDataBuilderTest extends AbstractDataBuilderTest
{
    /**
     * @var AmountDebitDataBuilder
     */
    private $builder;

    /**
     * @inheritDoc
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->builder = new AmountDebitDataBuilder();
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

    public function testGetAmount()
    {
        $this->orderMock->method('getGrandTotal')
            ->willReturn(100.00);

        $this->assertEquals(100.00, $this->builder->getAmount($this->orderMock));
    }
}
