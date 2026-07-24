<?php

namespace Buckaroo\Magento2\Test\Unit\Stubs;

/**
 * PHPUnit 12 replacement for MockBuilder::addMethods() on \Magento\Backend\Model\Session.
 * Declares the magic methods tests need to configure on their doubles.
 *
 * @SuppressWarnings(PHPMD.UnusedFormalParameter)
 */
class SessionStub extends \Magento\Backend\Model\Session
{
    public function getFormData(...$args)
    {
        return null;
    }
}
