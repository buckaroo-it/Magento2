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

use Buckaroo\Magento2\Service\BuckarooFee\ResultFactory;
use Buckaroo\Magento2\Service\Tax\TaxCalculate;
use Buckaroo\Magento2\Service\BuckarooFee\Result;
use Magento\Quote\Api\Data\CartInterface;
use Magento\Quote\Model\Quote\Address\Total;

class FixedAmount
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
     * Calculate the Buckaroo fee for a fixed amount including tax.
     *
     * @param CartInterface $cart
     * @param float $amount
     * @return Result
     */
    public function calculate(CartInterface $cart, float $amount)
    {
        $tax = $this->taxCalculate->getTaxFromAmountIncludingTax($cart, $amount);
        /** @var Result $result */
        $result = $this->resultFactory->create();
        $result->setAmount($amount - $tax);
        $result->setTaxAmount($tax);

        return $result;
    }
}
