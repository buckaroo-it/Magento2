<?php

namespace Buckaroo\Magento2\Test\Unit\Stubs;

/**
 * PHPUnit 12 replacement for MockBuilder::addMethods() on \Magento\Sales\Model\Order\Payment.
 * Declares the magic methods tests need to configure on their doubles.
 *
 * @SuppressWarnings(PHPMD.UnusedFormalParameter)
 */
class PaymentStub extends \Magento\Sales\Model\Order\Payment
{
    public function canProcessPostData(...$args)
    {
        return null;
    }

    public function createCreditNoteRequest(...$args)
    {
        return null;
    }

    public function processCustomPostData(...$args)
    {
        return null;
    }
}
