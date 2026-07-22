<?php

namespace Buckaroo\Magento2\Test\Unit\Stubs;

/**
 * PHPUnit 12 replacement for MockBuilder::addMethods() on \Magento\Quote\Model\Quote\Address\Total.
 * Declares the magic methods tests need to configure on their doubles.
 */
class TotalStub extends \Magento\Quote\Model\Quote\Address\Total
{
    public function getBaseBuckarooFee(...$args)
    {
        return null;
    }

    public function getBaseBuckarooFeeInclTax(...$args)
    {
        return null;
    }

    public function getBaseGrandTotal(...$args)
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

    public function getGrandTotal(...$args)
    {
        return null;
    }

    public function setBaseBuckarooFee(...$args)
    {
        return $this;
    }

    public function setBaseGrandTotal(...$args)
    {
        return $this;
    }

    public function setBuckarooFee(...$args)
    {
        return $this;
    }

    public function setGrandTotal(...$args)
    {
        return $this;
    }
}
