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
    private $amountCreditDataBuilder;

    protected function setUp(): void
    {
        parent::setUp();

        $this->dataBuilderServiceMock = $this->createMock(DataBuilderService::class);

        $this->refundGroupServiceMock = $this->createMock(RefundGroupTransactionService::class);

        $this->amountCreditDataBuilder = new AmountCreditDataBuilder(
            $this->dataBuilderServiceMock,
            $this->refundGroupServiceMock
        );
    }

    /**
     * @dataProvider buildDataProvider
     *
     * @param float $amount
     * @param float $amountLeftToRefund
     * @param float $expectedResult
     *
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

        $this->refundGroupServiceMock->method('getAmountLeftToRefund')->willReturn($amountLeftToRefund);

        $result = $this->amountCreditDataBuilder->build($buildSubject);

        $this->assertEquals($expectedResult, $result[AmountCreditDataBuilder::AMOUNT_CREDIT]);
    }

    /**
     * @return array
     */
    public static function buildDataProvider(): array
    {
        return [
            [100.00, 100.00, 100.00],
            [50.00, 75.00, 75.00],
            [0.01, 0.01, 0.01],
        ];
    }
}
