<?php

namespace Buckaroo\Magento2\Test\Unit\Stubs;

/**
 * PHPUnit 12 replacement for MockBuilder::addMethods() on \Magento\Checkout\Model\Session.
 * Declares the magic methods tests need to configure on their doubles.
 *
 * @SuppressWarnings(PHPMD.UnusedFormalParameter)
 */
class SessionStub2 extends \Magento\Checkout\Model\Session
{
    public function getLastOrderId(...$args)
    {
        return null;
    }

    public function getLastQuoteId(...$args)
    {
        return null;
    }

    public function getLastRealOrderId(...$args)
    {
        return null;
    }

    public function getLastSuccessQuoteId(...$args)
    {
        return null;
    }

    public function setLastOrderId(...$args)
    {
        return $this;
    }

    public function setLastOrderStatus(...$args)
    {
        return $this;
    }

    public function setLastQuoteId(...$args)
    {
        return $this;
    }

    public function setLastRealOrderId(...$args)
    {
        return $this;
    }

    public function setLastSuccessQuoteId(...$args)
    {
        return $this;
    }

    public function setRestoreQuoteLastOrder(...$args)
    {
        return $this;
    }

    public function unsLastOrderId(...$args)
    {
        return null;
    }

    public function unsLastQuoteId(...$args)
    {
        return null;
    }

    public function unsLastRealOrderId(...$args)
    {
        return null;
    }

    public function unsLastSuccessQuoteId(...$args)
    {
        return null;
    }

    public function unsRedirectUrl(...$args)
    {
        return null;
    }
}
