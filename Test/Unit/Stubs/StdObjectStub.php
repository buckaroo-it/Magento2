<?php

namespace Buckaroo\Magento2\Test\Unit\Stubs;

/**
 * PHPUnit 12 replacement for MockBuilder::addMethods() on \stdClass.
 * Declares the magic methods tests need to configure on their doubles.
 */
class StdObjectStub
{
    public function createCreditNoteRequest(...$args)
    {
        return null;
    }

    public function getAmount(...$args)
    {
        return null;
    }

    public function getRoundedAmount(...$args)
    {
        return null;
    }

    public function setProductClassId(...$args)
    {
        return $this;
    }
}
