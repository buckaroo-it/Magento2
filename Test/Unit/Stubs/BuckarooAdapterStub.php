<?php

namespace Buckaroo\Magento2\Test\Unit\Stubs;

/**
 * PHPUnit 12 replacement for MockBuilder::addMethods() on \Buckaroo\Magento2\Model\Method\BuckarooAdapter.
 * Declares the magic methods tests need to configure on their doubles.
 *
 * @SuppressWarnings(PHPMD.UnusedFormalParameter)
 */
class BuckarooAdapterStub extends \Buckaroo\Magento2\Model\Method\BuckarooAdapter
{
    public function getBuckarooPaymentMethodCode(...$args)
    {
        return null;
    }
}
