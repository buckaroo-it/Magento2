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
