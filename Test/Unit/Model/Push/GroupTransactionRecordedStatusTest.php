<?php

/**
 * Buckaroo Magento 2 payment module (https://www.buckaroo.eu/)
 *
 * Copyright (c) Buckaroo B.V.
 * See LICENSE for license details.
 *
 * Support: support@buckaroo.nl
 *
 * @copyright Copyright (c) Buckaroo B.V.
 * @license   MIT
 */
declare(strict_types=1);

namespace Buckaroo\Magento2\Test\Unit\Model\Push;

use Buckaroo\Magento2\Model\Push\DefaultProcessor;
use Buckaroo\Magento2\Model\Push\GroupTransactionPushProcessor;
use PHPUnit\Framework\TestCase;

/**
 * A group transaction push is applied by the inner default processor; Model\Push must still find the status it
 * recorded, so a failed push can be retried.
 */
class GroupTransactionRecordedStatusTest extends TestCase
{
    private function processor(bool $delegated): GroupTransactionPushProcessor
    {
        $default = $this->createMock(DefaultProcessor::class);
        $default->method('getRecordedTransactionStatus')->willReturn(['transaction_key' => 'KEY1', 'payment_id' => 7]);

        $reflection = new \ReflectionClass(GroupTransactionPushProcessor::class);
        $processor = $reflection->newInstanceWithoutConstructor();
        foreach (['defaultProcessor' => $default, 'delegatedToDefaultProcessor' => $delegated] as $name => $value) {
            $property = $reflection->getProperty($name);
            $property->setAccessible(true);
            $property->setValue($processor, $value);
        }

        return $processor;
    }

    public function testTheStatusRecordedByTheDefaultProcessorIsForwarded(): void
    {
        $this->assertSame(
            ['transaction_key' => 'KEY1', 'payment_id' => 7],
            $this->processor(true)->getRecordedTransactionStatus()
        );
    }

    public function testNothingIsForwardedWhenThePushWasNotHandedOn(): void
    {
        $this->assertNull($this->processor(false)->getRecordedTransactionStatus());
    }
}
