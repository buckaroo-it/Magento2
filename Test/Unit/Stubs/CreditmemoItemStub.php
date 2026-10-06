<?php

namespace Buckaroo\Magento2\Test\Unit\Stubs;

/**
 * PHPUnit 12 replacement for MockBuilder::addMethods() on \Magento\Sales\Model\Order\Creditmemo\Item.
 * Declares the magic methods tests need to configure on their doubles.
 *
 * @SuppressWarnings(PHPMD.UnusedFormalParameter)
 */
class CreditmemoItemStub extends \Magento\Sales\Model\Order\Creditmemo\Item
{
    public function hasParentItemId(...$args)
    {
        return null;
    }
}
