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
use PHPUnit\Framework\Attributes\DataProvider;
use Buckaroo\Magento2\Model\Config\Backend\Price;

class PriceTest extends \Buckaroo\Magento2\Test\BaseTest
{
    protected $instanceClass = Price::class;

    /**
     * @return array
     */
    public static function acceptedValueProvider(): array
    {
        return [
            'empty string' => [''],
            'null'         => [null],
            'integer string' => ['10'],
            'decimal float' => [10.42],
            'decimal string' => ['10.42'],
        ];
    }

    /**
     * Empty and numeric values are passed to the resource unchanged.
     *
     *
     * @param mixed $value
     *
     * @throws LocalizedException
     */
    #[DataProvider('acceptedValueProvider')]
    public function testAcceptedValueIsSavedUnchanged($value)
    {
        $resourceMock = $this->getFakeMock(\Buckaroo\Magento2\Test\Unit\Stubs\AbstractResourceStub::class)
            ->onlyMethods(['save'])->getMock();

        $instance = $this->getInstance(['resource' => $resourceMock]);
        $instance->setValue($value);

        $resourceMock->expects($this->once())->method('save')->with($this->identicalTo($instance));

        $result = $instance->save();
        $this->assertSame($instance, $result);
        $this->assertSame($value, $instance->getValue());
    }

    /**
     * A non numeric value is rejected and never reaches the resource.
     */
    public function testInvalidValue()
    {
        $resourceMock = $this->getFakeMock(\Buckaroo\Magento2\Test\Unit\Stubs\AbstractResourceStub::class)
            ->onlyMethods(['save'])->getMock();
        $resourceMock->expects($this->never())->method('save');

        $instance = $this->getInstance(['resource' => $resourceMock]);
        $instance->setValue("invalid value");

        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage("Please enter a valid number: 'invalid value'.");

        $instance->save();
    }
}
