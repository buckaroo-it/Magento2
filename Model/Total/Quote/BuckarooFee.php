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

namespace Buckaroo\Magento2\Model\Total\Quote;

use Buckaroo\Magento2\Exception;
use Buckaroo\Magento2\Helper\PaymentGroupTransaction;
use Buckaroo\Magento2\Logging\Log;
use Buckaroo\Magento2\Model\Config\Source\TaxClass\Calculation;
use Buckaroo\Magento2\Model\ConfigProvider\Account as ConfigProviderAccount;
use Buckaroo\Magento2\Model\ConfigProvider\BuckarooFee as ConfigProviderBuckarooFee;
use Buckaroo\Magento2\Model\ConfigProvider\Method\Factory;
use Buckaroo\Magento2\Model\Method\BuckarooAdapter;
use Magento\Catalog\Helper\Data;
use Magento\Framework\DataObject;
use Magento\Framework\Phrase;
use Magento\Framework\Pricing\PriceCurrencyInterface;
use Magento\Payment\Model\MethodInterface;
use Magento\Quote\Api\Data\ShippingAssignmentInterface;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Address\Total;
use Magento\Quote\Model\Quote\Address\Total\AbstractTotal;
use Magento\Tax\Model\Calculation as TaxModelCalculation;

/**
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class BuckarooFee extends AbstractTotal
{
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
     * @var ConfigProviderAccount
     */
    protected $configProviderAccount;
    /**
     * @var ConfigProviderBuckarooFee
     */
    protected $configProviderBuckarooFee;
    /**
     * @var Factory
     */
    protected $configProviderMethodFactory;
    /**
     * @var Calculation
     */
    protected $taxCalculation;

    /**
     * @var Log $logging
     */
    protected $logging;

    /**
     * @param ConfigProviderAccount $configProviderAccount
     * @param ConfigProviderBuckarooFee $configProviderBuckarooFee
     * @param Factory $configProviderMethodFactory
     * @param PriceCurrencyInterface $priceCurrency
     * @param Data $catalogHelper
     * @param PaymentGroupTransaction $groupTransaction
     * @param Log $logging
     * @param TaxModelCalculation $taxCalculation
     */
    public function __construct(
        ConfigProviderAccount $configProviderAccount,
        ConfigProviderBuckarooFee $configProviderBuckarooFee,
        Factory $configProviderMethodFactory,
        PriceCurrencyInterface $priceCurrency,
        Data $catalogHelper,
        PaymentGroupTransaction $groupTransaction,
        Log $logging,
        TaxModelCalculation $taxCalculation
    ) {
        $this->setCode('buckaroo_fee');

        $this->configProviderAccount = $configProviderAccount;
        $this->configProviderBuckarooFee = $configProviderBuckarooFee;
        $this->configProviderMethodFactory = $configProviderMethodFactory;
        $this->priceCurrency = $priceCurrency;
        $this->catalogHelper = $catalogHelper;

        $this->groupTransaction = $groupTransaction;
        $this->logging = $logging;
        $this->taxCalculation = $taxCalculation;
    }

    /**
     * Collect grand total address amount
     *
     * @param Quote $quote
     * @param ShippingAssignmentInterface $shippingAssignment
     * @param Total $total
     * @return $this
     *
     * @throws \LogicException
     */
    public function collect(
        Quote $quote,
        ShippingAssignmentInterface $shippingAssignment,
        Total $total
    ) {

        /**
         * @noinspection PhpUndefinedMethodInspection
         */
        $total->setBuckarooFee(0);
        /**
         * @noinspection PhpUndefinedMethodInspection
         */
        $total->setBaseBuckarooFee(0);

        $orderId = $quote->getReservedOrderId();
        if ($this->groupTransaction->getAlreadyPaid($orderId) > 0) {
            return $this;
        }

        if (!$shippingAssignment->getItems()) {
            return $this;
        }

        $paymentMethod = $quote->getPayment()->getMethod();
        if (!$paymentMethod || strpos($paymentMethod, 'buckaroo_magento2_') !== 0) {
            return $this;
        }

        $methodInstance = $quote->getPayment()->getMethodInstance();
        if (!$methodInstance instanceof BuckarooAdapter) {
            return $this;
        }

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
     * @param Quote $quote
     * @param Total $total
     * @return array
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function fetch(Quote $quote, Total $total)
    {
        /**
         * @noinspection PhpUndefinedMethodInspection
         */
        return [
            'code'                         => $this->getCode(),
            'title'                        => $this->getLabel(),
            'buckaroo_fee'                 => $total->getBuckarooFee(),
            'base_buckaroo_fee'            => $total->getBaseBuckarooFee(),
            'buckaroo_fee_incl_tax'        => $total->getBuckarooFeeInclTax(),
            'base_buckaroo_fee_incl_tax'   => $total->getBaseBuckarooFeeInclTax(),
            'buckaroo_fee_tax_amount'      => $total->getBuckarooFeeTaxAmount(),
            'buckaroo_fee_base_tax_amount' => $total->getBuckarooFeeBaseTaxAmount(),
        ];
    }

    /**
     * Get Buckaroo label
     *
     * @return Phrase
     */
    public function getLabel(): Phrase
    {
        return __('Payment Fee');
    }

    /**
     * Get base Buckaroo fee
     *
     * @param BuckarooAdapter $methodInstance
     * @param Quote $quote
     * @param bool $inclTax
     * @return bool|false|float
     * @throws Exception
     *
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     */
    public function getBaseFee(
        MethodInterface $methodInstance,
        Quote $quote,
        bool $inclTax = false
    ) {
        $buckarooPaymentMethodCode = $methodInstance->buckarooPaymentMethodCode;
        if (!$this->configProviderMethodFactory->has($buckarooPaymentMethodCode)) {
            return false;
        }

        $configProvider = $this->configProviderMethodFactory->get($buckarooPaymentMethodCode);
        $basePaymentFee = trim((string)$configProvider->getPaymentFee($quote->getStore()));

        if (is_numeric($basePaymentFee)) {
            if (in_array($buckarooPaymentMethodCode, ['billink', 'afterpay20', 'afterpay', 'paypal'])) {
                $inclTax = $this->configProviderBuckarooFee->getPaymentFeeTax() ==
                    Calculation::DISPLAY_TYPE_INCLUDING_TAX;

                if ($inclTax) {
                    $request = $this->taxCalculation->getRateRequest(null, null, null, $quote->getStore());
                    $taxClassId = $this->configProviderBuckarooFee->getTaxClass($quote->getStore());
                    $percent = $this->taxCalculation->getRate($request->setProductClassId($taxClassId));
                    if ($percent > 0) {
                        return $basePaymentFee / (1 + ($percent / 100));
                    }
                }
                return $basePaymentFee;
            } else {
                if ($inclTax) {
                    return $basePaymentFee;
                }
                /**
                 * Payment fee is a number
                 */
                return $this->getFeePrice($basePaymentFee);
            }
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

        if ($feePercentageMode === 'subtotal') {
            $total = $address->getBaseSubtotal();
        }

        if ($feePercentageMode === 'subtotal_incl_tax') {
            $total = $address->getBaseSubtotalTotalInclTax();
        }

        return ($percentage / 100) * $total;
    }

    /**
     * Get payment fee price with correct tax
     *
     * @param float $price
     * @param float|null $priceIncl
     * @param DataObject|null $pseudoProduct
     * @return float
     * @throws Exception
     */
    public function getFeePrice($price, $priceIncl = null, DataObject $pseudoProduct = null)
    {
        if ($pseudoProduct === null) {
            $pseudoProduct = new DataObject();
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

        return $this->catalogHelper->getTaxPrice(
            $pseudoProduct,
            $price,
            false,
            null,
            null,
            null,
            null,
            $priceIncl
        );
    }
}
