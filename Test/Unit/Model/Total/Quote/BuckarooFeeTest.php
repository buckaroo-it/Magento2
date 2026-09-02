<?php
// phpcs:ignoreFile
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
namespace Buckaroo\Magento2\Test\Unit\Model\Total\Quote;

use PHPUnit\Framework\Attributes\DataProvider;
use Magento\Framework\Pricing\PriceCurrencyInterface;
use Magento\Quote\Api\Data\ShippingAssignmentInterface;
use Magento\Quote\Api\Data\ShippingInterface;
use Magento\Quote\Model\Quote;
use Buckaroo\Magento2\Helper\PaymentGroupTransaction;
use Buckaroo\Magento2\Model\Total\Quote\BuckarooFee;
use Buckaroo\Magento2\Service\BuckarooFee\Calculate;
use Buckaroo\Magento2\Test\Unit\Stubs\QuoteStub;
use Buckaroo\Magento2\Test\Unit\Stubs\StdObjectStub;
use Buckaroo\Magento2\Test\Unit\Stubs\TotalStub;

/**
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class BuckarooFeeTest extends \Buckaroo\Magento2\Test\BaseTest
{
    protected $instanceClass = BuckarooFee::class;

    /**
     * Build a shipping assignment mock. Its shipping address is needed by
     * AbstractTotal::collect(), which runs before any Buckaroo logic.
     *
     * @param bool $hasItems
     *
     * @return \PHPUnit\Framework\MockObject\MockObject
     */
    private function getShippingAssignmentMock($hasItems = true)
    {
        $addressMock = $this->getFakeMock(\Magento\Quote\Model\Quote\Address::class, true);

        $shippingMock = $this->getFakeMock(ShippingInterface::class, true);
        $shippingMock->method('getAddress')->willReturn($addressMock);

        $shippingAssignmentMock = $this->getFakeMock(ShippingAssignmentInterface::class, true);
        $shippingAssignmentMock->method('getItems')->willReturn($hasItems ? [new \stdClass()] : []);
        $shippingAssignmentMock->method('getShipping')->willReturn($shippingMock);

        return $shippingAssignmentMock;
    }

    /**
     * Build a Calculate service mock that must never be consulted.
     *
     * @return \PHPUnit\Framework\MockObject\MockObject
     */
    private function getNeverCalledCalculateMock()
    {
        $calculateMock = $this->getFakeMock(Calculate::class)
            ->onlyMethods(['calculatePaymentFee'])
            ->getMock();
        $calculateMock->expects($this->never())->method('calculatePaymentFee');

        return $calculateMock;
    }

    public function testCollectReturnsSelfWithoutTouchingTotalsWhenThereAreNoShippingItems()
    {
        $quoteMock = $this->getFakeMock(QuoteStub::class)
            ->onlyMethods(['setBuckarooFee', 'setBaseBuckarooFee'])
            ->getMock();
        $quoteMock->expects($this->never())->method('setBuckarooFee');
        $quoteMock->expects($this->never())->method('setBaseBuckarooFee');

        $totalMock = $this->getFakeMock(TotalStub::class)
            ->onlyMethods(['setBuckarooFee', 'setBaseBuckarooFee', 'setGrandTotal', 'setBaseGrandTotal'])
            ->getMock();
        $totalMock->expects($this->never())->method('setBuckarooFee');
        $totalMock->expects($this->never())->method('setBaseBuckarooFee');
        $totalMock->expects($this->never())->method('setGrandTotal');
        $totalMock->expects($this->never())->method('setBaseGrandTotal');

        $instance = $this->getInstance(['calculate' => $this->getNeverCalledCalculateMock()]);
        $result = $instance->collect($quoteMock, $this->getShippingAssignmentMock(false), $totalMock);

        $this->assertSame($instance, $result);
    }

    public function testCollectOnlyResetsFeeTotalsWhenOrderIsAlreadyPartiallyPaid()
    {
        $reservedOrderId = '100000123';

        $quoteMock = $this->getFakeMock(QuoteStub::class)
            ->onlyMethods(['getReservedOrderId', 'setBuckarooFee', 'setBaseBuckarooFee'])
            ->getMock();
        $quoteMock->method('getReservedOrderId')->willReturn($reservedOrderId);
        $quoteMock->expects($this->never())->method('setBuckarooFee');
        $quoteMock->expects($this->never())->method('setBaseBuckarooFee');

        $totalMock = $this->getFakeMock(TotalStub::class)
            ->onlyMethods(['setBuckarooFee', 'setBaseBuckarooFee', 'setGrandTotal', 'setBaseGrandTotal'])
            ->getMock();
        $totalMock->expects($this->once())->method('setBuckarooFee')->with(0)->willReturnSelf();
        $totalMock->expects($this->once())->method('setBaseBuckarooFee')->with(0)->willReturnSelf();
        $totalMock->expects($this->never())->method('setGrandTotal');
        $totalMock->expects($this->never())->method('setBaseGrandTotal');

        $groupTransactionMock = $this->getFakeMock(PaymentGroupTransaction::class)
            ->onlyMethods(['getAlreadyPaid'])
            ->getMock();
        $groupTransactionMock->expects($this->once())
            ->method('getAlreadyPaid')
            ->with($reservedOrderId)
            ->willReturn(10.0);

        $instance = $this->getInstance([
            'groupTransaction' => $groupTransactionMock,
            'calculate' => $this->getNeverCalledCalculateMock(),
        ]);
        $result = $instance->collect($quoteMock, $this->getShippingAssignmentMock(), $totalMock);

        $this->assertSame($instance, $result);
    }

    /**
     * @return array
     */
    public static function nonChargeableFeeResultProvider()
    {
        return [
            'calculate returns null' => [null],
            'fee amount is zero' => [0.0],
            'fee amount below one cent' => [0.009],
        ];
    }

    /**
     * @param float|null $amount Calculated fee amount, null when Calculate returns no result at all.
     */
    #[DataProvider('nonChargeableFeeResultProvider')]
    public function testCollectDoesNotChargeFeeWhenCalculateReturnsNoUsableFee($amount)
    {
        $feeResult = null;
        if ($amount !== null) {
            $feeResult = $this->getMockBuilder(StdObjectStub::class)->getMock();
            $feeResult->method('getAmount')->willReturn($amount);
            $feeResult->method('getRoundedAmount')->willReturn($amount);
        }

        $quoteMock = $this->getFakeMock(QuoteStub::class)
            ->onlyMethods(['setBuckarooFee', 'setBaseBuckarooFee'])
            ->getMock();
        $quoteMock->expects($this->never())->method('setBuckarooFee');
        $quoteMock->expects($this->never())->method('setBaseBuckarooFee');

        $totalMock = $this->getFakeMock(TotalStub::class)
            ->onlyMethods(['setBuckarooFee', 'setBaseBuckarooFee', 'setGrandTotal', 'setBaseGrandTotal'])
            ->getMock();
        $totalMock->expects($this->once())->method('setBuckarooFee')->with(0)->willReturnSelf();
        $totalMock->expects($this->once())->method('setBaseBuckarooFee')->with(0)->willReturnSelf();
        $totalMock->expects($this->never())->method('setGrandTotal');
        $totalMock->expects($this->never())->method('setBaseGrandTotal');

        $calculateMock = $this->getFakeMock(Calculate::class)
            ->onlyMethods(['calculatePaymentFee'])
            ->getMock();
        $calculateMock->expects($this->once())
            ->method('calculatePaymentFee')
            ->with($quoteMock, $totalMock)
            ->willReturn($feeResult);

        $priceCurrencyMock = $this->getFakeMock(PriceCurrencyInterface::class, true);
        $priceCurrencyMock->expects($this->never())->method('convert');

        $instance = $this->getInstance([
            'priceCurrency' => $priceCurrencyMock,
            'calculate' => $calculateMock,
        ]);
        $result = $instance->collect($quoteMock, $this->getShippingAssignmentMock(), $totalMock);

        $this->assertSame($instance, $result);
    }

    /**
     * @return array
     */
    public static function collectFeeDataProvider()
    {
        return [
            'fee converted to quote currency' => [
                4.50,   // rounded base fee returned by the Calculate service
                5.40,   // fee after base->quote currency conversion
                45.00,  // grand total before the fee
                45.00,  // base grand total before the fee
            ],
            'fee with 1:1 conversion rate' => [
                4.05,
                4.05,
                100.00,
                90.00,
            ],
        ];
    }

    /**
     * @param float $roundedBaseFee
     * @param float $convertedFee
     * @param float $initialGrandTotal
     * @param float $initialBaseGrandTotal
     */
    #[DataProvider('collectFeeDataProvider')]
    public function testCollectWritesConvertedFeeAndRaisesGrandTotals(
        $roundedBaseFee,
        $convertedFee,
        $initialGrandTotal,
        $initialBaseGrandTotal
    ) {
        $expectedGrandTotal = $initialGrandTotal + $convertedFee;
        $expectedBaseGrandTotal = $initialBaseGrandTotal + $roundedBaseFee;

        $quoteMock = $this->getFakeMock(QuoteStub::class)
            ->onlyMethods(['setBuckarooFee', 'setBaseBuckarooFee'])
            ->getMock();
        $quoteMock->expects($this->once())->method('setBuckarooFee')->with($convertedFee)->willReturnSelf();
        $quoteMock->expects($this->once())->method('setBaseBuckarooFee')->with($roundedBaseFee)->willReturnSelf();

        $totalMock = $this->getFakeMock(TotalStub::class)
            ->onlyMethods([
                'setBuckarooFee',
                'setBaseBuckarooFee',
                'getGrandTotal',
                'getBaseGrandTotal',
                'setGrandTotal',
                'setBaseGrandTotal',
            ])
            ->getMock();
        $totalMock->method('getGrandTotal')->willReturn($initialGrandTotal);
        $totalMock->method('getBaseGrandTotal')->willReturn($initialBaseGrandTotal);

        $feeCalls = [];
        $totalMock->expects($this->exactly(2))
            ->method('setBuckarooFee')
            ->willReturnCallback(function ($value) use (&$feeCalls, $totalMock) {
                $feeCalls[] = $value;
                return $totalMock;
            });

        $baseFeeCalls = [];
        $totalMock->expects($this->exactly(2))
            ->method('setBaseBuckarooFee')
            ->willReturnCallback(function ($value) use (&$baseFeeCalls, $totalMock) {
                $baseFeeCalls[] = $value;
                return $totalMock;
            });

        $totalMock->expects($this->once())->method('setGrandTotal')->with($expectedGrandTotal)->willReturnSelf();
        $totalMock->expects($this->once())->method('setBaseGrandTotal')->with($expectedBaseGrandTotal)->willReturnSelf();

        $feeResult = $this->getMockBuilder(StdObjectStub::class)->getMock();
        $feeResult->method('getAmount')->willReturn($roundedBaseFee);
        $feeResult->method('getRoundedAmount')->willReturn($roundedBaseFee);

        $calculateMock = $this->getFakeMock(Calculate::class)
            ->onlyMethods(['calculatePaymentFee'])
            ->getMock();
        $calculateMock->expects($this->once())
            ->method('calculatePaymentFee')
            ->with($quoteMock, $totalMock)
            ->willReturn($feeResult);

        $priceCurrencyMock = $this->getFakeMock(PriceCurrencyInterface::class, true);
        $priceCurrencyMock->expects($this->once())
            ->method('convert')
            ->with($roundedBaseFee)
            ->willReturn($convertedFee);

        $groupTransactionMock = $this->getFakeMock(PaymentGroupTransaction::class)
            ->onlyMethods(['getAlreadyPaid'])
            ->getMock();
        $groupTransactionMock->method('getAlreadyPaid')->willReturn(0);

        $instance = $this->getInstance([
            'priceCurrency' => $priceCurrencyMock,
            'groupTransaction' => $groupTransactionMock,
            'calculate' => $calculateMock,
        ]);
        $result = $instance->collect($quoteMock, $this->getShippingAssignmentMock(), $totalMock);

        $this->assertSame($instance, $result);
        $this->assertSame(
            [0, $convertedFee],
            $feeCalls,
            'Total buckaroo_fee must be reset to 0 and then set to the converted fee amount'
        );
        $this->assertSame(
            [0, $roundedBaseFee],
            $baseFeeCalls,
            'Total base_buckaroo_fee must be reset to 0 and then set to the rounded base fee amount'
        );
    }

    public function testGetLabelReturnsLabel()
    {
        $instance = $this->getInstance();
        $this->assertEquals('Payment Fee', $instance->getLabel());
    }

    public function testFetchShouldReturnCorrectTotalsData()
    {
        $expectedCode = 'buckaroo_fee';
        $expectedLabel = 'Payment Fee';
        $expectedBuckarooFee = 1.1;
        $expectedBaseBuckarooFee = 1.1;
        $expectedBuckarooFeeInclTax = 1.2;
        $expectedBaseBuckarooFeeInclTax = 1.2;
        $expectedBuckarooFeeTaxAmount = 0.1;
        $expectedBaseBuckarooFeeTaxAmount = 0.1;

        $expected = [
            'code' => $expectedCode,
            'title' => $expectedLabel,
            'buckaroo_fee' => $expectedBuckarooFee,
            'base_buckaroo_fee' => $expectedBaseBuckarooFee,
            'buckaroo_fee_incl_tax' => $expectedBuckarooFeeInclTax,
            'base_buckaroo_fee_incl_tax' => $expectedBaseBuckarooFeeInclTax,
            'buckaroo_fee_tax_amount' => $expectedBuckarooFeeTaxAmount,
            'buckaroo_fee_base_tax_amount' => $expectedBaseBuckarooFeeTaxAmount,
        ];

        $quoteMock = $this->getFakeMock(Quote::class, false)->getMock();

        $totalMock = $this->getFakeMock(TotalStub::class)
            ->onlyMethods([
                'getBuckarooFee',
                'getBaseBuckarooFee',
                'getBuckarooFeeInclTax',
                'getBaseBuckarooFeeInclTax',
                'getBuckarooFeeTaxAmount',
                'getBuckarooFeeBaseTaxAmount',
            ])
            ->getMock();
        $totalMock->method('getBuckarooFee')->willReturn($expectedBuckarooFee);
        $totalMock->method('getBaseBuckarooFee')->willReturn($expectedBaseBuckarooFee);
        $totalMock->method('getBuckarooFeeInclTax')->willReturn($expectedBuckarooFeeInclTax);
        $totalMock->method('getBaseBuckarooFeeInclTax')->willReturn($expectedBaseBuckarooFeeInclTax);
        $totalMock->method('getBuckarooFeeTaxAmount')->willReturn($expectedBuckarooFeeTaxAmount);
        $totalMock->method('getBuckarooFeeBaseTaxAmount')->willReturn($expectedBaseBuckarooFeeTaxAmount);

        $instance = $this->getInstance();
        $result = $instance->fetch($quoteMock, $totalMock);

        $this->assertEquals($expected, $result);
    }
}
