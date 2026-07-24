<?php

namespace Buckaroo\Magento2\Test\Unit\Stubs;

/**
 * PHPUnit 12 replacement for MockBuilder::addMethods() on \Magento\Framework\Model\ResourceModel\AbstractResource.
 * Declares the magic methods tests need to configure on their doubles.
 *
 * @SuppressWarnings(PHPMD.UnusedFormalParameter)
 */
class AbstractResourceStub extends \Magento\Framework\Model\ResourceModel\AbstractResource
{
    public function save(...$args)
    {
        return null;
    }

    protected function _construct()
    {
    }

    public function getConnection()
    {
        return null;
    }
}
