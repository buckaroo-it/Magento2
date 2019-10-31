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
namespace TIG\Buckaroo\Model\Total\Quote;

use Magento\Catalog\Helper\Data;
use Magento\Framework\Pricing\PriceCurrencyInterface;
use TIG\Buckaroo\Model\Config\Source\TaxClass\Calculation;
use TIG\Buckaroo\Model\ConfigProvider\Account as ConfigProviderAccount;
use TIG\Buckaroo\Model\ConfigProvider\BuckarooFee as ConfigProviderBuckarooFee;
use TIG\Buckaroo\Model\ConfigProvider\Method\Factory;

class BuckarooFee extends \Magento\Quote\Model\Quote\Address\Total\AbstractTotal
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
     * @param ConfigProviderAccount     $configProviderAccount
     * @param ConfigProviderBuckarooFee $configProviderBuckarooFee
     * @param Factory                   $configProviderMethodFactory
     * @param PriceCurrencyInterface    $priceCurrency
     * @param Data                      $catalogHelper
     */
    public function __construct(
        ConfigProviderAccount $configProviderAccount,
        ConfigProviderBuckarooFee $configProviderBuckarooFee,
        Factory $configProviderMethodFactory,
        PriceCurrencyInterface $priceCurrency,
        Data $catalogHelper
    ) {
        $this->setCode('buckaroo_fee');

        $this->configProviderAccount = $configProviderAccount;
        $this->configProviderBuckarooFee = $configProviderBuckarooFee;
        $this->configProviderMethodFactory = $configProviderMethodFactory;
        $this->priceCurrency = $priceCurrency;
        $this->catalogHelper = $catalogHelper;
    }

    /**
     * Collect grand total address amount
     *
     * @param  \Magento\Quote\Model\Quote                          $quote
     * @param  \Magento\Quote\Api\Data\ShippingAssignmentInterface $shippingAssignment
     * @param  \Magento\Quote\Model\Quote\Address\Total            $total
     * @return $this
     *
     * @throws \LogicException
     */
    public function collect(
        \Magento\Quote\Model\Quote $quote,
        \Magento\Quote\Api\Data\ShippingAssignmentInterface $shippingAssignment,
        \Magento\Quote\Model\Quote\Address\Total $total
    ) {
        /**
         * @noinspection PhpUndefinedMethodInspection
         */
        $total->setBuckarooFee(0);
        /**
         * @noinspection PhpUndefinedMethodInspection
         */
        $total->setBaseBuckarooFee(0);

        if (!$shippingAssignment->getItems()) {
            return $this;
        }

        $paymentMethod = $quote->getPayment()->getMethod();
        if (!$paymentMethod || strpos($paymentMethod, 'tig_buckaroo_') !== 0) {
            return $this;
        }

        $methodInstance = $quote->getPayment()->getMethodInstance();
        if (!$methodInstance instanceof \TIG\Buckaroo\Model\Method\AbstractMethod) {
            return $this;
        }

        $basePaymentFeeOLD = $this->getBaseFee($methodInstance, $quote);
        $basePaymentFee = $total->getBaseBuckarooFeeInclTax() - $total->getBuckarooFeeBaseTaxAmount();

        if ($basePaymentFee < 0.01) {
            return $this;
        }

        $paymentFee = $this->priceCurrency->convert($basePaymentFee, $quote->getStore());

        /**
         * @noinspection PhpUndefinedMethodInspection
         */
        $quote->setBuckarooFee($paymentFee);
        /**
         * @noinspection PhpUndefinedMethodInspection
         */
        $quote->setBaseBuckarooFee($basePaymentFee);

        /**
         * @noinspection PhpUndefinedMethodInspection
         */
        $total->setBuckarooFee($paymentFee);
        /**
         * @noinspection PhpUndefinedMethodInspection
         */
        $total->setBaseBuckarooFee($basePaymentFee);

        /**
         * @noinspection PhpUndefinedMethodInspection
         */
        $total->setBaseGrandTotal($total->getBaseGrandTotal() + $basePaymentFee);
        /**
         * @noinspection PhpUndefinedMethodInspection
         */
        $total->setGrandTotal($total->getGrandTotal() + $paymentFee);

        return $this;
    }

    /**
     * Add buckaroo fee information to address
     *
     * @param  \Magento\Quote\Model\Quote               $quote
     * @param  \Magento\Quote\Model\Quote\Address\Total $total
     * @return $this
     */
    public function fetch(\Magento\Quote\Model\Quote $quote, \Magento\Quote\Model\Quote\Address\Total $total)
    {
        /**
         * @noinspection PhpUndefinedMethodInspection
         */
        $totals = [
            'code' => $this->getCode(),
            'title' => $this->getLabel(),
            'buckaroo_fee' => $total->getBuckarooFee(),
            'base_buckaroo_fee' => $total->getBaseBuckarooFee(),
            'buckaroo_fee_incl_tax' => $total->getBuckarooFeeInclTax(),
            'base_buckaroo_fee_incl_tax' => $total->getBaseBuckarooFeeInclTax(),
            'buckaroo_fee_tax_amount' => $total->getBuckarooFeeTaxAmount(),
            'buckaroo_fee_base_tax_amount' => $total->getBuckarooFeeBaseTaxAmount(),
        ];

        return $totals;
    }

    /**
     * @param \TIG\Buckaroo\Model\Method\AbstractMethod $methodInstance
     * @param \Magento\Quote\Model\Quote                $quote
     * @param bool                                      $inclTax
     *
     * @return bool|false|float
     * @throws \TIG\Buckaroo\Exception
     */
    public function getBaseFee(
        \TIG\Buckaroo\Model\Method\AbstractMethod $methodInstance,
        \Magento\Quote\Model\Quote $quote,
        $inclTax = false
    ) {
        $buckarooPaymentMethodCode = $methodInstance->buckarooPaymentMethodCode;
        if (!$this->configProviderMethodFactory->has($buckarooPaymentMethodCode)) {
            return false;
        }

        $configProvider = $this->configProviderMethodFactory->get($buckarooPaymentMethodCode);
        $basePaymentFee = trim($configProvider->getPaymentFee($quote->getStore()));

        if (is_numeric($basePaymentFee)) {
            if ($inclTax) {
                return $basePaymentFee;
            }
            /**
             * Payment fee is a number
             */
            return $this->getFeePrice($basePaymentFee);
        } elseif (strpos($basePaymentFee, '%') === false) {
            /**
             * Payment fee is invalid
             */
            return false;
        }

        /**
         * Payment fee is a percentage
         */
        $percentage = floatval($basePaymentFee);
        if ($quote->getShippingAddress()) {
            $address = $quote->getShippingAddress();
        } else {
            $address = $quote->getBillingAddress();
        }

        $total = 0;

        $feePercentageMode = $this->configProviderAccount->getFeePercentageMode($quote->getStore());

        switch ($feePercentageMode) {
            case 'subtotal':
                $total = $address->getBaseSubtotal();
                break;
            case 'subtotal_incl_tax':
                $total = $address->getBaseSubtotalTotalInclTax();
                break;
        }

        $basePaymentFee = ($percentage / 100) * $total;

        return $basePaymentFee;
    }

    /**
     * Get payment fee price with correct tax
     *
     * @param float                              $price
     * @param null                               $priceIncl
     *
     * @param \Magento\Framework\DataObject|null $pseudoProduct
     *
     * @return float
     * @throws \TIG\Buckaroo\Exception
     */
    public function getFeePrice($price, $priceIncl = null, \Magento\Framework\DataObject $pseudoProduct = null)
    {
        if ($pseudoProduct === null) {
            $pseudoProduct = new \Magento\Framework\DataObject();
        }

        $pseudoProduct->setTaxClassId($this->configProviderBuckarooFee->getTaxClass());

        /**
         * @noinspection PhpUndefinedMethodInspection
         */
        if ($priceIncl === null
            && $this->configProviderBuckarooFee->getPaymentFeeTax() == Calculation::DISPLAY_TYPE_INCLUDING_TAX
        ) {
            $priceIncl = true;
        } else {
            $priceIncl = false;
        }

        $price = $this->catalogHelper->getTaxPrice(
            $pseudoProduct,
            $price,
            false,
            null,
            null,
            null,
            null,
            $priceIncl
        );

        return $price;
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
