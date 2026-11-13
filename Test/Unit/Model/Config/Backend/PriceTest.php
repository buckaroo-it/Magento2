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

namespace Buckaroo\Magento2\Test\Unit\Model\Config\Backend;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Model\ResourceModel\AbstractResource;
use Buckaroo\Magento2\Model\Config\Backend\Price;

class PriceTest extends \Buckaroo\Magento2\Test\BaseTest
{
    protected $instanceClass = Price::class;

    /**
     * Test what happens when a empty value is provided.
     */
    public function testEmptyValue()
    {
        $resourceMock = $this->getFakeMock(\Buckaroo\Magento2\Test\Unit\Stubs\AbstractResourceStub::class)
            ->onlyMethods(['save'])->getMock();
        $resourceMock->method('save');

        $instance = $this->getInstance(['resource' => $resourceMock]);
        $instance->setValue("10");

        $result = $instance->save();
        $this->assertInstanceOf(Price::class, $result);
    }

    /**
     * Test what happens when there is a valid value is provided.
     *
     * @throws LocalizedException
     */
    public function testValidValue()
    {
        $resourceMock = $this->getFakeMock(\Buckaroo\Magento2\Test\Unit\Stubs\AbstractResourceStub::class)
            ->onlyMethods(['save'])->getMock();
        $resourceMock->method('save');

        $instance = $this->getInstance(['resource' => $resourceMock]);
        $instance->setValue(10.42);

        $result = $instance->save();
        $this->assertInstanceOf(Price::class, $result);
    }

    /**
     * Test what happens when an invalid value is provided.
     */
    public function testInvalidValue()
    {
        $instance = $this->getInstance();
        $instance->setValue("invalid value");

        try {
            $instance->save();
        } catch (LocalizedException $e) {
            $this->assertEquals("Please enter a valid number: 'invalid value'.", $e->getMessage());
        }
    }
}
