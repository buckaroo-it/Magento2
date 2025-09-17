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

use Buckaroo\Magento2\Gateway\Request\BasicParameter\AmountDebitDataBuilder;
use Buckaroo\Magento2\Service\DataBuilderService;
use Buckaroo\Magento2\Test\Unit\Gateway\Request\AbstractDataBuilderTest;
use PHPUnit\Framework\MockObject\MockObject;

class AmountDebitDataBuilderTest extends AbstractDataBuilderTest
{
    /**
     * @var DataBuilderService|MockObject
     */
    private $dataBuilderServiceMock;

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

        $this->dataBuilderServiceMock = $this->createMock(DataBuilderService::class);


        $this->builder = new AmountDebitDataBuilder($this->dataBuilderServiceMock);
    }

    /**
     * @dataProvider amountDataProvider
     *
     * @param float|null $grandTotal
     * @param float|null $baseGrandTotal
     * @param string $orderCurrency
     * @param string|null $serviceCurrency
     * @param float $expectedAmount
     * @throws \Exception
     */
    public function testBuild(
        ?float $grandTotal,
        ?float $baseGrandTotal,
        string $orderCurrency,
        ?string $serviceCurrency,
        float $expectedAmount
    ) {
        $this->orderMock->expects($this->atMost(1))
            ->method('getGrandTotal')
            ->willReturn($grandTotal);
        $this->orderMock->expects($this->atMost(1))
            ->method('getBaseGrandTotal')
            ->willReturn($baseGrandTotal);
        $this->orderMock->method('getOrderCurrencyCode')
            ->willReturn($orderCurrency);

        if ($grandTotal == null || $baseGrandTotal == null) {
            $this->expectException(\Exception::class);
            $this->expectExceptionMessage('Total of the order can not be empty.');
        }

        $buildSubject = [
            'payment' => $this->getPaymentDOMock()
        ];

        $this->dataBuilderServiceMock->method('getElement')
            ->with('currency')
            ->willReturn($serviceCurrency);

        $result = $this->builder->build($buildSubject);

        $this->assertEquals($expectedAmount, $result[AmountDebitDataBuilder::AMOUNT_DEBIT]);
    }

    public static function amountDataProvider(): array
    {
        return [
            'valid grandTotal'               => [
                'grandTotal'      => 100,
                'baseGrandTotal'  => 80,
                'orderCurrency'   => 'USD',
                'serviceCurrency' => 'USD',
                'expectedAmount'  => 100
            ],
            'valid baseGrandTotal'           => [
                'grandTotal'      => 100,
                'baseGrandTotal'  => 90,
                'orderCurrency'   => 'USD',
                'serviceCurrency' => 'EUR',
                'expectedAmount'  => 90
            ],
            'invalid grandTotal null'        => [
                'grandTotal'      => null,
                'baseGrandTotal'  => 100,
                'orderCurrency'   => 'USD',
                'serviceCurrency' => 'USD',
                'expectedAmount'  => 0
            ],
            'invalid baseGrandTotal null'    => [
                'grandTotal'      => 100,
                'baseGrandTotal'  => null,
                'orderCurrency'   => 'USD',
                'serviceCurrency' => 'EUR',
                'expectedAmount'  => 100
            ],
            'valid but null serviceCurrency' => [
                'grandTotal'      => 100,
                'baseGrandTotal'  => 80,
                'orderCurrency'   => 'USD',
                'serviceCurrency' => null,
                'expectedAmount'  => 80
            ],
        ];
    }

    public function testGetAmount()
    {
        $this->orderMock->method('getOrderCurrencyCode')
            ->willReturn('USD');

        $this->dataBuilderServiceMock->method('getElement')
            ->with('currency')
            ->willReturn('USD');

        $this->orderMock->method('getGrandTotal')
            ->willReturn(100.00);

        $this->assertEquals(100.00, $this->builder->getAmount($this->orderMock));
    }

    public function testSetAmount()
    {
        $this->orderMock->method('getOrderCurrencyCode')
            ->willReturn('USD');

        $this->dataBuilderServiceMock->method('getElement')
            ->with('currency')
            ->willReturn('EUR');

        $this->orderMock->method('getBaseGrandTotal')
            ->willReturn(80.00);

        $this->builder->setAmount($this->orderMock);

        $this->assertEquals(80.00, $this->builder->getAmount());
    }
}
