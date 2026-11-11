<?php

namespace Buckaroo\Magento2\Test\Unit\Stubs;

/**
 * PHPUnit 12 replacement for MockBuilder::addMethods() on \Magento\Framework\Event.
 * Declares the magic accessors observers read off the event.
 *
 * @SuppressWarnings(PHPMD.UnusedFormalParameter)
 */
class EventStub extends \Magento\Framework\Event
{
    public function getShipment(...$args)
    {
        return null;
    }
}
