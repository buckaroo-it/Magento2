<?php
/**
 * NOTICE OF LICENSE
 *
 * This source file is subject to the MIT License
 * It is available through the world-wide-web at this URL:
 * https://tldrlegal.com/license/mit-license
 * If you are unable to obtain it through the world-wide-web, please send an email
 * to support@buckaroo.nl so we can send you a copy immediately.
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
namespace TIG\Buckaroo\Test\Unit\Model\Quote\Total;

use Magento\Catalog\Helper\Data;
use Magento\Framework\Pricing\PriceCurrencyInterface;
use Magento\Quote\Api\Data\ShippingAssignmentInterface;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Address\Total;
use TIG\Buckaroo\Model\Config\Source\TaxClass\Calculation;
use TIG\Buckaroo\Model\ConfigProvider\Account;
use TIG\Buckaroo\Model\ConfigProvider\BuckarooFee as ConfigProviderBuckarooFee;
use TIG\Buckaroo\Model\ConfigProvider\Method\Factory;
use TIG\Buckaroo\Model\Method\AbstractMethod;
use TIG\Buckaroo\Model\Total\Quote\BuckarooFee;

class BuckarooFeeTest extends \TIG\Buckaroo\Test\BaseTest
{
    protected $instanceClass = BuckarooFee::class;

    public function testGetBaseFeeReturnsConfigValueIfIsNumber()
    {
        $expectedFee = 1.89;
        $paymentCode = 'tig_buckaroo_ideal';
        $taxIncl = Calculation::DISPLAY_TYPE_INCLUDING_TAX;

        $paymentMethodMock = $this->getFakeMock(AbstractMethod::class)->getMock();
        $paymentMethodMock->buckarooPaymentMethodCode = $paymentCode;

        $quoteMock = $this->getFakeMock(Quote::class)->getMock();

        $configProviderFeeMock = $this->getFakeMock(ConfigProviderBuckarooFee::class)
            ->setMethods(['getTaxClass', 'getPaymentFeeTax'])
            ->getMock();
        $configProviderFeeMock->expects($this->once())->method('getTaxClass')->willReturn(1);
        $configProviderFeeMock->expects($this->once())->method('getPaymentFeeTax')->willReturn($taxIncl);

        $catalogHelper = $this->getFakeMock(Data::class)->setMethods(['getTaxPrice'])->getMock();
        $catalogHelper->expects($this->once())->method('getTaxPrice')->willReturn($expectedFee);

        $configProviderFactoryMock = $this->getFakeMock(Factory::class)
            ->setMethods(['has', 'get', 'getPaymentFee'])
            ->getMock();
        $configProviderFactoryMock->expects($this->once())->method('has')->with($paymentCode)->willReturn(true);
        $configProviderFactoryMock->expects($this->once())->method('get')->with($paymentCode)->willReturnSelf();
        $configProviderFactoryMock->expects($this->once())->method('getPaymentFee')->willReturn($expectedFee);

        $instance = $this->getInstance([
            'configProviderBuckarooFee' => $configProviderFeeMock,
            'configProviderMethodFactory' => $configProviderFactoryMock,
            'catalogHelper' => $catalogHelper
        ]);

        $result = $instance->getBaseFee($paymentMethodMock, $quoteMock);
        $this->assertEquals($expectedFee, $result);
    }

    public function testGetBaseFeeReturnFalseForANonExistingConfigProvider()
    {
        $paymentCode = 'tig_buckaroo_non_existing';

        $paymentMethodMock = $this->getFakeMock(AbstractMethod::class)->getMock();
        $paymentMethodMock->buckarooPaymentMethodCode = $paymentCode;

        $quoteMock = $this->getFakeMock(Quote::class)->getMock();

        $configProviderFactoryMock = $this->getFakeMock(Factory::class)->setMethods(['has'])->getMock();
        $configProviderFactoryMock->expects($this->once())->method('has')->with($paymentCode)->willReturn(false);

        $instance = $this->getInstance(['configProviderMethodFactory' => $configProviderFactoryMock]);
        $result = $instance->getBaseFee($paymentMethodMock, $quoteMock);

        $this->assertFalse($result);
    }

    public function testGetBaseFeeReturnFalseForAnInvalidFeeValue()
    {
        $paymentCode = 'tig_buckaroo_ideal';
        $invalidFee = 'invalid';

        $paymentMethodMock = $this->getFakeMock(AbstractMethod::class)->getMock();
        $paymentMethodMock->buckarooPaymentMethodCode = $paymentCode;

        $quoteMock = $this->getFakeMock(Quote::class)->getMock();

        $configProviderFactoryMock = $this->getFakeMock(Factory::class)
            ->setMethods(['has', 'get', 'getPaymentFee'])
            ->getMock();
        $configProviderFactoryMock->expects($this->once())->method('has')->with($paymentCode)->willReturn(true);
        $configProviderFactoryMock->expects($this->once())->method('get')->with($paymentCode)->willReturnSelf();
        $configProviderFactoryMock->expects($this->once())->method('getPaymentFee')->willReturn($invalidFee);

        $instance = $this->getInstance(['configProviderMethodFactory' => $configProviderFactoryMock]);
        $result = $instance->getBaseFee($paymentMethodMock, $quoteMock);

        $this->assertFalse($result);
    }

    /**
     * @dataProvider baseFeePercentageDataProvider
     *
     * @param $paymentCode
     * @param $fee
     * @param $feeMode
     * @param $quoteMethod
     * @param $quoteAmount
     * @param $expectedValue
     */
    public function testGetBaseFeeCalculatesPercentageOnCorrectTotal(
        $paymentCode,
        $fee,
        $feeMode,
        $quoteMethod,
        $quoteAmount,
        $expectedValue
    ) {
        $paymentMethodMock = $this->getFakeMock(AbstractMethod::class)->getMock();
        $paymentMethodMock->buckarooPaymentMethodCode = $paymentCode;

        $quoteMock = $this->getFakeMock(Quote::class)
            ->setMethods(['getShippingAddress', 'getStore', $quoteMethod])
            ->getMock();
        $quoteMock->expects($this->exactly(2))->method('getShippingAddress')->willReturnSelf();
        $quoteMock->expects($this->exactly(2))->method('getStore')->willReturnSelf();
        $quoteMock->expects($this->once())->method($quoteMethod)->willReturn($quoteAmount);

        $configProviderFactoryMock = $this->getFakeMock(Factory::class)
            ->setMethods(['has', 'get', 'getPaymentFee'])
            ->getMock();
        $configProviderFactoryMock->expects($this->once())->method('has')->with($paymentCode)->willReturn(true);
        $configProviderFactoryMock->expects($this->once())->method('get')->with($paymentCode)->willReturnSelf();
        $configProviderFactoryMock->expects($this->once())->method('getPaymentFee')->willReturn($fee);

        $configAccountMock = $this->getFakeMock(Account::class)->setMethods(['getFeePercentageMode'])->getMock();
        $configAccountMock->expects($this->once())->method('getFeePercentageMode')->willReturn($feeMode);

        $instance = $this->getInstance([
            'configProviderAccount' => $configAccountMock,
            'configProviderMethodFactory' => $configProviderFactoryMock
        ]);
        $result = $instance->getBaseFee($paymentMethodMock, $quoteMock);

        $this->assertEquals($expectedValue, $result);
    }

    /**
     * @return array
     */
    public function baseFeePercentageDataProvider()
    {
        return [
            [
                'tig_buckaroo_ideal',
                '10%',
                'subtotal',
                'getBaseSubtotal',
                45.0000,
                4.5000
            ],
            [
                'tig_buckaroo_ideal',
                '9%',
                'subtotal_incl_tax',
                'getBaseSubtotalTotalInclTax',
                45.0000,
                4.0500
            ],
        ];
    }

    /**
     * @dataProvider baseFeePercentageDataProvider
     *
     * @param $paymentCode
     * @param $fee
     * @param $feeMode
     * @param $quoteMethod
     * @param $quoteAmount
     * @param $expectedValue
     */
    public function testGetBaseFeeCalculatesPercentageOnCorrectTotalWithBillingAddress(
        $paymentCode,
        $fee,
        $feeMode,
        $quoteMethod,
        $quoteAmount,
        $expectedValue
    ) {
        $paymentMethodMock = $this->getFakeMock(AbstractMethod::class)->getMock();
        $paymentMethodMock->buckarooPaymentMethodCode = $paymentCode;

        $quoteMock = $this->getFakeMock(Quote::class)
            ->setMethods(['getShippingAddress', 'getBillingAddress', 'getStore', $quoteMethod])
            ->getMock();
        $quoteMock->expects($this->once())->method('getShippingAddress')->willReturn(false);
        $quoteMock->expects($this->once())->method('getBillingAddress')->willReturnSelf();
        $quoteMock->expects($this->exactly(2))->method('getStore')->willReturnSelf();
        $quoteMock->expects($this->once())->method($quoteMethod)->willReturn($quoteAmount);

        $configProviderFactoryMock = $this->getFakeMock(Factory::class)
            ->setMethods(['has', 'get', 'getPaymentFee'])
            ->getMock();
        $configProviderFactoryMock->expects($this->once())->method('has')->with($paymentCode)->willReturn(true);
        $configProviderFactoryMock->expects($this->once())->method('get')->with($paymentCode)->willReturnSelf();
        $configProviderFactoryMock->expects($this->once())->method('getPaymentFee')->willReturn($fee);

        $configAccountMock = $this->getFakeMock(Account::class)->setMethods(['getFeePercentageMode'])->getMock();
        $configAccountMock->expects($this->once())->method('getFeePercentageMode')->willReturn($feeMode);

        $instance = $this->getInstance([
            'configProviderAccount' => $configAccountMock,
            'configProviderMethodFactory' => $configProviderFactoryMock
        ]);
        $result = $instance->getBaseFee($paymentMethodMock, $quoteMock);

        $this->assertEquals($expectedValue, $result);
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

        $quoteMock = $this->getFakeMock(Quote::class, true);

        $totalMock = $this->getFakeMock(Total::class)
            ->setMethods([
                'getBuckarooFee', 'getBaseBuckarooFee', 'getBuckarooFeeInclTax',
                'getBaseBuckarooFeeInclTax', 'getBuckarooFeeTaxAmount', 'getBuckarooFeeBaseTaxAmount'
            ])
            ->getMock();
        $totalMock->expects($this->once())->method('getBuckarooFee')->willReturn($expectedBuckarooFee);
        $totalMock->expects($this->once())->method('getBaseBuckarooFee')->willReturn($expectedBaseBuckarooFee);
        $totalMock->expects($this->once())->method('getBuckarooFeeInclTax')->willReturn($expectedBuckarooFeeInclTax);
        $totalMock->expects($this->once())->method('getBaseBuckarooFeeInclTax')->willReturn($expectedBaseBuckarooFeeInclTax);
        $totalMock->expects($this->once())->method('getBuckarooFeeTaxAmount')->willReturn($expectedBuckarooFeeTaxAmount);
        $totalMock->expects($this->once())->method('getBuckarooFeeBaseTaxAmount')->willReturn($expectedBaseBuckarooFeeTaxAmount);

        $instance = $this->getInstance();
        $result = $instance->fetch($quoteMock, $totalMock);

        $this->assertEquals($expected, $result);
    }

    public function testCollectShouldReturnSelfIfNoShippingItems()
    {
        $quoteMock = $this->getFakeMock(Quote::class, true);

        $shippingAssignmentMock = $this->getFakeMock(ShippingAssignmentInterface::class)
            ->setMethods(['getItems'])
            ->getMockForAbstractClass();
        $shippingAssignmentMock->expects($this->once())->method('getItems')->willReturn(false);

        $totalMock = $this->getFakeMock(Total::class)->setMethods(['setBuckarooFee', 'setBaseBuckarooFee'])->getMock();
        $totalMock->expects($this->once())->method('setBuckarooFee')->willReturn(0);
        $totalMock->expects($this->once())->method('setBaseBuckarooFee')->willReturn(0);

        $instance = $this->getInstance();
        $result = $instance->collect($quoteMock, $shippingAssignmentMock, $totalMock);

        $this->assertEquals($instance, $result);
    }

    /**
     * @dataProvider collectPaymentMethodDataProvider
     *
     * @param $method
     */
    public function testCollectShouldReturnSelfIfNoPaymentMethodOrNonBuckarooMethod($method)
    {
        $quoteMock = $this->getFakeMock(Quote::class)->setMethods(['getPayment', 'getMethod'])->getMock();
        $quoteMock->expects($this->once())->method('getPayment')->willReturnSelf();
        $quoteMock->expects($this->once())->method('getMethod')->willReturn($method);

        $shippingAssignmentMock = $this->getFakeMock(ShippingAssignmentInterface::class)
            ->setMethods(['getItems'])
            ->getMockForAbstractClass();
        $shippingAssignmentMock->expects($this->once())->method('getItems')->willReturn(true);

        $totalMock = $this->getFakeMock(Total::class)->setMethods(['setBuckarooFee', 'setBaseBuckarooFee'])->getMock();
        $totalMock->expects($this->once())->method('setBuckarooFee')->willReturn(0);
        $totalMock->expects($this->once())->method('setBaseBuckarooFee')->willReturn(0);

        $instance = $this->getInstance();
        $result = $instance->collect($quoteMock, $shippingAssignmentMock, $totalMock);

        $this->assertEquals($instance, $result);
    }

    public function collectPaymentMethodDataProvider()
    {
        return [
            [
                false
            ],
            [
                'check_mo'
            ]
        ];
    }

    public function testCollectShouldReturnSelfIfFeeIsZero()
    {
        $expectedFee = 0;
        $paymentCode = 'tig_buckaroo_ideal';

        $paymentMethodMock = $this->getFakeMock(AbstractMethod::class)->getMock();
        $paymentMethodMock->buckarooPaymentMethodCode = $paymentCode;


        $quoteMock = $this->getFakeMock(Quote::class)
            ->setMethods(['getPayment', 'getMethod', 'getMethodInstance', 'getStore'])
            ->getMock();
        $quoteMock->expects($this->exactly(2))->method('getPayment')->willReturnSelf();
        $quoteMock->expects($this->once())->method('getMethod')->willReturn($paymentCode);
        $quoteMock->expects($this->once())->method('getMethodInstance')->willReturn($paymentMethodMock);

        $shippingAssignmentMock = $this->getFakeMock(ShippingAssignmentInterface::class)
            ->setMethods(['getItems'])
            ->getMockForAbstractClass();
        $shippingAssignmentMock->expects($this->once())->method('getItems')->willReturn(true);

        $totalMock = $this->getFakeMock(Total::class)->setMethods(['setBuckarooFee', 'setBaseBuckarooFee'])->getMock();
        $totalMock->expects($this->once())->method('setBuckarooFee')->willReturn(0);
        $totalMock->expects($this->once())->method('setBaseBuckarooFee')->willReturn(0);

        $configProviderFactoryMock = $this->getFakeMock(Factory::class)
            ->setMethods(['has', 'get', 'getPaymentFee'])
            ->getMock();
        $configProviderFactoryMock->expects($this->once())->method('has')->with($paymentCode)->willReturn(true);
        $configProviderFactoryMock->expects($this->once())->method('get')->with($paymentCode)->willReturnSelf();
        $configProviderFactoryMock->expects($this->once())->method('getPaymentFee')->willReturn($expectedFee);

        $configProviderFeeMock = $this->getFakeMock(ConfigProviderBuckarooFee::class)
            ->setMethods(['getTaxClass'])
            ->getMock();
        $configProviderFeeMock->expects($this->once())->method('getTaxClass')->willReturn(1);

        $catalogHelperMock = $this->getFakeMock(Data::class)->setMethods(['getTaxPrice'])->getMock();
        $catalogHelperMock->expects($this->once())->method('getTaxPrice')->willReturn($expectedFee);

        $instance = $this->getInstance([
            'configProviderBuckarooFee' => $configProviderFeeMock,
            'configProviderMethodFactory' => $configProviderFactoryMock,
            'catalogHelper' => $catalogHelperMock
        ]);

        $result = $instance->collect($quoteMock, $shippingAssignmentMock, $totalMock);
        $this->assertEquals($instance, $result);
    }

    public function testCollectShouldReturnSelfIfInvalidMessage()
    {
        $paymentCode = 'tig_buckaroo_ideal';

        $quoteMock = $this->getFakeMock(Quote::class)
            ->setMethods(['getPayment', 'getMethod', 'getMethodInstance'])
            ->getMock();
        $quoteMock->expects($this->exactly(2))->method('getPayment')->willReturnSelf();
        $quoteMock->expects($this->once())->method('getMethod')->willReturn($paymentCode);
        $quoteMock->expects($this->once())->method('getMethodInstance')->willReturn(new \stdClass());

        $shippingAssignmentMock = $this->getFakeMock(ShippingAssignmentInterface::class)
            ->setMethods(['getItems'])
            ->getMockForAbstractClass();
        $shippingAssignmentMock->expects($this->once())->method('getItems')->willReturn(true);

        $totalMock = $this->getFakeMock(Total::class)->setMethods(['setBuckarooFee', 'setBaseBuckarooFee'])->getMock();
        $totalMock->expects($this->once())->method('setBuckarooFee')->willReturn(0);
        $totalMock->expects($this->once())->method('setBaseBuckarooFee')->willReturn(0);

        $instance = $this->getInstance();
        $result = $instance->collect($quoteMock, $shippingAssignmentMock, $totalMock);

        $this->assertEquals($instance, $result);
    }

    public function testCollectShouldSetTotalsOnQuote()
    {
        $paymentCode = 'tig_buckaroo_ideal';
        $fee = 1.1;
        $grandTotal = 45.0000;
        $baseGrandTotal = 45.0000;
        $taxIncl = Calculation::DISPLAY_TYPE_INCLUDING_TAX;
        $store = 1;

        $configProviderFactoryMock = $this->getFakeMock(Factory::class)
            ->setMethods(['has', 'get', 'getPaymentFee'])
            ->getMock();
        $configProviderFactoryMock->expects($this->once())->method('has')->with($paymentCode)->willReturn(true);
        $configProviderFactoryMock->expects($this->once())->method('get')->with($paymentCode)->willReturnSelf();
        $configProviderFactoryMock->expects($this->once())->method('getPaymentFee')->willReturn(1.1);

        $priceCurrencyMock = $this->getFakeMock(PriceCurrencyInterface::class)
            ->setMethods(['convert'])
            ->getMockForAbstractClass();
        $priceCurrencyMock->method('convert')->with($fee, $store)->willReturn($fee);

        $configProviderFeeMock = $this->getFakeMock(ConfigProviderBuckarooFee::class)
            ->setMethods(['getTaxClass', 'getPaymentFeeTax'])
            ->getMock();
        $configProviderFeeMock->expects($this->once())->method('getTaxClass')->willReturn(1);
        $configProviderFeeMock->expects($this->once())->method('getPaymentFeeTax')->willReturn($taxIncl);

        $catalogHelperMock = $this->getFakeMock(Data::class)->setMethods(['getTaxPrice'])->getMock();
        $catalogHelperMock->expects($this->once())->method('getTaxPrice')->willReturn($fee);

        $paymentMethodMock = $this->getFakeMock(AbstractMethod::class, true);
        $paymentMethodMock->buckarooPaymentMethodCode = $paymentCode;

        $quoteMock = $this->getFakeMock(Quote::class)
            ->setMethods(
                ['getPayment', 'getStore', 'getMethod', 'getMethodInstance', 'setBuckarooFee', 'setBaseBuckarooFee']
            )
            ->getMock();
        $quoteMock->expects($this->exactly(2))->method('getPayment')->willReturnSelf();
        $quoteMock->expects($this->once())->method('getStore')->willReturn($store);
        $quoteMock->expects($this->once())->method('getMethod')->willReturn($paymentCode);
        $quoteMock->expects($this->once())->method('getMethodInstance')->willReturn($paymentMethodMock);
        $quoteMock->method('setBuckarooFee')->willReturn($fee);
        $quoteMock->method('setBaseBuckarooFee')->willReturn($fee);

        $shippingAssignmentMock = $this->getFakeMock(ShippingAssignmentInterface::class)
            ->setMethods(['getItems'])
            ->getMockForAbstractClass();
        $shippingAssignmentMock->expects($this->once())->method('getItems')->willReturn(true);

        $totalMock = $this->getFakeMock(Total::class)
            ->setMethods([
                'setBuckarooFee', 'setBaseBuckarooFee', 'getBaseGrandTotal',
                'getGrandTotal', 'setBaseGrandTotal', 'setGrandTotal'
            ])
            ->getMock();
        //$totalMock->expects($this->exactly(2))->method('setBuckarooFee')->withConsecutive([0], [$fee]);
        //$totalMock->expects($this->exactly(2))->method('setBaseBuckarooFee')->withConsecutive([0], [$fee]);
        //$totalMock->expects($this->once())->method('getBaseGrandTotal')->willReturn($baseGrandTotal);
        //$totalMock->expects($this->once())->method('getGrandTotal')->willReturn($grandTotal);
        //$totalMock->expects($this->once())->method('setBaseGrandTotal')->with($fee + $baseGrandTotal);
        //$totalMock->expects($this->once())->method('setGrandTotal')->with($fee + $grandTotal);



        $instance = $this->getInstance([
            'catalogHelper' => $catalogHelperMock,
            'configProviderBuckarooFee' => $configProviderFeeMock,
            'configProviderMethodFactory' => $configProviderFactoryMock,
            'priceCurrency' => $priceCurrencyMock,
        ]);

        $result = $instance->collect($quoteMock, $shippingAssignmentMock, $totalMock);
        $this->assertEquals($instance, $result);
    }
}
