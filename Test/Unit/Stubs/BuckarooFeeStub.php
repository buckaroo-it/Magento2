<?php

namespace Buckaroo\Magento2\Test\Unit\Stubs;

/**
 * PHPUnit 12 replacement for MockBuilder::addMethods() on \Buckaroo\Magento2\Model\ConfigProvider\BuckarooFee.
 * Declares the magic methods tests need to configure on their doubles.
 */
class BuckarooFeeStub extends \Buckaroo\Magento2\Model\ConfigProvider\BuckarooFee
{
    public function getPaymentFeeTax(...$args)
    {
        return null;
    }

    public function getTaxClass(...$args)
    {
        return null;
    }
}
