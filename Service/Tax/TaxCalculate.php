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

namespace Buckaroo\Magento2\Service\Tax;

use Buckaroo\Magento2\Model\ConfigProvider\BuckarooFee as BuckarooFeeConfigProvider;
use Magento\Quote\Api\Data\CartInterface;
use Magento\Tax\Model\Calculation;

class TaxCalculate
{
    /**
     * @var Calculation
     */
    private $taxCalculation;

    /**
     * @var BuckarooFeeConfigProvider
     */
    protected $configProviderBuckarooFee;

    /**
     * Constructor
     *
     * @param Calculation               $taxCalculation
     * @param BuckarooFeeConfigProvider $configProviderBuckarooFee
     */
    public function __construct(
        Calculation $taxCalculation,
        BuckarooFeeConfigProvider $configProviderBuckarooFee
    ) {
        $this->taxCalculation = $taxCalculation;
        $this->configProviderBuckarooFee = $configProviderBuckarooFee;
    }

    /**
     * Calculate tax amount from an amount that includes tax.
     *
     * @param  CartInterface $cart
     * @param  float         $amount Amount including tax
     * @return float         Tax amount
     */
    public function getTaxFromAmountIncludingTax(CartInterface $cart, float $amount): float
    {
        $shippingAddress = $cart->getShippingAddress();
        $billingAddress = $cart->getBillingAddress();
        $customerTaxClassId = $cart->getCustomerTaxClassId();
        $storeId = $cart->getStoreId();

        $taxClassId = $this->configProviderBuckarooFee->getBuckarooFeeTaxClass($cart->getStore());

        if (empty($taxClassId) || !is_numeric($taxClassId)) {
            return 0.0;
        }

        $request = $this->taxCalculation->getRateRequest(
            $shippingAddress,
            $billingAddress,
            $customerTaxClassId,
            $storeId
        );

        $request->setProductClassId($taxClassId);

        $rate = $this->taxCalculation->getRate($request);

        return $this->taxCalculation->calcTaxAmount(
            $amount,
            $rate,
            true,
            false
        );
    }
}
