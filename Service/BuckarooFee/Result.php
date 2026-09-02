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

namespace Buckaroo\Magento2\Service\BuckarooFee;

class Result
{
    /**
     * @var float
     */
    private $amount = 0;

    /**
     * @var float
     */
    private $taxAmount = 0;

    /**
     * Get the Buckaroo fee amount.
     *
     * @return float
     */
    public function getAmount()
    {
        return $this->amount;
    }

    /**
     * Get the Buckaroo fee amount rounded to two decimals.
     *
     * @return float
     */
    public function getRoundedAmount()
    {
        return round($this->amount, 2);
    }

    /**
     * Set the Buckaroo fee amount.
     *
     * @param float $amount
     */
    public function setAmount($amount)
    {
        $this->amount = $amount;
    }

    /**
     * Get the Buckaroo fee tax amount.
     *
     * @return float
     */
    public function getTaxAmount()
    {
        return $this->taxAmount;
    }

    /**
     * Get the Buckaroo fee tax amount rounded to two decimals.
     *
     * @return float
     */
    public function getRoundedTaxAmount()
    {
        return round($this->taxAmount, 2);
    }

    /**
     * Set the Buckaroo fee tax amount.
     *
     * @param float $taxAmount
     */
    public function setTaxAmount(float $taxAmount)
    {
        $this->taxAmount = $taxAmount;
    }

    /**
     * Get the Buckaroo fee amount including tax.
     *
     * @return float
     */
    public function getAmountIncludingTax()
    {
        return $this->amount + $this->taxAmount;
    }
}
