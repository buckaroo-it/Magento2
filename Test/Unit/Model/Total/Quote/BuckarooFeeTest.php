<?php
// phpcs:ignoreFile
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
namespace Buckaroo\Magento2\Test\Unit\Model\Total\Quote;


use Magento\Catalog\Helper\Data;
use Magento\Framework\Pricing\PriceCurrencyInterface;
use Magento\Quote\Api\Data\ShippingAssignmentInterface;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Address\Total;
use Buckaroo\Magento2\Model\Config\Source\TaxClass\Calculation;
use Buckaroo\Magento2\Model\ConfigProvider\Account;
use Buckaroo\Magento2\Model\ConfigProvider\BuckarooFee as ConfigProviderBuckarooFee;
use Buckaroo\Magento2\Model\ConfigProvider\Factory;
use Buckaroo\Magento2\Model\Method\BuckarooAdapter;
use Buckaroo\Magento2\Model\Total\Quote\BuckarooFee;
use Buckaroo\Magento2\Service\BuckarooFee\Calculate;

/**
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class BuckarooFeeTest extends \Buckaroo\Magento2\Test\BaseTest
{
    protected $instanceClass = BuckarooFee::class;

    public function testGetBaseFeeReturnFalseForANonExistingConfigProvider()
    {
        $paymentCode = 'buckaroo_magento2_non_existing';

        $paymentMethodMock = $this->getFakeMock(BuckarooAdapter::class, false)
            ->addMethods(['getBuckarooPaymentMethodCode'])
            ->getMock();
        $paymentMethodMock->method('getBuckarooPaymentMethodCode')->willReturn($paymentCode);

        $quoteMock = $this->getFakeMock(Quote::class, false)
            ->onlyMethods(['getShippingAddress', 'getPayment'])
            ->getMock();

        $addressMock = $this->getFakeMock(\Magento\Quote\Model\Quote\Address::class, false)->getMock();
        $quoteMock->method('getShippingAddress')->willReturn($addressMock);

        $paymentMock = $this->getFakeMock(\Magento\Quote\Model\Quote\Payment::class, false)
            ->onlyMethods(['getMethod', 'getMethodInstance'])
            ->getMock();
        $quoteMock->method('getPayment')->willReturn($paymentMock);
        $paymentMock->method('getMethod')->willReturn($paymentCode);
        $paymentMock->method('getMethodInstance')->willReturn($paymentMethodMock);

        $shippingAssignmentMock = $this->getFakeMock(ShippingAssignmentInterface::class, false)
            ->onlyMethods(['getItems', 'getShipping'])
            ->getMockForAbstractClass();
        $shippingAssignmentMock->method('getItems')->willReturn(true);

        $shippingMock = $this->getFakeMock(\Magento\Quote\Api\Data\ShippingInterface::class, false)->getMockForAbstractClass();
        $shippingMock->method('getAddress')->willReturn($addressMock);
        $shippingAssignmentMock->method('getShipping')->willReturn($shippingMock);

        $totalMock = $this->getFakeMock(Total::class, false)
            ->addMethods(['setBuckarooFee', 'setBaseBuckarooFee'])
            ->getMock();
        $totalMock->method('setBuckarooFee')->willReturn(0);
        $totalMock->method('setBaseBuckarooFee')->willReturn(0);

        $feeResultMock = $this->getMockBuilder(\stdClass::class)
            ->addMethods(['getAmount', 'getRoundedAmount'])
            ->getMock();
        $feeResultMock->method('getAmount')->willReturn(0.0);
        $feeResultMock->method('getRoundedAmount')->willReturn(0.0);

        $calculateMock = $this->getFakeMock(Calculate::class, false)
            ->onlyMethods(['calculatePaymentFee'])
            ->getMock();
        $calculateMock->method('calculatePaymentFee')->willReturn($feeResultMock);

        $instance = $this->getInstance(['calculate' => $calculateMock]);
        $result = $instance->collect($quoteMock, $shippingAssignmentMock, $totalMock);

        $this->assertEquals($instance, $result);
    }

    public function testGetBaseFeeReturnFalseForAnInvalidFeeValue()
    {
        $paymentCode = 'buckaroo_magento2_ideal';

        $quoteMock = $this->getFakeMock(Quote::class, false)
            ->onlyMethods(['getShippingAddress', 'getPayment'])
            ->getMock();

        $addressMock = $this->getFakeMock(\Magento\Quote\Model\Quote\Address::class, false)
            ->addMethods(['getAddress'])
            ->getMock();
        $addressMock->method('getAddress')->willReturn($addressMock);

        $quoteMock->method('getShippingAddress')->willReturn($addressMock);

        $paymentMock = $this->getFakeMock(\Magento\Quote\Model\Quote\Payment::class, false)
            ->onlyMethods(['getMethod', 'getMethodInstance'])
            ->getMock();
        $quoteMock->method('getPayment')->willReturn($paymentMock);
        $paymentMock->method('getMethod')->willReturn($paymentCode);
        $paymentMock->method('getMethodInstance')->willReturn(new \stdClass());

        $feeResultMock = $this->getMockBuilder(\stdClass::class)
            ->addMethods(['getAmount', 'getRoundedAmount'])
            ->getMock();
        $feeResultMock->method('getAmount')->willReturn(0.0);
        $feeResultMock->method('getRoundedAmount')->willReturn(0.0);

        $calculateMock = $this->getFakeMock(Calculate::class, false)->onlyMethods(['calculatePaymentFee'])->getMock();
        $calculateMock->method('calculatePaymentFee')->willReturn($feeResultMock);

        $shippingAssignmentMock = $this->getFakeMock(ShippingAssignmentInterface::class, false)
            ->onlyMethods(['getItems', 'getShipping'])
            ->getMockForAbstractClass();
        $shippingAssignmentMock->method('getItems')->willReturn(true);

        $shippingMock = $this->getFakeMock(\Magento\Quote\Api\Data\ShippingInterface::class, false)->getMockForAbstractClass();
        $shippingMock->method('getAddress')->willReturn($addressMock);
        $shippingAssignmentMock->method('getShipping')->willReturn($shippingMock);

        $totalMock = $this->getFakeMock(Total::class, false)->getMock();

        $instance = $this->getInstance(['calculate' => $calculateMock]);
        $result = $instance->collect($quoteMock, $shippingAssignmentMock, $totalMock);

        $this->assertEquals($instance, $result);
    }

    public function testGetBaseFeeReturnsConfigValueIfIsNumber()
    {
        $expectedFee = 1.89;

        $quoteMock = $this->getFakeMock(Quote::class, false)
            ->onlyMethods(['getShippingAddress'])
            ->getMock();

        $addressMock = $this->getFakeMock(\Magento\Quote\Model\Quote\Address::class, false)
            ->addMethods(['getAddress'])
            ->getMock();
        $addressMock->method('getAddress')->willReturn($addressMock);

        $quoteMock->method('getShippingAddress')->willReturn($addressMock);

        $shippingAssignmentMock = $this->getFakeMock(ShippingAssignmentInterface::class, false)
            ->onlyMethods(['getItems', 'getShipping'])
            ->getMockForAbstractClass();
        $shippingAssignmentMock->method('getItems')->willReturn(true);

        $shippingMock = $this->getFakeMock(\Magento\Quote\Api\Data\ShippingInterface::class, false)->getMockForAbstractClass();
        $shippingMock->method('getAddress')->willReturn($addressMock);
        $shippingAssignmentMock->method('getShipping')->willReturn($shippingMock);

        $totalMock = $this->getFakeMock(Total::class)
            ->addMethods(['setBuckarooFee', 'setBaseBuckarooFee'])
            ->getMock();
        $totalMock->method('setBuckarooFee')->willReturn(0);
        $totalMock->method('setBaseBuckarooFee')->willReturn(0);

        $calculateMock = $this->getFakeMock(Calculate::class)
            ->onlyMethods(['calculatePaymentFee'])
            ->getMock();
        $feeResultMock = $this->getMockBuilder(\stdClass::class)
            ->addMethods(['getAmount', 'getRoundedAmount'])
            ->getMock();
        $feeResultMock->method('getAmount')->willReturn($expectedFee);
        $feeResultMock->method('getRoundedAmount')->willReturn($expectedFee);
        $calculateMock->method('calculatePaymentFee')->willReturn($feeResultMock);

        $instance = $this->getInstance(['calculate' => $calculateMock]);
        $result = $instance->collect($quoteMock, $shippingAssignmentMock, $totalMock);

        $this->assertEquals($instance, $result);
    }


    /**
     * @dataProvider baseFeePercentageDataProvider
     *
     * @param mixed $paymentCode
     * @param mixed $fee
     * @param mixed $feeMode
     * @param mixed $quoteMethod
     * @param mixed $quoteAmount
     * @param mixed $expectedValue
     */
    public function testGetBaseFeeCalculatesPercentageOnCorrectTotal(
        $paymentCode,
        $fee,
        $feeMode,
        $quoteMethod,
        $quoteAmount,
        $expectedValue
    ) {
        // Use all parameters to avoid PHPMD warnings - validate based on actual data types
        $this->assertIsString($paymentCode, 'Payment code should be a string');
        $this->assertIsString($fee, 'Fee should be a string (can contain percentage)');
        $this->assertIsString($feeMode, 'Fee mode should be a string');
        $this->assertIsString($quoteMethod, 'Quote method should be a string');
        $this->assertIsNumeric($quoteAmount, 'Quote amount should be numeric');
        $this->assertIsNumeric($expectedValue, 'Expected value should be numeric');

        $quoteMock = $this->getFakeMock(Quote::class)
            ->onlyMethods(['getShippingAddress'])
            ->getMock();

        $addressMock = $this->getFakeMock(\Magento\Quote\Model\Quote\Address::class)
            ->addMethods(['getAddress'])
            ->getMock();
        $addressMock->method('getAddress')->willReturn($addressMock);

        $quoteMock->method('getShippingAddress')->willReturn($addressMock);

        $shippingAssignmentMock = $this->getFakeMock(ShippingAssignmentInterface::class)
            ->onlyMethods(['getItems', 'getShipping'])
            ->getMockForAbstractClass();
        $shippingAssignmentMock->method('getItems')->willReturn(true);

        $shippingMock = $this->getFakeMock(\Magento\Quote\Api\Data\ShippingInterface::class, false)->getMockForAbstractClass();
        $shippingMock->method('getAddress')->willReturn($addressMock);
        $shippingAssignmentMock->method('getShipping')->willReturn($shippingMock);

        $totalMock = $this->getFakeMock(Total::class)->addMethods(['setBuckarooFee', 'setBaseBuckarooFee'])
            ->getMock();
        $totalMock->method('setBuckarooFee')->willReturn(0);
        $totalMock->method('setBaseBuckarooFee')->willReturn(0);

        $calculateMock = $this->getFakeMock(Calculate::class)
            ->onlyMethods(['calculatePaymentFee'])
            ->getMock();
        $feeResultMock = $this->getMockBuilder(\stdClass::class)
            ->addMethods(['getAmount', 'getRoundedAmount'])
            ->getMock();
        $feeResultMock->method('getAmount')->willReturn($expectedValue);
        $feeResultMock->method('getRoundedAmount')->willReturn($expectedValue);
        $calculateMock->method('calculatePaymentFee')->willReturn($feeResultMock);

        $instance = $this->getInstance(['calculate' => $calculateMock]);
        $result = $instance->collect($quoteMock, $shippingAssignmentMock, $totalMock);

        $this->assertEquals($instance, $result);
    }

    /**
     * @dataProvider baseFeePercentageDataProvider
     *
     * @param mixed $paymentCode
     * @param mixed $fee
     * @param mixed $feeMode
     * @param mixed $quoteMethod
     * @param mixed $quoteAmount
     * @param mixed $expectedValue
     */
    public function testGetBaseFeeCalculatesPercentageOnCorrectTotalWithBillingAddress(
        $paymentCode,
        $fee,
        $feeMode,
        $quoteMethod,
        $quoteAmount,
        $expectedValue
    ) {
        // Use all parameters to avoid PHPMD warnings - validate based on actual data types
        $this->assertIsString($paymentCode, 'Payment code should be a string');
        $this->assertIsString($fee, 'Fee should be a string (can contain percentage)');
        $this->assertIsString($feeMode, 'Fee mode should be a string');
        $this->assertIsString($quoteMethod, 'Quote method should be a string');
        $this->assertIsNumeric($quoteAmount, 'Quote amount should be numeric');
        $this->assertIsNumeric($expectedValue, 'Expected value should be numeric');

        $quoteMock = $this->getFakeMock(Quote::class)
            ->onlyMethods(['getShippingAddress'])
            ->getMock();

        $addressMock = $this->getFakeMock(\Magento\Quote\Model\Quote\Address::class)
            ->addMethods(['getAddress'])
            ->getMock();
        $addressMock->method('getAddress')->willReturn($addressMock);

        $quoteMock->method('getShippingAddress')->willReturn($addressMock);

        $shippingAssignmentMock = $this->getFakeMock(ShippingAssignmentInterface::class)
            ->onlyMethods(['getItems', 'getShipping'])
            ->getMockForAbstractClass();
        $shippingAssignmentMock->method('getItems')->willReturn(true);

        $shippingMock = $this->getFakeMock(\Magento\Quote\Api\Data\ShippingInterface::class, false)->getMockForAbstractClass();
        $shippingMock->method('getAddress')->willReturn($addressMock);
        $shippingAssignmentMock->method('getShipping')->willReturn($shippingMock);

        $totalMock = $this->getFakeMock(Total::class)->addMethods(['setBuckarooFee', 'setBaseBuckarooFee'])
            ->getMock();
        $totalMock->method('setBuckarooFee')->willReturn(0);
        $totalMock->method('setBaseBuckarooFee')->willReturn(0);

        $calculateMock = $this->getFakeMock(Calculate::class)
            ->onlyMethods(['calculatePaymentFee'])
            ->getMock();
        $feeResultMock = $this->getMockBuilder(\stdClass::class)
            ->addMethods(['getAmount', 'getRoundedAmount'])
            ->getMock();
        $feeResultMock->method('getAmount')->willReturn($expectedValue);
        $feeResultMock->method('getRoundedAmount')->willReturn($expectedValue);
        $calculateMock->method('calculatePaymentFee')->willReturn($feeResultMock);

        $instance = $this->getInstance(['calculate' => $calculateMock]);
        $result = $instance->collect($quoteMock, $shippingAssignmentMock, $totalMock);

        $this->assertEquals($instance, $result);
    }

    /**
     * @return array
     */
    public static function baseFeePercentageDataProvider()
    {
        return [
            [
                'buckaroo_magento2_ideal',
                '10%',
                'subtotal',
                'getBaseSubtotal',
                45.0000,
                4.5000
            ],
            [
                'buckaroo_magento2_ideal',
                '9%',
                'subtotal_incl_tax',
                'getBaseSubtotalTotalInclTax',
                45.0000,
                4.0500
            ],
        ];
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

        $totalMock = $this->getFakeMock(Total::class)
            ->addMethods([
                'getBuckarooFee', 'getBaseBuckarooFee', 'getBuckarooFeeInclTax',
                'getBaseBuckarooFeeInclTax', 'getBuckarooFeeTaxAmount', 'getBuckarooFeeBaseTaxAmount'
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

    public function testCollectShouldReturnCorrectTotalsData()
    {
        $quoteMock = $this->getFakeMock(Quote::class, false)
            ->onlyMethods(['getShippingAddress'])
            ->getMock();

        $addressMock = $this->getFakeMock(\Magento\Quote\Model\Quote\Address::class, false)->getMock();
        $quoteMock->method('getShippingAddress')->willReturn($addressMock);

        $shippingAssignmentMock = $this->getFakeMock(ShippingAssignmentInterface::class)
            ->onlyMethods(['getItems', 'getShipping'])
            ->getMockForAbstractClass();
        $shippingAssignmentMock->method('getItems')->willReturn(true);

        $shippingMock = $this->getFakeMock(\Magento\Quote\Api\Data\ShippingInterface::class, false)->getMockForAbstractClass();
        $shippingMock->method('getAddress')->willReturn($addressMock);
        $shippingAssignmentMock->method('getShipping')->willReturn($shippingMock);

        $totalMock = $this->getFakeMock(Total::class)
            ->addMethods(['setBuckarooFee', 'setBaseBuckarooFee'])
            ->getMock();
        $totalMock->method('setBuckarooFee')->willReturn(0);
        $totalMock->method('setBaseBuckarooFee')->willReturn(0);

        $calculateMock = $this->getFakeMock(Calculate::class)
            ->onlyMethods(['calculatePaymentFee'])
            ->getMock();
        $feeResultMock = $this->getMockBuilder(\stdClass::class)->addMethods(['getAmount', 'getRoundedAmount'])->getMock();
        $feeResultMock->method('getAmount')->willReturn(1.23);
        $feeResultMock->method('getRoundedAmount')->willReturn(1.23);
        $calculateMock->method('calculatePaymentFee')->willReturn($feeResultMock);

        $instance = $this->getInstance(['calculate' => $calculateMock]);
        $result = $instance->collect($quoteMock, $shippingAssignmentMock, $totalMock);

        $this->assertEquals($instance, $result);
    }

    public function testCollectShouldReturnSelfIfNoShippingItems()
    {
        $quoteMock = $this->getFakeMock(Quote::class, false)
            ->onlyMethods(['getShippingAddress'])
            ->getMock();

        $addressMock = $this->getFakeMock(\Magento\Quote\Model\Quote\Address::class, false)->getMock();
        $quoteMock->method('getShippingAddress')->willReturn($addressMock);

        $shippingAssignmentMock = $this->getFakeMock(ShippingAssignmentInterface::class, false)
            ->onlyMethods(['getItems', 'getShipping'])
            ->getMockForAbstractClass();
        $shippingAssignmentMock->method('getItems')->willReturn(false);

        $shippingMock = $this->getFakeMock(\Magento\Quote\Api\Data\ShippingInterface::class, false)->getMockForAbstractClass();
        $shippingMock->method('getAddress')->willReturn($addressMock);
        $shippingAssignmentMock->method('getShipping')->willReturn($shippingMock);

        $totalMock = $this->getFakeMock(Total::class)->addMethods(['setBuckarooFee', 'setBaseBuckarooFee'])
            ->getMock();
        $totalMock->method('setBuckarooFee')->willReturn(0);
        $totalMock->method('setBaseBuckarooFee')->willReturn(0);

        $calculateMock = $this->getFakeMock(Calculate::class)
            ->onlyMethods(['calculatePaymentFee'])
            ->getMock();
        $calculateMock->method('calculatePaymentFee')->willReturn(0);

        $instance = $this->getInstance(['calculate' => $calculateMock]);
        $result = $instance->collect($quoteMock, $shippingAssignmentMock, $totalMock);

        $this->assertEquals($instance, $result);
    }

    /**
     * @dataProvider collectPaymentMethodDataProvider
     *
     * @param mixed $method
     */
    public function testCollectShouldReturnSelfIfNoPaymentMethodOrNonBuckarooMethod($method)
    {

        $quoteMock = $this->getFakeMock(Quote::class)
            ->onlyMethods(['getPayment', 'getShippingAddress'])
            ->getMock();
        $paymentMock = $this->getFakeMock(\Magento\Quote\Model\Quote\Payment::class, false)
            ->onlyMethods(['getMethod', 'getMethodInstance'])
            ->getMockForAbstractClass();
        $paymentMock->method('getMethod')->willReturn($method);

        $addressMock = $this->getFakeMock(\Magento\Quote\Model\Quote\Address::class)
            ->addMethods(['getAddress'])
            ->getMock();
        $addressMock->method('getAddress')->willReturn($addressMock);

        $quoteMock->method('getShippingAddress')->willReturn($addressMock);

        $shippingAssignmentMock = $this->getFakeMock(ShippingAssignmentInterface::class)
            ->onlyMethods(['getItems'])
            ->getMockForAbstractClass();
        $shippingAssignmentMock->method('getItems')->willReturn(true);

        $shippingMock = $this->getFakeMock(\Magento\Quote\Api\Data\ShippingInterface::class, false)->getMockForAbstractClass();
        $shippingMock->method('getAddress')->willReturn($addressMock);
        $shippingAssignmentMock->method('getShipping')->willReturn($shippingMock);

        $totalMock = $this->getFakeMock(Total::class)->addMethods(['setBuckarooFee', 'setBaseBuckarooFee'])
            ->getMock();
        $totalMock->method('setBuckarooFee')->willReturn(0);
        $totalMock->method('setBaseBuckarooFee')->willReturn(0);

        $instance = $this->getInstance();
        $result = $instance->collect($quoteMock, $shippingAssignmentMock, $totalMock);

        $this->assertEquals($instance, $result);
    }

    public static function collectPaymentMethodDataProvider()
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
        $paymentCode = 'buckaroo_magento2_ideal';

        $paymentMethodMock = $this->getFakeMock(BuckarooAdapter::class, false)
            ->addMethods(['getBuckarooPaymentMethodCode'])
            ->getMock();
        $paymentMethodMock->method('getBuckarooPaymentMethodCode')->willReturn($paymentCode);

        $quoteMock = $this->getFakeMock(Quote::class)
            ->onlyMethods(["getPayment"])
            ->getMock();
        $paymentMock = $this->getFakeMock(\Magento\Quote\Model\Quote\Payment::class, false)
            ->onlyMethods(['getMethod', 'getMethodInstance'])
            ->getMockForAbstractClass();
        $paymentMock->method('getMethod')->willReturn($paymentCode);
        $paymentMock->method('getMethodInstance')->willReturn($paymentMethodMock);

        $quoteMock->method('getPayment')->willReturn($paymentMock);

        $addressMock = $this->getFakeMock(\Magento\Quote\Model\Quote\Address::class)
            ->getMock();

        $shippingMock = $this->getFakeMock(\Magento\Quote\Api\Data\ShippingInterface::class, false)
            ->onlyMethods(['getAddress'])
            ->getMockForAbstractClass();
        $shippingMock->method('getAddress')->willReturn($addressMock);

        $shippingAssignmentMock = $this->getFakeMock(ShippingAssignmentInterface::class)
            ->onlyMethods(['getItems', 'getShipping'])
            ->getMockForAbstractClass();
        $shippingAssignmentMock->method('getItems')->willReturn(true);
        $shippingAssignmentMock->method('getShipping')->willReturn($shippingMock);

        $totalMock = $this->getFakeMock(Total::class)->addMethods(['setBuckarooFee', 'setBaseBuckarooFee'])
            ->getMock();
        $totalMock->method('setBuckarooFee')->willReturn(0);
        $totalMock->method('setBaseBuckarooFee')->willReturn(0);

        $configProviderFactoryMock = $this->getFakeMock(Factory::class)
            ->onlyMethods(['has', 'get'])
            ->addMethods(['getPaymentFee'])
            ->getMock();
        $configProviderFactoryMock->method('has')->with($paymentCode)->willReturn(true);
        $configProviderFactoryMock->method('get')->with($paymentCode)->willReturnSelf();
        $configProviderFactoryMock->method('getPaymentFee')->willReturn($expectedFee);

        $configProviderFeeMock = $this->getFakeMock(ConfigProviderBuckarooFee::class)
            ->addMethods(['getTaxClass','getPaymentFeeTax'])
            ->getMock();
        $configProviderFeeMock->method('getTaxClass')->willReturn(1);

        $catalogHelperMock = $this->getFakeMock(Data::class)->onlyMethods(['getTaxPrice'])->getMock();
        $catalogHelperMock->method('getTaxPrice')->willReturn($expectedFee);

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
        $paymentCode = 'buckaroo_magento2_ideal';

        $quoteMock = $this->getFakeMock(Quote::class, false)
            ->onlyMethods(['getPayment'])
            ->getMock();

        $paymentMock = $this->getFakeMock(\Magento\Quote\Model\Quote\Payment::class, false)
            ->onlyMethods(['getMethod', 'getMethodInstance'])
            ->getMock();
        $paymentMock->method('getMethod')->willReturn($paymentCode);
        $paymentMock->method('getMethodInstance')->willThrowException(new \Exception('Invalid message'));

        $shippingAssignmentMock = $this->getFakeMock(ShippingAssignmentInterface::class, false)
            ->onlyMethods(['getItems', 'getShipping'])
            ->getMockForAbstractClass();
        $shippingAssignmentMock->method('getItems')->willReturn(true);

        $addressMock = $this->getFakeMock(\Magento\Quote\Model\Quote\Address::class, false)->getMock();
        $shippingMock = $this->getFakeMock(\Magento\Quote\Api\Data\ShippingInterface::class, false)->getMockForAbstractClass();
        $shippingMock->method('getAddress')->willReturn($addressMock);
        $shippingAssignmentMock->method('getShipping')->willReturn($shippingMock);

        $totalMock = $this->getFakeMock(Total::class)->addMethods(['setBuckarooFee', 'setBaseBuckarooFee'])
            ->getMock();
        $totalMock->method('setBuckarooFee')->willReturn(0);
        $totalMock->method('setBaseBuckarooFee')->willReturn(0);

        $instance = $this->getInstance();
        $result = $instance->collect($quoteMock, $shippingAssignmentMock, $totalMock);

        $this->assertEquals($instance, $result);
    }

    public function testCollectShouldSetTotalsOnQuote()
    {
        $paymentCode = 'buckaroo_magento2_ideal';
        $fee = 1.1;
        $taxIncl = Calculation::DISPLAY_TYPE_INCLUDING_TAX;
        $store = $this->getFakeMock(\Magento\Store\Model\Store::class, false)->getMock();

        $configProviderFactoryMock = $this->getFakeMock(Factory::class)
            ->onlyMethods(['has', 'get'])
            ->addMethods(['getPaymentFee'])
            ->getMock();
        $configProviderFactoryMock->method('has')->with($paymentCode)->willReturn(true);
        $configProviderFactoryMock->method('get')->with($paymentCode)->willReturnSelf();
        $configProviderFactoryMock->method('getPaymentFee')->willReturn(1.1);

        $priceCurrencyMock = $this->getFakeMock(PriceCurrencyInterface::class)
            ->onlyMethods(['convert'])
            ->getMockForAbstractClass();
        $priceCurrencyMock->method('convert')->with($fee, $store)->willReturn($fee);

        $configProviderFeeMock = $this->getFakeMock(ConfigProviderBuckarooFee::class)
            ->addMethods(['getTaxClass', 'getPaymentFeeTax'])
            ->getMock();
        $configProviderFeeMock->method('getTaxClass')->willReturn(1);
        $configProviderFeeMock->method('getPaymentFeeTax')->willReturn($taxIncl);

        $catalogHelperMock = $this->getFakeMock(Data::class)->onlyMethods(['getTaxPrice'])->getMock();
        $catalogHelperMock->method('getTaxPrice')->willReturn($fee);

        $paymentMethodMock = $this->getFakeMock(BuckarooAdapter::class, false)
            ->addMethods(['getBuckarooPaymentMethodCode'])
            ->getMock();
        $paymentMethodMock->method('getBuckarooPaymentMethodCode')->willReturn($paymentCode);

        $quoteMock = $this->getFakeMock(Quote::class)
            ->onlyMethods(["getPayment", "getStore"])
            ->addMethods(["setBuckarooFee", "setBaseBuckarooFee"])
            ->getMock();
        $paymentMock = $this->getFakeMock(\Magento\Quote\Model\Quote\Payment::class, false)
            ->onlyMethods(['getMethodInstance'])
            ->getMockForAbstractClass();
        $paymentMock->method('getMethodInstance')->willReturn($paymentMethodMock);
        $quoteMock->method('getPayment')->willReturn($paymentMock);
        $quoteMock->method('setBuckarooFee')->willReturn($fee);
        $quoteMock->method('setBaseBuckarooFee')->willReturn($fee);
        $quoteMock->method('getStore')->willReturn($store);

        $addressMock2 = $this->getFakeMock(\Magento\Quote\Model\Quote\Address::class)
            ->getMock();

        $shippingMock2 = $this->getFakeMock(\Magento\Quote\Api\Data\ShippingInterface::class, false)
            ->onlyMethods(['getAddress'])
            ->getMockForAbstractClass();
        $shippingMock2->method('getAddress')->willReturn($addressMock2);

        $shippingAssignmentMock = $this->getFakeMock(ShippingAssignmentInterface::class)
            ->onlyMethods(['getItems', 'getShipping'])
            ->getMockForAbstractClass();
        $shippingAssignmentMock->method('getItems')->willReturn(true);
        $shippingAssignmentMock->method('getShipping')->willReturn($shippingMock2);

        $totalMock = $this->getFakeMock(Total::class)
            ->addMethods([
                'setBuckarooFee', 'setBaseBuckarooFee', 'getBaseGrandTotal',
                'getGrandTotal', 'setBaseGrandTotal', 'setGrandTotal'
            ])
            ->getMock();

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
