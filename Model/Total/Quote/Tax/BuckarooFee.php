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

namespace Buckaroo\Magento2\Model\Total\Quote\Tax;

use Buckaroo\Magento2\Helper\PaymentGroupTransaction;
use Buckaroo\Magento2\Service\BuckarooFee\Calculate;
use Buckaroo\Magento2\Service\BuckarooFee\Result;
use Magento\Customer\Api\AccountManagementInterface as CustomerAccountManagement;
use Magento\Customer\Api\Data\AddressInterfaceFactory as CustomerAddressFactory;
use Magento\Customer\Api\Data\RegionInterfaceFactory as CustomerAddressRegionFactory;
use Magento\Framework\Pricing\PriceCurrencyInterface;
use Magento\Quote\Api\Data\ShippingAssignmentInterface;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Address\Total;
use Magento\Tax\Api\Data\QuoteDetailsInterfaceFactory;
use Magento\Tax\Api\Data\QuoteDetailsItemExtensionInterfaceFactory;
use Magento\Tax\Api\Data\QuoteDetailsItemInterfaceFactory;
use Magento\Tax\Api\Data\TaxClassKeyInterfaceFactory;
use Magento\Tax\Api\TaxCalculationInterface;
use Magento\Tax\Helper\Data as TaxHelper;
use Magento\Tax\Model\Config as TaxConfig;
use Magento\Tax\Model\Sales\Total\Quote\CommonTaxCollector;

/**
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class BuckarooFee extends CommonTaxCollector
{
    const QUOTE_TYPE = 'buckaroo_fee_tax';
    const CODE_QUOTE_GW = 'buckaroo_fee_tax';

    /**
     * @var PriceCurrencyInterface
     */
    protected $priceCurrency;

    /**
     * @var PaymentGroupTransaction
     */
    protected $groupTransaction;

    /**
     * @var Calculate
     */
    protected $calculate;

    /**
     * @var \Buckaroo\Magento2\Model\ConfigProvider\BuckarooFee
     */
    protected $configProviderBuckarooFee;

    /**
     * @var TaxHelper|null
     */
    protected $taxHelper;

    /**
     * Constructor
     *
     * @param TaxConfig $taxConfig
     * @param TaxCalculationInterface $taxCalculationService
     * @param QuoteDetailsInterfaceFactory $quoteDetailsDataObjectFactory
     * @param QuoteDetailsItemInterfaceFactory $quoteDetailsItemDataObjectFactory
     * @param TaxClassKeyInterfaceFactory $taxClassKeyDataObjectFactory
     * @param CustomerAddressFactory $customerAddressFactory
     * @param CustomerAddressRegionFactory $customerAddressRegionFactory
     * @param PriceCurrencyInterface $priceCurrency
     * @param PaymentGroupTransaction $groupTransaction
     * @param Calculate $calculate
     * @param \Buckaroo\Magento2\Model\ConfigProvider\BuckarooFee $configProviderBuckarooFee
     * @param TaxHelper|null $taxHelper
     * @param QuoteDetailsItemExtensionInterfaceFactory|null $quoteDetailsItemExtensionInterfaceFactory
     * @param CustomerAccountManagement|null $customerAccountManagement
     *
     * @SuppressWarnings(PHPMD.ExcessiveParameterList)
     */
    public function __construct(
        TaxConfig $taxConfig,
        TaxCalculationInterface $taxCalculationService,
        QuoteDetailsInterfaceFactory $quoteDetailsDataObjectFactory,
        QuoteDetailsItemInterfaceFactory $quoteDetailsItemDataObjectFactory,
        TaxClassKeyInterfaceFactory $taxClassKeyDataObjectFactory,
        CustomerAddressFactory $customerAddressFactory,
        CustomerAddressRegionFactory $customerAddressRegionFactory,
        PriceCurrencyInterface $priceCurrency,
        PaymentGroupTransaction $groupTransaction,
        Calculate $calculate,
        \Buckaroo\Magento2\Model\ConfigProvider\BuckarooFee $configProviderBuckarooFee,
        ?TaxHelper $taxHelper = null,
        ?QuoteDetailsItemExtensionInterfaceFactory $quoteDetailsItemExtensionInterfaceFactory = null,
        ?CustomerAccountManagement $customerAccountManagement = null
    ) {
        $parent = new \ReflectionClass(parent::class);
        $parentConstructor = $parent->getConstructor();

        // The parent call fails when running setup:di:compile in 2.4.3 and lower due to an extra parameter.
        if ($parentConstructor->getNumberOfParameters() == 9) {
            // @phpstan-ignore-next-line
            parent::__construct(
                $taxConfig,
                $taxCalculationService,
                $quoteDetailsDataObjectFactory,
                $quoteDetailsItemDataObjectFactory,
                $taxClassKeyDataObjectFactory,
                $customerAddressFactory,
                $customerAddressRegionFactory,
                $taxHelper,
                $quoteDetailsItemExtensionInterfaceFactory
            );
        } else {
            // @phpstan-ignore-next-line
            parent::__construct(
                $taxConfig,
                $taxCalculationService,
                $quoteDetailsDataObjectFactory,
                $quoteDetailsItemDataObjectFactory,
                $taxClassKeyDataObjectFactory,
                $customerAddressFactory,
                $customerAddressRegionFactory,
                $taxHelper,
                $quoteDetailsItemExtensionInterfaceFactory,
                $customerAccountManagement
            );
        }

        $this->priceCurrency = $priceCurrency;
        $this->groupTransaction = $groupTransaction;
        $this->calculate = $calculate;
        $this->configProviderBuckarooFee = $configProviderBuckarooFee;
        $this->taxHelper = $taxHelper;
        $this->setCode('pretax_buckaroo_fee');
    }

    /**
     * Collect Buckaroo fee related items and add them to tax calculation
     *
     * @param  Quote $quote
     * @param  ShippingAssignmentInterface $shippingAssignment
     * @param  Total $total
     * @return $this
     */
    public function collect(
        Quote $quote,
        ShippingAssignmentInterface $shippingAssignment,
        Total $total
    ) {
        if (!$shippingAssignment->getItems()) {
            parent::collect($quote, $shippingAssignment, $total);
            return $this;
        }
        $orderId = $quote->getReservedOrderId();

        // Check if already paid amount is affecting the calculation
        if ($this->groupTransaction->getAlreadyPaid($orderId) > 0) {
            return $this;
        }

        $result = $this->calculate->calculatePaymentFee($quote, $total);

        if ($result === null) {
            return $this;
        }

        $this->addAssociatedTaxable($shippingAssignment, $result, $quote);

        parent::collect($quote, $shippingAssignment, $total);

        return $this;
    }

    /**
     * Add associated taxable for Buckaroo fee
     *
     * @param ShippingAssignmentInterface $shippingAssignment
     * @param \Buckaroo\Magento2\Service\BuckarooFee\Result $result
     * @param Quote $quote
     * @return void
     */
    private function addAssociatedTaxable(ShippingAssignmentInterface $shippingAssignment, Result $result, Quote $quote)
    {
        $fullAmount = $this->priceCurrency->convert($result->getRoundedAmount());

        $address = $shippingAssignment->getShipping()->getAddress();
        /**
         * @noinspection PhpUndefinedMethodInspection
         */
        $associatedTaxables = $address->getAssociatedTaxables();
        if (!$associatedTaxables) {
            $associatedTaxables = [];
        }

        // Retrieve the store from the quote
        $store = $quote->getStore();

        // Retrieve the Buckaroo fee tax class ID from BuckarooFeeConfigProvider using the store
        $taxClassId = $this->configProviderBuckarooFee->getBuckarooFeeTaxClass($store);
        $associatedTaxables[] = [
            CommonTaxCollector::KEY_ASSOCIATED_TAXABLE_TYPE => self::QUOTE_TYPE,
            CommonTaxCollector::KEY_ASSOCIATED_TAXABLE_CODE => self::CODE_QUOTE_GW,
            CommonTaxCollector::KEY_ASSOCIATED_TAXABLE_UNIT_PRICE => $fullAmount,
            CommonTaxCollector::KEY_ASSOCIATED_TAXABLE_BASE_UNIT_PRICE => $result->getRoundedAmount(),
            CommonTaxCollector::KEY_ASSOCIATED_TAXABLE_QUANTITY => 1,
            CommonTaxCollector::KEY_ASSOCIATED_TAXABLE_TAX_CLASS_ID => $taxClassId,
            CommonTaxCollector::KEY_ASSOCIATED_TAXABLE_PRICE_INCLUDES_TAX => false,
            CommonTaxCollector::KEY_ASSOCIATED_TAXABLE_ASSOCIATION_ITEM_CODE => CommonTaxCollector::ASSOCIATION_ITEM_CODE_FOR_QUOTE,
        ];

        /**
         * @noinspection PhpUndefinedMethodInspection
         */
        $address->setAssociatedTaxables($associatedTaxables);
    }
}
