<?php

namespace Buckaroo\Magento2\Test\Unit\Stubs;

/**
 * PHPUnit 12 replacement for MockBuilder::addMethods() on \Magento\Framework\Event\Observer.
 * Declares the magic methods tests need to configure on their doubles.
 *
 * @SuppressWarnings(PHPMD.UnusedFormalParameter)
 */
class ObserverStub extends \Magento\Framework\Event\Observer
{
    public function getBaseBuckarooFee(...$args)
    {
        return null;
    }

    public function getBaseBuckarooFeeInclTax(...$args)
    {
        return null;
    }

    public function getBuckarooFee(...$args)
    {
        return null;
    }

    public function getBuckarooFeeBaseTaxAmount(...$args)
    {
        return null;
    }

    public function getBuckarooFeeInclTax(...$args)
    {
        return null;
    }

    public function getBuckarooFeeTaxAmount(...$args)
    {
        return null;
    }

    public function getInvoice(...$args)
    {
        return null;
    }

    public function getMethod(...$args)
    {
        return null;
    }

    public function getOrder(...$args)
    {
        return null;
    }

    public function getPayment(...$args)
    {
        return null;
    }

    public function getQuote(...$args)
    {
        return null;
    }

    public function getStore(...$args)
    {
        return null;
    }

    public function setStatus(...$args)
    {
        return $this;
    }
}
