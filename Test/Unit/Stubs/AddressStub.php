<?php

namespace Buckaroo\Magento2\Test\Unit\Stubs;

/**
 * PHPUnit 12 replacement for MockBuilder::addMethods() on \Magento\Quote\Model\Quote\Address.
 * Declares the magic methods tests need to configure on their doubles.
 *
 * @SuppressWarnings(PHPMD.UnusedFormalParameter)
 */
class AddressStub extends \Magento\Quote\Model\Quote\Address
{
    public function getAddress(...$args)
    {
        return null;
    }

    public function importOrderAddress(...$args)
    {
        return null;
    }

    public function setCollectShippingRates(...$args)
    {
        return $this;
    }

    public function setShippingMethod(...$args)
    {
        return $this;
    }

    public function setShouldIgnoreValidation(...$args)
    {
        return $this;
    }
}
