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
use Magento\Quote\Model\Quote\Address as QuoteAddress;
use Magento\Quote\Model\Quote\Address\Total;
use Magento\Quote\Api\Data\ShippingInterface;
use Magento\Store\Model\Store;
use Buckaroo\Magento2\Model\ConfigProvider\BuckarooFee as BuckarooFeeConfigProvider;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
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
     * @return array{0: BuckarooFee, 1: Quote, 2: Total}
     */
    private function buildCollector($feeResult, $priceCurrency): array
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
        [$instance, $quote, $total] = $this->buildCollector($feeResult, $priceCurrency);

        $this->assertSame($instance, $instance->collect($quote, $shippingAssignment, $total));
    }

    /**
     * A chargeable fee is added to the address as a taxable item in the quote store's currency,
     * after any taxables already present.
     */
    public function testCollectAddsFeeTaxableForPositiveFee(): void
    {
        $store = $this->getFakeMock(Store::class)->getMock();

        $priceCurrency = $this->getFakeMock(PriceCurrencyInterface::class, true);
        $priceCurrency->expects($this->once())
            ->method('convert')
            ->with(1.5, $store)
            ->willReturn(3.0);

        $existingTaxable = ['type' => 'other', 'code' => 'other_code'];
        // Address whose magic get/set calls read and write a plain array
        $addressData = ['associated_taxables' => [$existingTaxable]];
        $address = $this->getFakeMock(QuoteAddress::class)->onlyMethods(['getData', 'setData'])->getMock();
        $address->method('getData')->willReturnCallback(function ($key = '') use (&$addressData) {
            return $addressData[$key] ?? null;
        });
        $address->method('setData')->willReturnCallback(function ($key, $value = null) use (&$addressData, $address) {
            $addressData[$key] = $value;
            return $address;
        });

        $shipping = $this->getFakeMock(ShippingInterface::class, true);
        $shipping->method('getAddress')->willReturn($address);

        $shippingAssignment = $this->getFakeMock(ShippingAssignmentInterface::class, true);
        $shippingAssignment->method('getItems')->willReturn([new \stdClass()]);
        $shippingAssignment->method('getShipping')->willReturn($shipping);

        // 1.499 is charged as its rounded amount 1.50
        [$instance, $quote, $total] = $this->buildCollector($this->createFeeResult(1.499), $priceCurrency);
        $quote->method('getStore')->willReturn($store);

        $feeConfig = $this->getFakeMock(BuckarooFeeConfigProvider::class)
            ->onlyMethods(['getBuckarooFeeTaxClass'])
            ->getMock();
        $feeConfig->expects($this->once())->method('getBuckarooFeeTaxClass')->with($store)->willReturn('7');
        $this->setProperty('configProviderBuckarooFee', $feeConfig, $instance);

        $this->assertSame($instance, $instance->collect($quote, $shippingAssignment, $total));

        $this->assertSame(
            [
                $existingTaxable,
                [
                    'type'                 => 'buckaroo_fee_tax',
                    'code'                 => 'buckaroo_fee_tax',
                    'unit_price'           => 3.0,
                    'base_unit_price'      => 1.5,
                    'quantity'             => 1,
                    'tax_class_id'         => '7',
                    'price_includes_tax'   => false,
                    'associated_item_code' => 'quote',
                ],
            ],
            $addressData['associated_taxables']
        );
    }
}
