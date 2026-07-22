<?php

namespace Buckaroo\Magento2\Test\Unit\Stubs;

/**
 * PHPUnit 12 replacement for MockBuilder::addMethods() on \Magento\Quote\Model\Quote.
 * Declares the magic methods tests need to configure on their doubles.
 */
class QuoteStub extends \Magento\Quote\Model\Quote
{
    public function getBaseBuckarooFee(...$args)
    {
        return null;
    }

    public function getBaseBuckarooFeeInclTax(...$args)
    {
        return null;
    }

    public function getBuckarooFee(...$args)
    {
        return null;
    }

    public function getBuckarooFeeBaseTaxAmount(...$args)
    {
        return null;
    }

    public function getBuckarooFeeInclTax(...$args)
    {
        return null;
    }

    public function getBuckarooFeeTaxAmount(...$args)
    {
        return null;
    }

    public function getCustomerEmail(...$args)
    {
        return null;
    }

    public function getCustomerFirstname(...$args)
    {
        return null;
    }

    public function getCustomerId(...$args)
    {
        return null;
    }

    public function getCustomerLastname(...$args)
    {
        return null;
    }

    public function getGiftCardsAmount(...$args)
    {
        return null;
    }

    public function getGrandTotal(...$args)
    {
        return null;
    }

    public function getQuoteCurrencyCode(...$args)
    {
        return null;
    }

    public function getRewardCurrencyAmount(...$args)
    {
        return null;
    }

    public function setBaseBuckarooFee(...$args)
    {
        return $this;
    }

    public function setBuckarooFee(...$args)
    {
        return $this;
    }

    public function setCustomerEmail(...$args)
    {
        return $this;
    }

    public function setCustomerFirstname(...$args)
    {
        return $this;
    }

    public function setCustomerId(...$args)
    {
        return $this;
    }

    public function setCustomerLastname(...$args)
    {
        return $this;
    }
}
