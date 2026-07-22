<?php

namespace Buckaroo\Magento2\Test\Unit\Stubs;

/**
 * PHPUnit 12 replacement for MockBuilder::addMethods() on \Magento\Sales\Model\Order\Creditmemo.
 * Declares the magic methods tests need to configure on their doubles.
 */
class CreditmemoStub extends \Magento\Sales\Model\Order\Creditmemo
{
    public function getBaseBuckarooFee(...$args)
    {
        return null;
    }

    public function getBuckarooFee(...$args)
    {
        return null;
    }

    public function setBaseBuckarooFee(...$args)
    {
        return $this;
    }

    public function setBuckarooFee(...$args)
    {
        return $this;
    }
}
