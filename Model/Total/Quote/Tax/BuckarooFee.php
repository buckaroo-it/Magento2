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

namespace Buckaroo\Magento2\Model\Total\Quote\Tax;

use Buckaroo\Magento2\Exception;
use Buckaroo\Magento2\Helper\PaymentGroupTransaction;
use Buckaroo\Magento2\Logging\Log;
use Buckaroo\Magento2\Model\ConfigProvider\Account as ConfigProviderAccount;
use Buckaroo\Magento2\Model\ConfigProvider\BuckarooFee as ConfigProviderBuckarooFee;
use Buckaroo\Magento2\Model\ConfigProvider\Method\Factory;
use Magento\Catalog\Helper\Data;
use Magento\Framework\Pricing\PriceCurrencyInterface;
use Magento\Quote\Api\Data\ShippingAssignmentInterface;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Address\Total;
use Magento\Tax\Model\Calculation as TaxModelCalculation;
use Magento\Tax\Model\Sales\Total\Quote\CommonTaxCollector;

/**
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class BuckarooFee extends \Buckaroo\Magento2\Model\Total\Quote\BuckarooFee
{
    public const QUOTE_TYPE = 'buckaroo_fee';
    public const CODE_QUOTE_GW = 'buckaroo_fee';

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
        parent::__construct(
            $configProviderAccount,
            $configProviderBuckarooFee,
            $configProviderMethodFactory,
            $priceCurrency,
            $catalogHelper,
            $groupTransaction,
            $logging,
            $taxCalculation
        );
        $this->setCode('pretax_buckaroo_fee');
    }

    /**
     * Collect buckaroo fee related items and add them to tax calculation
     *
     * @param Quote $quote
     * @param ShippingAssignmentInterface $shippingAssignment
     * @param Total $total
     * @return $this
     * @throws Exception
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function collect(
        Quote $quote,
        ShippingAssignmentInterface $shippingAssignment,
        Total $total
    ) {
        if (!$shippingAssignment->getItems()) {
            return $this;
        }

        $paymentMethod = $quote->getPayment()->getMethod();
        if (!$paymentMethod || strpos($paymentMethod, 'buckaroo_magento2_') !== 0) {
            return $this;
        }

        $methodInstance = $quote->getPayment()->getMethodInstance();
        if (!$methodInstance instanceof \Buckaroo\Magento2\Model\Method\BuckarooAdapter) {
            return $this;
        }

        $orderId = $quote->getReservedOrderId();

        if ($this->groupTransaction->getAlreadyPaid($orderId) > 0) {
            return $this;
        }

        $basePaymentFee = $this->getBaseFee($methodInstance, $quote, true);

        if ($basePaymentFee < 0.01) {
            return $this;
        }

        $paymentFee = $this->priceCurrency->convert($basePaymentFee, $quote->getStore());

        $productTaxClassId = $this->configProviderBuckarooFee->getTaxClass($quote->getStore());

        $address = $shippingAssignment->getShipping()->getAddress();
        /**
         * @noinspection PhpUndefinedMethodInspection
         */
        $associatedTaxables = $address->getAssociatedTaxables();
        if (!$associatedTaxables) {
            $associatedTaxables = [];
        }

        $associatedTaxables[] = [
            CommonTaxCollector::KEY_ASSOCIATED_TAXABLE_TYPE               => self::QUOTE_TYPE,
            CommonTaxCollector::KEY_ASSOCIATED_TAXABLE_CODE               => self::CODE_QUOTE_GW,
            CommonTaxCollector::KEY_ASSOCIATED_TAXABLE_UNIT_PRICE         => $paymentFee,
            CommonTaxCollector::KEY_ASSOCIATED_TAXABLE_BASE_UNIT_PRICE    => $basePaymentFee,
            CommonTaxCollector::KEY_ASSOCIATED_TAXABLE_QUANTITY           => 1,
            CommonTaxCollector::KEY_ASSOCIATED_TAXABLE_TAX_CLASS_ID       => $productTaxClassId,
            CommonTaxCollector::KEY_ASSOCIATED_TAXABLE_PRICE_INCLUDES_TAX => true,
            CommonTaxCollector::KEY_ASSOCIATED_TAXABLE_ASSOCIATION_ITEM_CODE
                => CommonTaxCollector::ASSOCIATION_ITEM_CODE_FOR_QUOTE
        ];

        /**
         * @noinspection PhpUndefinedMethodInspection
         */
        $address->setAssociatedTaxables($associatedTaxables);

        return $this;
    }
}
