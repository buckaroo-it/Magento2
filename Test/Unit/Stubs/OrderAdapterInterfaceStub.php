<?php

namespace Buckaroo\Magento2\Test\Unit\Stubs;

/**
 * PHPUnit 12 replacement for MockBuilder::addMethods() on \Magento\Payment\Gateway\Data\OrderAdapterInterface.
 * Declares the magic methods tests need to configure on their doubles.
 */
interface OrderAdapterInterfaceStub extends \Magento\Payment\Gateway\Data\OrderAdapterInterface
{
    public function getOrder(...$args);
}
