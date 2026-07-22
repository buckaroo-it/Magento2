<?php

namespace Buckaroo\Magento2\Test\Unit\Stubs;

/**
 * PHPUnit 12 replacement for MockBuilder::addMethods() on \Magento\Sales\Model\Order\Invoice\Item.
 * Declares the magic methods tests need to configure on their doubles.
 */
class InvoiceItemStub extends \Magento\Sales\Model\Order\Invoice\Item
{
    public function hasParentItemId(...$args)
    {
        return null;
    }

    public function getWeeeTaxAppliedAmount(...$args)
    {
        return null;
    }
}
