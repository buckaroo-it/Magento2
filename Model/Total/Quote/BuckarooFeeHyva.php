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
namespace Buckaroo\Magento2\Model\Total\Quote;

use Buckaroo\Magento2\Exception;
use Buckaroo\Magento2\Helper\PaymentGroupTransaction;
use Buckaroo\Magento2\Logging\Log;
use Buckaroo\Magento2\Model\Config\Source\TaxClass\Calculation;
use Buckaroo\Magento2\Model\ConfigProvider\Account as ConfigProviderAccount;
use Buckaroo\Magento2\Model\ConfigProvider\BuckarooFee as ConfigProviderBuckarooFee;
use Buckaroo\Magento2\Model\ConfigProvider\Method\Factory;
use Buckaroo\Magento2\Service\HyvaCheckoutConfig;
use Magento\Catalog\Helper\Data;
use Magento\Framework\Pricing\PriceCurrencyInterface;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Address\Total;
use Magento\Tax\Model\Calculation as TaxModelCalculation;

class BuckarooFeeHyva extends \Magento\Quote\Model\Quote\Address\Total\AbstractTotal
{
    /** @var ConfigProviderAccount */
    protected $configProviderAccount;

    /** @var ConfigProviderBuckarooFee */
    protected $configProviderBuckarooFee;

    /**
     * @var Factory
     */
    protected $configProviderMethodFactory;

    /**
     * @var PriceCurrencyInterface
     */
    public $priceCurrency;

    /**
     * @var Data
     */
    public $catalogHelper;

    /**
     * @var PaymentGroupTransaction
     */
    public $groupTransaction;

    /**
     * @var Calculation
     */
    protected $taxCalculation;

    /**
     * @var Log $logging
     */
    protected $logging;

    /**
     * @var HyvaCheckoutConfig
     */
    protected $configProvider;

    /**
     * @param ConfigProviderAccount $configProviderAccount
     * @param ConfigProviderBuckarooFee $configProviderBuckarooFee
     * @param Factory $configProviderMethodFactory
     * @param PriceCurrencyInterface $priceCurrency
     * @param Data $catalogHelper
     * @param PaymentGroupTransaction $groupTransaction
     * @param Log $logging
     * @param TaxModelCalculation $taxCalculation
     * @param HyvaCheckoutConfig $configProvider
     */
    public function __construct(
        ConfigProviderAccount $configProviderAccount,
        ConfigProviderBuckarooFee $configProviderBuckarooFee,
        Factory $configProviderMethodFactory,
        PriceCurrencyInterface $priceCurrency,
        Data $catalogHelper,
        PaymentGroupTransaction $groupTransaction,
        Log $logging,
        TaxModelCalculation $taxCalculation,
        HyvaCheckoutConfig $configProvider,
    ) {
        $this->setCode('buckaroo_fee_hyva');

        $this->configProviderAccount = $configProviderAccount;
        $this->configProviderBuckarooFee = $configProviderBuckarooFee;
        $this->configProviderMethodFactory = $configProviderMethodFactory;
        $this->priceCurrency = $priceCurrency;
        $this->catalogHelper = $catalogHelper;

        $this->groupTransaction = $groupTransaction;
        $this->logging = $logging;
        $this->taxCalculation = $taxCalculation;
        $this->configProvider = $configProvider;
    }

    /**
     * Collect grand total address amount
     *
     * @param \Magento\Quote\Model\Quote $quote
     * @param \Magento\Quote\Api\Data\ShippingAssignmentInterface $shippingAssignment
     * @param \Magento\Quote\Model\Quote\Address\Total $total
     * @return $this
     *
     * @throws \LogicException
     * @throws Exception
     */
    public function collect(
        \Magento\Quote\Model\Quote $quote,
        \Magento\Quote\Api\Data\ShippingAssignmentInterface $shippingAssignment,
        \Magento\Quote\Model\Quote\Address\Total $total
    ) {
        if (!$this->configProvider->isHyvaCheckoutEnabled()) {
            return $this;
        }

        parent::collect($quote, $shippingAssignment, $total);

        // Ensure that shipping assignment has items, otherwise skip processing.
        if (!$shippingAssignment->getItems()) {
            return $this;
        }

        /**
         * @noinspection PhpUndefinedMethodInspection
         */
        $total->setBuckarooFee(0);
        /**
         * @noinspection PhpUndefinedMethodInspection
         */
        $total->setBaseBuckarooFee(0);

        $orderId = $quote->getReservedOrderId();

        // Check if already paid amount is affecting the calculation
        if ($this->groupTransaction->getAlreadyPaid($orderId) > 0) {
            return $this;
        }

        // Ensure payment method is set correctly
        $paymentMethod = $quote->getPayment()->getMethod();
        if (!$paymentMethod || strpos($paymentMethod, 'buckaroo_magento2_') !== 0) {
            return $this;
        }

        $methodInstance = $quote->getPayment()->getMethodInstance();
        if (!$methodInstance instanceof \Buckaroo\Magento2\Model\Method\AbstractMethod) {
            return $this;
        }

        // Calculate the base payment fee using the getBaseFee method
        $basePaymentFee = $this->getBaseFee($methodInstance, $quote, $total);
        if ($basePaymentFee < 0.01) {
            return $this;
        }

        // Convert the fee to the store's currency
        $paymentFee = $this->priceCurrency->convert($basePaymentFee, $quote->getStore());

        // Add fee amounts using addTotalAmount to ensure proper accumulation with other totals
        $total->addTotalAmount('buckaroo_fee_hyva', $paymentFee);
        $total->addBaseTotalAmount('buckaroo_fee_hyva', $basePaymentFee);

        // Set the fee on the total object for further calculations
        /**
         * @noinspection PhpUndefinedMethodInspection
         */
        $total->setBuckarooFee($paymentFee);
        /**
         * @noinspection PhpUndefinedMethodInspection
         */
        $total->setBaseBuckarooFee($basePaymentFee);

        return $this;
    }

    /**
     * Add buckaroo fee information to address
     *
     * @param Quote $quote
     * @param Total $total
     * @return array
     */
    public function fetch(\Magento\Quote\Model\Quote $quote, \Magento\Quote\Model\Quote\Address\Total $total)
    {
        // Determine whether to include tax in the fee value based on your configuration settings
        $includeTax = $this->configProviderBuckarooFee->getPaymentFeeTax() == \Buckaroo\Magento2\Model\Config\Source\TaxClass\Calculation::DISPLAY_TYPE_INCLUDING_TAX;

        // Calculate the value based on the settings (either including or excluding tax)
        $value = $includeTax ? $total->getBuckarooFeeInclTax() : $total->getBuckarooFee();

        return [
            'code'  => $this->getCode(),
            'title' => $this->getLabel(),
            'value' => $value
        ];
    }

    /**
     * @param \Buckaroo\Magento2\Model\Method\AbstractMethod $methodInstance
     * @param \Magento\Quote\Model\Quote                $quote
     * @param \Magento\Quote\Model\Quote\Address\Total $total
     *
     * @return bool|false|float
     * @throws \Buckaroo\Magento2\Exception
     */
    public function getBaseFee(
        \Buckaroo\Magento2\Model\Method\AbstractMethod $methodInstance,
        \Magento\Quote\Model\Quote $quote,
        \Magento\Quote\Model\Quote\Address\Total $total
    ) {
        $buckarooPaymentMethodCode = $methodInstance->buckarooPaymentMethodCode;
        if (!$this->configProviderMethodFactory->has($buckarooPaymentMethodCode)) {
            return false;
        }

        $configProvider = $this->configProviderMethodFactory->get($buckarooPaymentMethodCode);
        $basePaymentFeeRaw = trim($configProvider->getPaymentFee($quote->getStore()));

        $inclTax= $this->configProviderBuckarooFee->getPaymentFeeTax() ==
            Calculation::DISPLAY_TYPE_INCLUDING_TAX;

        // Determine if the fee is a percentage or fixed amount
        $isPercentage = strpos($basePaymentFeeRaw, '%') !== false;

        if ($isPercentage) {
            $subtotal = $total->getData('base_subtotal_incl_tax');
            if (!$subtotal) {
                $subtotal = $total->getTotalAmount('subtotal');
            }
            $percentage = (float) rtrim($basePaymentFeeRaw, '%');
            $basePaymentFee = ($percentage / 100) * $subtotal;
        } else {
            $basePaymentFee = (float) $basePaymentFeeRaw;
        }

        if (!$inclTax) {
            $shippingAddress = $quote->getShippingAddress() ?: $quote->getBillingAddress();
            $billingAddress = $quote->getBillingAddress();
            $customerTaxClassId = $quote->getCustomerTaxClassId();
            $storeId = $quote->getStoreId();
            $taxClassId = $this->configProviderBuckarooFee->getTaxClass();
            if (!$taxClassId) {
                $taxClassId = 0;
            }

            $taxRate = $this->taxCalculation->getRate(
                $this->taxCalculation->getRateRequest(
                    $shippingAddress,
                    $billingAddress,
                    $customerTaxClassId,
                    $storeId
                )->setProductClassId($taxClassId)
            );
            $paymentFee = $basePaymentFee * (1 + $taxRate / 100);
        } else {
            $paymentFee = $basePaymentFee;
        }

        return $paymentFee;
    }
    /**
     * Get Buckaroo label
     *
     * @return \Magento\Framework\Phrase
     */
    public function getLabel()
    {
        return __('Payment Fee');
    }
}
