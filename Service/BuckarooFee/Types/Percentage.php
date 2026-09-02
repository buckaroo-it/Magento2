<?php
/**
 * Buckaroo Magento 2 payment module (https://www.buckaroo.eu/)
 *
 * Copyright (c) Buckaroo B.V.
 * See LICENSE for license details.
 *
 * Support: support@buckaroo.nl
 *
 * @copyright Copyright (c) Buckaroo B.V.
 * @license   MIT
 */

namespace Buckaroo\Magento2\Service\BuckarooFee\Types;

use Buckaroo\Magento2\Service\BuckarooFee\Result;
use Buckaroo\Magento2\Service\BuckarooFee\ResultFactory;
use Buckaroo\Magento2\Service\Tax\TaxCalculate;
use Magento\Quote\Api\Data\CartInterface;
use Magento\Quote\Model\Quote\Address\Total;

class Percentage
{
    /**
     * @var ResultFactory
     */
    private $resultFactory;
    /**
     * @var TaxCalculate
     */
    private $taxCalculate;

    /**
     * @param ResultFactory $resultFactory
     * @param TaxCalculate  $taxCalculate
     */
    public function __construct(ResultFactory $resultFactory, TaxCalculate $taxCalculate)
    {
        $this->resultFactory = $resultFactory;
        $this->taxCalculate = $taxCalculate;
    }

    /**
     * Calculate the Buckaroo fee as a percentage of the cart subtotal.
     *
     * @param CartInterface $cart
     * @param Total $total
     * @param string|float $percentage
     * @return Result|null
     */
    public function calculate(CartInterface $cart, Total $total, $percentage)
    {
        $percentage = (float) rtrim($percentage, '%');
        if ($percentage <= 0) {
            return null;
        }
        $subtotal = $total->getData('base_subtotal_incl_tax');
        if (!$subtotal) {
            $subtotal = $total->getTotalAmount('subtotal');
        }
        if ($subtotal <= 0) {
            return null;
        }

        $calculatedResult = ($subtotal / 100) * $percentage;
        $tax = $this->taxCalculate->getTaxFromAmountIncludingTax($cart, $calculatedResult);

        /** @var Result $result */
        $result = $this->resultFactory->create();
        $result->setAmount($calculatedResult - $tax);
        $result->setTaxAmount($tax);

        return $result;
    }
}
