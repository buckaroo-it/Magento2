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
use Buckaroo\Magento2\Model\Config\Backend\Number;

class NumberTest extends \Buckaroo\Magento2\Test\BaseTest
{
    protected $instanceClass = Number::class;

    /**
     * @return array
     */
    public static function acceptedValueProvider(): array
    {
        return [
            'empty string' => [''],
            'null'         => [null],
            'zero string'  => ['0'],
            'digits'       => ['10'],
        ];
    }

    /**
     * Empty values and digit strings are passed to the resource unchanged.
     *
     *
     * @param mixed $value
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
     * @return array
     */
    public static function invalidValueProvider(): array
    {
        return [
            'text'     => ['invalid value'],
            'negative' => ['-5'],
            'decimal'  => ['10.5'],
        ];
    }

    /**
     * Values that are not a non-negative whole number are rejected and never reach the resource.
     *
     *
     * @param string $value
     */
    #[DataProvider('invalidValueProvider')]
    public function testInvalidValue($value)
    {
        $resourceMock = $this->getFakeMock(\Buckaroo\Magento2\Test\Unit\Stubs\AbstractResourceStub::class)
            ->onlyMethods(['save'])->getMock();
        $resourceMock->expects($this->never())->method('save');

        $instance = $this->getInstance(['resource' => $resourceMock]);
        $instance->setValue($value);

        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage("Please enter a valid number: '" . $value . "'.");

        $instance->save();
    }
}
