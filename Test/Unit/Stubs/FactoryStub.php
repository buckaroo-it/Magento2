<?php

namespace Buckaroo\Magento2\Test\Unit\Stubs;

/**
 * PHPUnit 12 replacement for MockBuilder::addMethods() on \Buckaroo\Magento2\Model\ConfigProvider\Factory.
 * Declares the magic methods tests need to configure on their doubles.
 *
 * @SuppressWarnings(PHPMD.UnusedFormalParameter)
 */
class FactoryStub extends \Buckaroo\Magento2\Model\ConfigProvider\Factory
{
    public function getPaymentFee(...$args)
    {
        return null;
    }
}
