<?php

namespace Buckaroo\Magento2\Test\Unit\Stubs;

/**
 * PHPUnit 12 replacement for MockBuilder::addMethods() on \Buckaroo\Magento2\Helper\Data.
 * Declares the magic methods tests need to configure on their doubles.
 *
 * @SuppressWarnings(PHPMD.UnusedFormalParameter)
 */
class DataStub extends \Buckaroo\Magento2\Helper\Data
{
    public function setRestoreQuoteLastOrder(...$args)
    {
        return $this;
    }
}
