<?php

namespace Buckaroo\Magento2\Test\Unit\Gateway\Request\BasicParameter;

use Buckaroo\Magento2\Gateway\Request\BasicParameter\AmountDebitDataBuilder;
use Buckaroo\Magento2\Service\DataBuilderService;
use Buckaroo\Magento2\Gateway\Data\Order\OrderAdapter;
use Magento\Payment\Gateway\Data\PaymentDataObjectInterface;
use Magento\Sales\Model\Order;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class AmountDebitDataBuilderTest extends TestCase
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
     * @dataProvider amountDataProvider
     *
     * @param float $grandTotal
     * @param float $baseGrandTotal
     * @param string $orderCurrency
     * @param string $serviceCurrency
     * @param float $expectedAmount
     * @throws \Exception
     */
    public function testBuild($grandTotal, $baseGrandTotal, $orderCurrency, $serviceCurrency, $expectedAmount)
    {
        $this->orderMock->expects($this->atMost(1))
            ->method('getGrandTotal')
            ->willReturn($grandTotal);
        $this->orderMock->expects($this->atMost(1))
            ->method('getBaseGrandTotal')
            ->willReturn($baseGrandTotal);
        $this->orderMock->expects($this->once())
            ->method('getOrderCurrencyCode')
            ->willReturn($orderCurrency);

        if ($grandTotal == null || $baseGrandTotal == null) {
            $this->expectException(\Exception::class);
            $this->expectExceptionMessage('Total of the order can not be empty.');
        }

        $paymentDOMock = $this->getMockBuilder(PaymentDataObjectInterface::class)
            ->disableOriginalConstructor()
            ->getMock();

        $orderAdapter = $this->getMockBuilder(OrderAdapter::class)
            ->disableOriginalConstructor()
            ->getMock();

        $orderAdapter->expects($this->once())
            ->method('getOrder')
            ->willReturn($this->orderMock);

        $paymentDOMock->expects($this->once())
            ->method('getOrder')
            ->willReturn($orderAdapter);

        $buildSubject = [
            'payment' => $paymentDOMock
        ];

        $this->dataBuilderServiceMock->expects($this->once())
            ->method('getElement')
            ->with('currency')
            ->willReturn($serviceCurrency);

        $result = $this->builder->build($buildSubject);

        $this->assertEquals($expectedAmount, $result[AmountDebitDataBuilder::AMOUNT_DEBIT]);
    }

    public function amountDataProvider(): array
    {
        return [
            'valid grandTotal' => [
                'grandTotal' => 100,
                'baseGrandTotal' => 80,
                'orderCurrency' => 'USD',
                'serviceCurrency' => 'USD',
                'expectedAmount' => 100
            ],
            'valid baseGrandTotal' => [
                'grandTotal' => 100,
                'baseGrandTotal' => 90,
                'orderCurrency' => 'USD',
                'serviceCurrency' => 'EUR',
                'expectedAmount' => 90
            ],
            'invalid grandTotal null' => [
                'grandTotal' => null,
                'baseGrandTotal' => 100,
                'orderCurrency' => 'USD',
                'serviceCurrency' => 'USD',
                'expectedAmount' => 0
            ],
            'invalid baseGrandTotal null' => [
                'grandTotal' => 100,
                'baseGrandTotal' => null,
                'orderCurrency' => 'USD',
                'serviceCurrency' => 'EUR',
                'expectedAmount' => 100
            ],
            'valid but null serviceCurrency' => [
                'grandTotal' => 100,
                'baseGrandTotal' => 80,
                'orderCurrency' => 'USD',
                'serviceCurrency' => null,
                'expectedAmount' => 80
            ],
        ];
    }

    public function testGetAmount()
    {
        $this->orderMock->expects($this->once())
            ->method('getOrderCurrencyCode')
            ->willReturn('USD');

        $this->dataBuilderServiceMock->expects($this->once())
            ->method('getElement')
            ->with('currency')
            ->willReturn('USD');

        $this->orderMock->expects($this->once())
            ->method('getGrandTotal')
            ->willReturn(100.00);

        $this->assertEquals(100.00, $this->builder->getAmount($this->orderMock));
    }

    public function testSetAmount()
    {
        $this->orderMock->expects($this->once())
            ->method('getOrderCurrencyCode')
            ->willReturn('USD');

        $this->dataBuilderServiceMock->expects($this->once())
            ->method('getElement')
            ->with('currency')
            ->willReturn('EUR');

        $this->orderMock->expects($this->once())
            ->method('getBaseGrandTotal')
            ->willReturn(80.00);

        $this->builder->setAmount($this->orderMock);

        $this->assertEquals(80.00, $this->builder->getAmount());
    }

    /**
     * @inheritDoc
     */
    protected function setUp(): void
    {
        $this->dataBuilderServiceMock = $this->getMockBuilder(DataBuilderService::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->orderMock = $this->getMockBuilder(Order::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->builder = new AmountDebitDataBuilder($this->dataBuilderServiceMock);
    }
}
