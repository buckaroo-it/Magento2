<?php
// phpcs:ignoreFile
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
namespace Buckaroo\Magento2\Test\Unit\Model\ConfigProvider\Method;

use Magento\Framework\ObjectManagerInterface;
use Buckaroo\Magento2\Exception;
use Magento\Checkout\Model\ConfigProviderInterface;
use Buckaroo\Magento2\Test\BaseTest;
use Buckaroo\Magento2\Model\ConfigProvider\Factory;

class FactoryTest extends BaseTest
{
    protected $instanceClass = Factory::class;

    /**
     * Test the happy path.
     */
    public function testGetHappyPath()
    {
        $model = 'model1';
        $providers = [['type' => 'model1', 'model' => 'model1']];

        $configProviderMock = $this->getFakeMock(ConfigProviderInterface::class)->getMockForAbstractClass();

        $objectManagerMock = $this->getFakeMock(ObjectManagerInterface::class)->onlyMethods(['get'])->getMockForAbstractClass();
        $objectManagerMock->method('get')->with($model)->willReturn($configProviderMock);

        $instance = $this->getInstance(['configProviders' => $providers, 'objectManager' => $objectManagerMock]);
        $result = $instance->get($model);

        $this->assertInstanceOf(ConfigProviderInterface::class, $result);
        $this->assertEquals($configProviderMock, $result);
    }

    /**
     * Test what happens when we provide a class that does not exists in the configProvider array
     */
    public function testGetInvalidClass()
    {
        $instance = $this->getInstance(['configProviders' => [['type' => 'some_model', 'model' => 'some_model']]]);

        try {
            $instance->get('invalid_type');
        } catch (Exception $e) {
            $this->assertEquals('Unknown ConfigProvider type requested: invalid_type.', $e->getMessage());
        }
    }

    /**
     * Test what happens if there is a wrong class in the ConfigProviders array.
     */
    public function testLogicException()
    {
        $model = 'buckaroo_magento2_testmethod'; // This will trigger $isPaymentMethod = true
        $providers = [['type' => 'testmethod', 'model' => 'InvalidTestClass']];

        // Mock an object that doesn't implement the expected BuckarooConfigProviderInterface
        $invalidConfigProvider = new \stdClass(); // This won't implement BuckarooConfigProviderInterface

        $objectManagerMock = $this->getFakeMock(ObjectManagerInterface::class)->onlyMethods(['get'])->getMockForAbstractClass();
        $objectManagerMock->method('get')->with('InvalidTestClass')->willReturn($invalidConfigProvider);

        $instance = $this->getInstance(['configProviders' => $providers, 'objectManager' => $objectManagerMock]);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('The ConfigProvider must implement "Buckaroo\Magento2\Model\ConfigProvider\Method\ConfigProviderInterface".');

        $instance->get($model);
    }

    /**
     * Test that we get an exception when there are no ConfigProviders.
     */
    public function testGetNoConfigProviders()
    {
        $instance = $this->getInstance();

        try {
            $instance->get('');
        } catch (\LogicException $e) {
            $this->assertEquals('ConfigProvider adapter is not set.', $e->getMessage());
        }
    }

    /**
     * Test the happy path for the has() method.
     */
    public function testHasHappyPath()
    {
        $providers = [['type' => 'model1', 'model' => 'model1']];

        $instance = $this->getInstance(['configProviders' => $providers]);
        $result = $instance->has('model1');

        $this->assertTrue($result);
    }

    /**
     * Test the not found path.
     */
    public function testHasValidProvider()
    {
        $providers = [['type' => 'model1', 'model' => 'model1']];

        $instance = $this->getInstance(['configProviders' => $providers]);
        $result = $instance->has('invalid_model');

        $this->assertFalse($result);
    }

    /**
     * Test what happens where there are no ConfigProviders
     */
    public function testHasNoProviders()
    {
        $instance = $this->getInstance();

        try {
            $instance->has('');
        } catch (\LogicException $e) {
            $this->assertEquals('ConfigProvider adapter is not set.', $e->getMessage());
        }
    }
}
