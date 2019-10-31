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

namespace TIG\Buckaroo\Model\Total\Quote\Tax;

use Magento\Catalog\Helper\Data;
use Magento\Framework\Pricing\PriceCurrencyInterface;
use Magento\Tax\Model\Sales\Total\Quote\CommonTaxCollector;
use TIG\Buckaroo\Model\ConfigProvider\Account as ConfigProviderAccount;
use TIG\Buckaroo\Model\ConfigProvider\BuckarooFee as ConfigProviderBuckarooFee;
use TIG\Buckaroo\Model\ConfigProvider\Method\Factory;

class BuckarooFee extends \TIG\Buckaroo\Model\Total\Quote\BuckarooFee
{
    const QUOTE_TYPE = 'buckaroo_fee';
    const CODE_QUOTE_GW = 'buckaroo_fee';

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
        parent::__construct(
            $configProviderAccount,
            $configProviderBuckarooFee,
            $configProviderMethodFactory,
            $priceCurrency,
            $catalogHelper
        );

        $this->setCode('pretax_buckaroo_fee');
    }

    /**
     * Collect buckaroo fee related items and add them to tax calculation
     *
     * @param  \Magento\Quote\Model\Quote                          $quote
     * @param  \Magento\Quote\Api\Data\ShippingAssignmentInterface $shippingAssignment
     * @param  \Magento\Quote\Model\Quote\Address\Total            $total
     * @return $this
     */
    public function collect(
        \Magento\Quote\Model\Quote $quote,
        \Magento\Quote\Api\Data\ShippingAssignmentInterface $shippingAssignment,
        \Magento\Quote\Model\Quote\Address\Total $total
    ) {
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
            CommonTaxCollector::KEY_ASSOCIATED_TAXABLE_TYPE => self::QUOTE_TYPE,
            CommonTaxCollector::KEY_ASSOCIATED_TAXABLE_CODE => self::CODE_QUOTE_GW,
            CommonTaxCollector::KEY_ASSOCIATED_TAXABLE_UNIT_PRICE => $paymentFee,
            CommonTaxCollector::KEY_ASSOCIATED_TAXABLE_BASE_UNIT_PRICE => $basePaymentFee,
            CommonTaxCollector::KEY_ASSOCIATED_TAXABLE_QUANTITY => 1,
            CommonTaxCollector::KEY_ASSOCIATED_TAXABLE_TAX_CLASS_ID => $productTaxClassId,
            CommonTaxCollector::KEY_ASSOCIATED_TAXABLE_PRICE_INCLUDES_TAX => true,
            CommonTaxCollector::KEY_ASSOCIATED_TAXABLE_ASSOCIATION_ITEM_CODE
            => CommonTaxCollector::ASSOCIATION_ITEM_CODE_FOR_QUOTE,
        ];

        /**
         * @noinspection PhpUndefinedMethodInspection
         */
        $address->setAssociatedTaxables($associatedTaxables);

        return $this;
    }
}
