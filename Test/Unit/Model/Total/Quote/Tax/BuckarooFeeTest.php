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

namespace Buckaroo\Magento2\Test\Unit\Model\Total\Quote\Tax;

use Buckaroo\Magento2\Helper\PaymentGroupTransaction;
use Buckaroo\Magento2\Model\Total\Quote\Tax\BuckarooFee;
use Buckaroo\Magento2\Service\BuckarooFee\Calculate;
use Buckaroo\Magento2\Service\BuckarooFee\Result;
use Buckaroo\Magento2\Test\BaseTest;
use Magento\Framework\Pricing\PriceCurrencyInterface;
use Magento\Quote\Api\Data\ShippingAssignmentInterface;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Address\Total;
use PHPUnit\Framework\Attributes\DataProvider;

class BuckarooFeeTest extends BaseTest
{
    /**
     * @param float $amount
     * @return Result
     */
    private function createFeeResult(float $amount): Result
    {
        $result = $this->getFakeMock(Result::class)->onlyMethods([])->getMock();
        $result->setAmount($amount);

        return $result;
    }

    /**
     * @param Result|null $feeResult
     * @param PriceCurrencyInterface $priceCurrency
     * @param ShippingAssignmentInterface $shippingAssignment
     * @return array{0: BuckarooFee, 1: Quote, 2: Total}
     */
    private function buildCollector($feeResult, $priceCurrency, $shippingAssignment): array
    {
        $quote = $this->getFakeMock(Quote::class)->onlyMethods(['getReservedOrderId', 'getStore'])->getMock();
        $quote->method('getReservedOrderId')->willReturn('100000001');

        $total = $this->getFakeMock(Total::class)->onlyMethods([])->getMock();

        $groupTransaction = $this->getFakeMock(PaymentGroupTransaction::class)
            ->onlyMethods(['getAlreadyPaid'])
            ->getMock();
        $groupTransaction->method('getAlreadyPaid')->willReturn(0);

        $calculate = $this->getFakeMock(Calculate::class)->onlyMethods(['calculatePaymentFee'])->getMock();
        $calculate->expects($this->once())
            ->method('calculatePaymentFee')
            ->with($quote, $total)
            ->willReturn($feeResult);

        // The parent CommonTaxCollector needs Magento-generated tax factories, so skip its constructor
        // and inject only the dependencies collect() uses before parent::collect().
        $instance = $this->getFakeMock(BuckarooFee::class)->onlyMethods([])->getMock();
        $this->setProperty('priceCurrency', $priceCurrency, $instance);
        $this->setProperty('groupTransaction', $groupTransaction, $instance);
        $this->setProperty('calculate', $calculate, $instance);

        return [$instance, $quote, $total];
    }

    /**
     * @return array
     */
    public static function nonChargeableFeeProvider(): array
    {
        return [
            'calculate returns null'    => [null],
            'fee amount is zero'        => [0.0],
            'fee amount below one cent' => [0.009],
            'negative fixed fee'        => [-5.0],
        ];
    }

    /**
     * Sub-0.01 fees (incl. a misconfigured negative fee) are skipped, matching the main fee total collector.
     *
     * @param float|null $amount
     */
    #[DataProvider('nonChargeableFeeProvider')]
    public function testCollectSkipsFeeTaxBelowOneCent(?float $amount): void
    {
        $priceCurrency = $this->getFakeMock(PriceCurrencyInterface::class, true);
        $priceCurrency->expects($this->never())->method('convert');

        $shippingAssignment = $this->getFakeMock(ShippingAssignmentInterface::class, true);
        $shippingAssignment->method('getItems')->willReturn([new \stdClass()]);
        $shippingAssignment->expects($this->never())->method('getShipping');

        $feeResult = $amount === null ? null : $this->createFeeResult($amount);
        [$instance, $quote, $total] = $this->buildCollector($feeResult, $priceCurrency, $shippingAssignment);

        $this->assertSame($instance, $instance->collect($quote, $shippingAssignment, $total));
    }

    public function testCollectAddsFeeTaxableForPositiveFee(): void
    {
        // Reaching the conversion proves the fee is fed to addAssociatedTaxable; stop there to avoid
        // running the full parent tax collection.
        $priceCurrency = $this->getFakeMock(PriceCurrencyInterface::class, true);
        $priceCurrency->expects($this->once())
            ->method('convert')
            ->with(1.5)
            ->willThrowException(new \LogicException('fee taxable reached'));

        $shippingAssignment = $this->getFakeMock(ShippingAssignmentInterface::class, true);
        $shippingAssignment->method('getItems')->willReturn([new \stdClass()]);

        [$instance, $quote, $total] = $this->buildCollector(
            $this->createFeeResult(1.5),
            $priceCurrency,
            $shippingAssignment
        );

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('fee taxable reached');
        $instance->collect($quote, $shippingAssignment, $total);
    }
}
