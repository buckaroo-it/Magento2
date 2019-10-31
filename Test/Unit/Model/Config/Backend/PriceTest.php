<?php
/**
 * NOTICE OF LICENSE
 *
 * This source file is subject to the MIT License
 * It is available through the world-wide-web at this URL:
 * https://tldrlegal.com/license/mit-license
 * If you are unable to obtain it through the world-wide-web, please send an email
 * to support@buckaroo.nl so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade this module to newer
 * versions in the future. If you wish to customize this module for your
 * needs please contact support@buckaroo.nl for more information.
 *
 * @copyright Copyright (c) Buckaroo B.V.
 * @license   https://tldrlegal.com/license/mit-license
 */
namespace TIG\Buckaroo\Test\Unit\Model\Config\Backend;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Model\ResourceModel\AbstractResource;
use TIG\Buckaroo\Model\Config\Backend\Price;

class PriceTest extends \TIG\Buckaroo\Test\BaseTest
{
    protected $instanceClass = Price::class;

    /**
     * Test what happens when a empty value is provided.
     */
    public function testEmptyValue()
    {
        $resourceMock = $this->getFakeMock(AbstractResource::class)->setMethods(['save'])->getMockForAbstractClass();
        $resourceMock->expects($this->once())->method('save');

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
        $resourceMock = $this->getFakeMock(AbstractResource::class)->setMethods(['save'])->getMockForAbstractClass();
        $resourceMock->expects($this->once())->method('save');

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
