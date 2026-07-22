<?php

namespace Buckaroo\Magento2\Test\Unit\Stubs;

/**
 * PHPUnit 12 replacement for MockBuilder::addMethods() on \Buckaroo\Magento2\Model\ConfigProvider\Account.
 * Declares the magic methods tests need to configure on their doubles.
 */
class AccountStub extends \Buckaroo\Magento2\Model\ConfigProvider\Account
{
    public function getMerchantGuid(...$args)
    {
        return null;
    }
}
