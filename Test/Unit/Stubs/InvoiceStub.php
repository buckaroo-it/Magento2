<?php

namespace Buckaroo\Magento2\Test\Unit\Stubs;

/**
 * PHPUnit 12 replacement for MockBuilder::addMethods() on \Magento\Sales\Model\Order\Invoice.
 * Declares the magic methods tests need to configure on their doubles.
 */
class InvoiceStub extends \Magento\Sales\Model\Order\Invoice
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
}
