<?php

namespace Buckaroo\Magento2\Test\Unit\Stubs;

/**
 * PHPUnit 12 replacement for MockBuilder::addMethods() on \Magento\Quote\Model\Quote\Item.
 * Declares the magic methods tests need to configure on their doubles.
 *
 * @SuppressWarnings(PHPMD.UnusedFormalParameter)
 */
class QuoteItemStub extends \Magento\Quote\Model\Quote\Item
{
    public function getDiscountAmount(...$args)
    {
        return null;
    }

    public function getDiscountTaxCompensationAmount(...$args)
    {
        return null;
    }

    public function getRowTotalInclTax(...$args)
    {
        return null;
    }

    public function getPriceInclTax(...$args)
    {
        return null;
    }

    public function getWeeeTaxAppliedAmount(...$args)
    {
        return null;
    }

    public function getTaxPercent(...$args)
    {
        return null;
    }

    public function hasParentItemId(...$args)
    {
        return false;
    }

    public function getTotalQty(...$args)
    {
        return null;
    }

    public function getParentItemId(...$args)
    {
        return null;
    }
}
