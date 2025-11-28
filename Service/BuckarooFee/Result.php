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
     * @return float
     */
    public function getAmount()
    {
        return $this->amount;
    }

    /**
     * @return float
     */
    public function getRoundedAmount()
    {
        return round($this->amount, 2);
    }

    /**
     * @param float $amount
     */
    public function setAmount($amount)
    {
        $this->amount = $amount;
    }

    /**
     * @return float
     */
    public function getTaxAmount()
    {
        return $this->taxAmount;
    }

    /**
     * @return float
     */
    public function getRoundedTaxAmount()
    {
        return round($this->taxAmount, 2);
    }

    /**
     * @param float $taxAmount
     */
    public function setTaxAmount(float $taxAmount)
    {
        $this->taxAmount = $taxAmount;
    }

    /**
     * @return float
     */
    public function getAmountIncludingTax()
    {
        return $this->amount + $this->taxAmount;
    }
}
