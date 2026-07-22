<?php

namespace Buckaroo\Magento2\Test\Unit\Stubs;

/**
 * PHPUnit 12 replacement for MockBuilder::addMethods() on \Magento\Framework\App\RequestInterface.
 * Declares the magic methods tests need to configure on their doubles.
 */
interface RequestInterfaceStub extends \Magento\Framework\App\RequestInterface
{
    public function getFullActionName(...$args);
}
