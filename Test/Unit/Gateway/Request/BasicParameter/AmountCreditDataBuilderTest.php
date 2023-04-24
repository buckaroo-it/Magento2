<?php

declare(strict_types=1);

namespace Buckaroo\Magento2\Test\Unit\Gateway\Request\BasicParameter;

use Buckaroo\Magento2\Gateway\Helper\SubjectReader;
use Buckaroo\Magento2\Gateway\Request\BasicParameter\AmountCreditDataBuilder;
use Buckaroo\Magento2\Service\DataBuilderService;
use Buckaroo\Magento2\Service\RefundGroupTransactionService;
use Buckaroo\Magento2\Test\Unit\Gateway\Request\AbstractDataBuilderTest;
use Magento\Payment\Gateway\Http\ClientException;
use Magento\Payment\Gateway\Http\ConverterException;
use PHPUnit\Framework\MockObject\MockObject;

class AmountCreditDataBuilderTest extends AbstractDataBuilderTest
{
    /**
     * @var MockObject|DataBuilderService
     */
    private $dataBuilderServiceMock;

    /**
     * @var MockObject|RefundGroupTransactionService
     */
    private $refundGroupServiceMock;

    /**
     * @var AmountCreditDataBuilder
     */
    private AmountCreditDataBuilder $amountCreditDataBuilder;

    protected function setUp(): void
    {
        parent::setUp();

        $this->dataBuilderServiceMock = $this->getMockBuilder(DataBuilderService::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->refundGroupServiceMock = $this->getMockBuilder(RefundGroupTransactionService::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->amountCreditDataBuilder = new AmountCreditDataBuilder(
            $this->dataBuilderServiceMock,
            $this->refundGroupServiceMock
        );
    }

    /**
     * @dataProvider buildDataProvider
     * @param float $amount
     * @param float $amountLeftToRefund
     * @param float $expectedResult
     * @throws ClientException
     * @throws ConverterException
     */
    public function testBuild(float $amount, float $amountLeftToRefund, float $expectedResult): void
    {
        $this->orderMock->method('getBaseGrandTotal')->willReturn($amount);
        $this->orderMock->method('getOrderCurrencyCode')->willReturn('USD');
        $this->orderMock->method('getBaseToOrderRate')->willReturn(1.0);

        $buildSubject = [
            'payment' => $this->getPaymentDOMock(),
            'amount'  => $amount
        ];

        $this->refundGroupServiceMock->method('getAmountLeftToRefund')->willReturn($amount);

        $result = $this->amountCreditDataBuilder->build($buildSubject);

        $this->assertEquals($expectedResult, $result[AmountCreditDataBuilder::AMOUNT_CREDIT]);
    }

    /**
     * @return array
     */
    public function buildDataProvider(): array
    {
        return [
            [100.00, 100.00, 100.00],
            [50.00, 75.00, 50.00],
            [0.01, 0.01, 0.01],
        ];
    }


}
