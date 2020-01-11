<?php
/**
 *                  ___________       __            __
 *                  \__    ___/____ _/  |_ _____   |  |
 *                    |    |  /  _ \\   __\\__  \  |  |
 *                    |    | |  |_| ||  |   / __ \_|  |__
 *                    |____|  \____/ |__|  (____  /|____/
 *                                              \/
 *          ___          __                                   __
 *         |   |  ____ _/  |_   ____ _______   ____    ____ _/  |_
 *         |   | /    \\   __\_/ __ \\_  __ \ /    \ _/ __ \\   __\
 *         |   ||   |  \|  |  \  ___/ |  | \/|   |  \\  ___/ |  |
 *         |___||___|  /|__|   \_____>|__|   |___|  / \_____>|__|
 *                  \/                           \/
 *                  ________
 *                 /  _____/_______   ____   __ __ ______
 *                /   \  ___\_  __ \ /  _ \ |  |  \\____ \
 *                \    \_\  \|  | \/|  |_| ||  |  /|  |_| |
 *                 \______  /|__|    \____/ |____/ |   __/
 *                        \/                       |__|
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Creative Commons License.
 * It is available through the world-wide-web at this URL:
 * http://creativecommons.org/licenses/by-nc-nd/3.0/nl/deed.en_US
 * If you are unable to obtain it through the world-wide-web, please send an email
 * to servicedesk@tig.nl so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade this module to newer
 * versions in the future. If you wish to customize this module for your
 * needs please contact servicedesk@tig.nl for more information.
 *
 * @copyright Copyright (c) Total Internet Group B.V. https://tig.nl/copyright
 * @license   http://creativecommons.org/licenses/by-nc-nd/3.0/nl/deed.en_US
 */
namespace TIG\Buckaroo\Test\Unit\Model\Quote\Total;

class BuckarooFeeTest extends \TIG\Buckaroo\Test\BaseTest
{
    /**
     * @var \TIG\Buckaroo\Model\Total\Quote\BuckarooFee
     */
    protected $object;

    /**
     * @var \Mockery\MockInterface
     */
    protected $debugger;

    /**
     * @var \Mockery\MockInterface
     */
    protected $order;

    /**
     * @var \Mockery\MockInterface
     */
    protected $objectManager;

    /** @var \Mockery\MockInterface */
    protected $configProviderAccount;

    /** @var \Mockery\MockInterface */
    protected $configProviderBuckarooFee;

    /**
     * @var \Mockery\MockInterface
     */
    protected $configProviderMethodFactory;

    /**
     * @var \Mockery\MockInterface
     */
    protected $priceCurrency;

    /**
     * @var \Mockery\MockInterface
     */
    protected $catalogHelper;

    /**
     * Setup the base mock objects.
     */
    public function setUp()
    {
        parent::setUp();

        $this->priceCurrency = \Mockery::mock(\Magento\Framework\Pricing\PriceCurrencyInterface::class);
        $this->objectManager = \Mockery::mock(\Magento\Framework\ObjectManagerInterface::class);
        $this->configProviderAccount = \Mockery::mock(\TIG\Buckaroo\Model\ConfigProvider\Account::class);
        $this->configProviderBuckarooFee = \Mockery::mock(\TIG\Buckaroo\Model\ConfigProvider\BuckarooFee::class);
        $this->configProviderMethodFactory = \Mockery::mock(\TIG\Buckaroo\Model\ConfigProvider\Method\Factory::class);
        $this->catalogHelper = \Mockery::mock(\Magento\Catalog\Helper\Data::class);

        $this->object = $this->objectManagerHelper->getObject(
            \TIG\Buckaroo\Model\Total\Quote\BuckarooFee::class,
            [
                'configProviderAccount' => $this->configProviderAccount,
                'configProviderBuckarooFee' => $this->configProviderBuckarooFee,
                'configProviderMethodFactory' => $this->configProviderMethodFactory,
                'priceCurrency' => $this->priceCurrency,
                'catalogHelper' => $this->catalogHelper,
            ]
        );
    }

    public function testGetBaseFeeReturnsConfigValueIfIsNumber()
    {
        $expectedFee = 1.89;
        $paymentCode = 'tig_buckaroo_ideal';
        $taxIncl = \TIG\Buckaroo\Model\Config\Source\TaxClass\Calculation::DISPLAY_TYPE_INCLUDING_TAX;

        /**
         * @var \TIG\Buckaroo\Model\Method\AbstractMethod $paymentMethod
         */
        $paymentMethod = \Mockery::mock(\TIG\Buckaroo\Model\Method\AbstractMethod::class);
        $paymentMethod->buckarooPaymentMethodCode = $paymentCode;

        $quote = \Mockery::mock(\Magento\Quote\Model\Quote::class);
        /**
         * @var \Magento\Quote\Model\Quote $quote
         */

        $this->configProviderBuckarooFee->shouldReceive('getTaxClass')->once()->andReturn(1);
        $this->configProviderBuckarooFee->shouldReceive('getPaymentFeeTax')->andReturn($taxIncl);
        $this->catalogHelper->shouldReceive('getTaxPrice')->andReturn($expectedFee);

        $this->configProviderMethodFactory->shouldReceive('has')->with($paymentCode)->andReturn(true);
        $this->configProviderMethodFactory->shouldReceive('get')->with($paymentCode)->andReturnSelf();
        $this->configProviderMethodFactory->shouldReceive('getPaymentFee')->atleast()->once()->andReturn($expectedFee);

        $this->assertEquals($expectedFee, $this->object->getBaseFee($paymentMethod, $quote));
    }

    public function testGetBaseFeeReturnFalseForANonExistingConfigProvider()
    {
        $paymentCode = 'tig_buckaroo_non_existing';
        /**
         * @var \TIG\Buckaroo\Model\Method\AbstractMethod $paymentMethod
         */
        $paymentMethod = \Mockery::mock(\TIG\Buckaroo\Model\Method\AbstractMethod::class);
        $paymentMethod->buckarooPaymentMethodCode = $paymentCode;

        $quote = \Mockery::mock(\Magento\Quote\Model\Quote::class);
        /**
         * @var \Magento\Quote\Model\Quote $quote
         */

        $this->configProviderMethodFactory->shouldReceive('has')->with($paymentCode)->andReturn(false);

        $this->assertFalse($this->object->getBaseFee($paymentMethod, $quote));
    }

    public function testGetBaseFeeReturnFalseForAnInvalidFeeValue()
    {
        $paymentCode = 'tig_buckaroo_ideal';
        $invalidFee = 'invalid';

        /**
         * @var \TIG\Buckaroo\Model\Method\AbstractMethod $paymentMethod
         */
        $paymentMethod = \Mockery::mock(\TIG\Buckaroo\Model\Method\AbstractMethod::class);
        $paymentMethod->buckarooPaymentMethodCode = $paymentCode;

        $quote = \Mockery::mock(\Magento\Quote\Model\Quote::class);
        /**
         * @var \Magento\Quote\Model\Quote $quote
         */

        $this->configProviderMethodFactory->shouldReceive('has')->with($paymentCode)->andReturn(true);
        $this->configProviderMethodFactory->shouldReceive('get')->with($paymentCode)->andReturnSelf();
        $this->configProviderMethodFactory->shouldReceive('getPaymentFee')->atleast()->once()->andReturn($invalidFee);

        $this->assertFalse($this->object->getBaseFee($paymentMethod, $quote));
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
        /**
         * @var \TIG\Buckaroo\Model\Method\AbstractMethod $paymentMethod
         */
        $paymentMethod = \Mockery::mock(\TIG\Buckaroo\Model\Method\AbstractMethod::class);
        $paymentMethod->buckarooPaymentMethodCode = $paymentCode;

        $quote = \Mockery::mock(\Magento\Quote\Model\Quote::class);
        $quote->shouldReceive('getShippingAddress')->andReturnSelf();
        $quote->shouldReceive('getStore')->andReturnSelf();
        $quote->shouldReceive($quoteMethod)->andReturn($quoteAmount);
        /**
         * @var \Magento\Quote\Model\Quote $quote
         */

        $this->configProviderMethodFactory->shouldReceive('has')->with($paymentCode)->andReturn(true);
        $this->configProviderMethodFactory->shouldReceive('get')->with($paymentCode)->andReturnSelf();
        $this->configProviderMethodFactory->shouldReceive('getPaymentFee')->atleast()->once()->andReturn($fee);

        $this->configProviderAccount->shouldReceive('getFeePercentageMode')->atleast()->once()->andReturn($feeMode);

        $this->assertEquals($expectedValue, $this->object->getBaseFee($paymentMethod, $quote));
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
        /**
         * @var \TIG\Buckaroo\Model\Method\AbstractMethod $paymentMethod
         */
        $paymentMethod = \Mockery::mock(\TIG\Buckaroo\Model\Method\AbstractMethod::class);
        $paymentMethod->buckarooPaymentMethodCode = $paymentCode;

        $quote = \Mockery::mock(\Magento\Quote\Model\Quote::class);
        $quote->shouldReceive('getShippingAddress')->andReturn(false);
        $quote->shouldReceive('getBillingAddress')->once()->andReturnSelf();
        $quote->shouldReceive('getStore')->once()->andReturnSelf();
        $quote->shouldReceive($quoteMethod)->andReturn($quoteAmount);
        /**
         * @var \Magento\Quote\Model\Quote $quote
         */

        $this->configProviderMethodFactory->shouldReceive('has')->with($paymentCode)->andReturn(true);
        $this->configProviderMethodFactory->shouldReceive('get')->with($paymentCode)->andReturnSelf();
        $this->configProviderMethodFactory->shouldReceive('getPaymentFee')->atleast()->once()->andReturn($fee);

        $this->configProviderAccount->shouldReceive('getFeePercentageMode')->atleast()->once()->andReturn($feeMode);

        $this->assertEquals($expectedValue, $this->object->getBaseFee($paymentMethod, $quote));
    }

    public function testGetLabelReturnsLabel()
    {
        $this->assertEquals('Payment Fee', $this->object->getLabel());
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

        $quoteMock = \Mockery::mock(\Magento\Quote\Model\Quote::class);
        /**
         * @var \Magento\Quote\Model\Quote $quoteMock
         */

        $totalMock = \Mockery::mock(\Magento\Quote\Model\Quote\Address\Total::class);
        $totalMock->shouldReceive('getBuckarooFee')->once()->andReturn($expectedBuckarooFee);
        $totalMock->shouldReceive('getBaseBuckarooFee')->once()->andReturn($expectedBaseBuckarooFee);
        $totalMock->shouldReceive('getBuckarooFeeInclTax')->once()->andReturn($expectedBuckarooFeeInclTax);
        $totalMock->shouldReceive('getBaseBuckarooFeeInclTax')->once()->andReturn($expectedBaseBuckarooFeeInclTax);
        $totalMock->shouldReceive('getBuckarooFeeTaxAmount')->once()->andReturn($expectedBuckarooFeeTaxAmount);
        $totalMock->shouldReceive('getBuckarooFeeBaseTaxAmount')->once()->andReturn($expectedBaseBuckarooFeeTaxAmount);
        /**
         * @var \Magento\Quote\Model\Quote\Address\Total $totalMock
         */

        $this->assertEquals($expected, $this->object->fetch($quoteMock, $totalMock));
    }

    public function testCollectShouldReturnSelfIfNoShippingItems()
    {
        $quoteMock = \Mockery::mock(\Magento\Quote\Model\Quote::class);
        /**
         * @var \Magento\Quote\Model\Quote $quoteMock
         */

        $shippingAssignmentMock = \Mockery::mock(\Magento\Quote\Api\Data\ShippingAssignmentInterface::class);
        $shippingAssignmentMock->shouldReceive('getItems')->once()->andReturn(false);
        /**
         * @var \Magento\Quote\Api\Data\ShippingAssignmentInterface $shippingAssignmentMock
         */

        $totalMock = \Mockery::mock(\Magento\Quote\Model\Quote\Address\Total::class);
        $totalMock->shouldReceive('setBuckarooFee')->once()->with(0);
        $totalMock->shouldReceive('setBaseBuckarooFee')->once()->with(0);
        /**
         * @var \Magento\Quote\Model\Quote\Address\Total $totalMock
         */

        $this->assertEquals($this->object, $this->object->collect($quoteMock, $shippingAssignmentMock, $totalMock));
    }

    /**
     * @dataProvider collectPaymentMethodDataProvider
     *
     * @param $method
     */
    public function testCollectShouldReturnSelfIfNoPaymentMethodOrNonBuckarooMethod($method)
    {
        $quoteMock = \Mockery::mock(\Magento\Quote\Model\Quote::class);
        $quoteMock->shouldReceive('getPayment')->andReturnSelf();
        $quoteMock->shouldReceive('getMethod')->andReturn($method);
        /**
         * @var \Magento\Quote\Model\Quote $quoteMock
         */

        $shippingAssignmentMock = \Mockery::mock(\Magento\Quote\Api\Data\ShippingAssignmentInterface::class);
        $shippingAssignmentMock->shouldReceive('getItems')->once()->andReturn(true);
        /**
         * @var \Magento\Quote\Api\Data\ShippingAssignmentInterface $shippingAssignmentMock
         */

        $totalMock = \Mockery::mock(\Magento\Quote\Model\Quote\Address\Total::class);
        $totalMock->shouldReceive('setBuckarooFee')->once()->with(0);
        $totalMock->shouldReceive('setBaseBuckarooFee')->once()->with(0);
        /**
         * @var \Magento\Quote\Model\Quote\Address\Total $totalMock
         */

        $this->assertEquals($this->object, $this->object->collect($quoteMock, $shippingAssignmentMock, $totalMock));
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
        $taxIncl = \TIG\Buckaroo\Model\Config\Source\TaxClass\Calculation::DISPLAY_TYPE_INCLUDING_TAX;

        $paymentMethod = \Mockery::mock(\TIG\Buckaroo\Model\Method\AbstractMethod::class);
        /**
         * @var \TIG\Buckaroo\Model\Method\AbstractMethod $paymentMethod
         */
        $paymentMethod->buckarooPaymentMethodCode = $paymentCode;

        $quoteMock = \Mockery::mock(\Magento\Quote\Model\Quote::class);
        $quoteMock->shouldReceive('getPayment')->andReturnSelf();
        $quoteMock->shouldReceive('getMethod')->andReturn($paymentCode);
        $quoteMock->shouldReceive('getMethodInstance')->andReturn($paymentMethod);
        /**
         * @var \Magento\Quote\Model\Quote $quoteMock
         */

        $this->configProviderMethodFactory->shouldReceive('has')->with($paymentCode)->andReturn(true);
        $this->configProviderMethodFactory->shouldReceive('get')->with($paymentCode)->andReturnSelf();
        $this->configProviderMethodFactory->shouldReceive('getPaymentFee')->atleast()->once()->andReturn($expectedFee);

        $this->configProviderBuckarooFee->shouldReceive('getTaxClass')->once()->andReturn(1);
        $this->configProviderBuckarooFee->shouldReceive('getPaymentFeeTax')->andReturn($taxIncl);
        $this->catalogHelper->shouldReceive('getTaxPrice')->andReturn($expectedFee);

        $shippingAssignmentMock = \Mockery::mock(\Magento\Quote\Api\Data\ShippingAssignmentInterface::class);
        $shippingAssignmentMock->shouldReceive('getItems')->once()->andReturn(true);
        /**
         * @var \Magento\Quote\Api\Data\ShippingAssignmentInterface $shippingAssignmentMock
         */

        $totalMock = \Mockery::mock(\Magento\Quote\Model\Quote\Address\Total::class);
        $totalMock->shouldReceive('setBuckarooFee')->once()->with(0);
        $totalMock->shouldReceive('setBaseBuckarooFee')->once()->with(0);
        /**
         * @var \Magento\Quote\Model\Quote\Address\Total $totalMock
         */

        $this->assertEquals($this->object, $this->object->collect($quoteMock, $shippingAssignmentMock, $totalMock));
    }

    public function testCollectShouldReturnSelfIfInvalidMessage()
    {
        $paymentCode = 'tig_buckaroo_ideal';

        $quoteMock = \Mockery::mock(\Magento\Quote\Model\Quote::class);
        $quoteMock->shouldReceive('getPayment')->andReturnSelf();
        $quoteMock->shouldReceive('getMethod')->andReturn($paymentCode);
        $quoteMock->shouldReceive('getMethodInstance')->andReturn(new \stdClass());
        /**
         * @var \Magento\Quote\Model\Quote $quoteMock
         */

        $shippingAssignmentMock = \Mockery::mock(\Magento\Quote\Api\Data\ShippingAssignmentInterface::class);
        $shippingAssignmentMock->shouldReceive('getItems')->once()->andReturn(true);
        /**
         * @var \Magento\Quote\Api\Data\ShippingAssignmentInterface $shippingAssignmentMock
         */

        $totalMock = \Mockery::mock(\Magento\Quote\Model\Quote\Address\Total::class);
        $totalMock->shouldReceive('setBuckarooFee')->once()->with(0);
        $totalMock->shouldReceive('setBaseBuckarooFee')->once()->with(0);
        /**
         * @var \Magento\Quote\Model\Quote\Address\Total $totalMock
         */

        $this->assertEquals($this->object, $this->object->collect($quoteMock, $shippingAssignmentMock, $totalMock));
    }

    public function testCollectShouldSetTotalsOnQuote()
    {
        $paymentCode = 'tig_buckaroo_ideal';
        $fee = 1.1;
        $grandTotal = 45.0000;
        $baseGrandTotal = 45.0000;
        $taxIncl = \TIG\Buckaroo\Model\Config\Source\TaxClass\Calculation::DISPLAY_TYPE_INCLUDING_TAX;

        $store = 1;

        $this->configProviderMethodFactory->shouldReceive('has')->with($paymentCode)->andReturn(true);
        $this->configProviderMethodFactory->shouldReceive('get')->with($paymentCode)->andReturnSelf();
        $this->configProviderMethodFactory->shouldReceive('getPaymentFee')->atleast()->once()->andReturn(1.1);

        $this->priceCurrency->shouldReceive('convert')->once()->with($fee, $store)->andReturn($fee);

        $this->configProviderBuckarooFee->shouldReceive('getTaxClass')->once()->andReturn(1);
        $this->configProviderBuckarooFee->shouldReceive('getPaymentFeeTax')->andReturn($taxIncl);
        $this->catalogHelper->shouldReceive('getTaxPrice')->andReturn($fee);

        $paymentMethod = \Mockery::mock(\TIG\Buckaroo\Model\Method\AbstractMethod::class);
        /**
         * @var \TIG\Buckaroo\Model\Method\AbstractMethod $paymentMethod
         */
        $paymentMethod->buckarooPaymentMethodCode = $paymentCode;

        $quoteMock = \Mockery::mock(\Magento\Quote\Model\Quote::class);
        $quoteMock->shouldReceive('getPayment')->andReturnSelf();
        $quoteMock->shouldReceive('getStore')->andReturn($store);
        $quoteMock->shouldReceive('getMethod')->andReturn($paymentCode);
        $quoteMock->shouldReceive('getMethodInstance')->andReturn($paymentMethod);
        $quoteMock->shouldReceive('setBuckarooFee')->once()->with($fee);
        $quoteMock->shouldReceive('setBaseBuckarooFee')->once()->with($fee);
        /**
         * @var \Magento\Quote\Model\Quote $quoteMock
         */

        $shippingAssignmentMock = \Mockery::mock(\Magento\Quote\Api\Data\ShippingAssignmentInterface::class);
        $shippingAssignmentMock->shouldReceive('getItems')->once()->andReturn(true);
        /**
         * @var \Magento\Quote\Api\Data\ShippingAssignmentInterface $shippingAssignmentMock
         */

        $totalMock = \Mockery::mock(\Magento\Quote\Model\Quote\Address\Total::class);
        $totalMock->shouldReceive('setBuckarooFee')->once()->with(0);
        $totalMock->shouldReceive('setBaseBuckarooFee')->once()->with(0);
        $totalMock->shouldReceive('setBuckarooFee')->once()->with($fee);
        $totalMock->shouldReceive('setBaseBuckarooFee')->once()->with($fee);
        $totalMock->shouldReceive('getBaseGrandTotal')->once()->andReturn($baseGrandTotal);
        $totalMock->shouldReceive('getGrandTotal')->once()->andReturn($grandTotal);
        $totalMock->shouldReceive('setBaseGrandTotal')->once()->with($fee + $baseGrandTotal);
        $totalMock->shouldReceive('setGrandTotal')->once()->with($fee + $grandTotal);

        /**
         * @var \Magento\Quote\Model\Quote\Address\Total $totalMock
         */

        $this->assertEquals($this->object, $this->object->collect($quoteMock, $shippingAssignmentMock, $totalMock));
    }
}
