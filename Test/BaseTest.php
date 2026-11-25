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

namespace Buckaroo\Magento2\Test;

use Magento\Framework\TestFramework\Unit\Helper\ObjectManager;
use PHPUnit\Framework\TestCase;

/**
 * @SuppressWarnings(PHPMD.NumberOfChildren)
 */
abstract class BaseTest extends TestCase
{
    protected const DEFAULT_PATH_PATTERN = 'payment/%s/%s';

    /**
     * @var null|string
     */
    protected $instanceClass;

    /**
     * @var \Magento\Framework\TestFramework\Unit\Helper\ObjectManager
     */
    protected $objectManagerHelper;

    protected $object;

    /**
     * @param array $args
     *
     * @return object
     */
    public function getInstance(array $args = [])
    {
        return $this->getObject($this->instanceClass, $args);
    }

    public static function setUpBeforeClass(): void
    {
        if (!defined('BP')) {
            define('BP', dirname(__DIR__, 5));
        }
        parent::setUpBeforeClass();
    }

    public function setUp(): void
    {
        ini_set('error_reporting', E_ALL);
        ini_set('display_errors', '1');
        ini_set('display_startup_errors', '1');

        // Initialize ObjectManager for unit tests if not already done
        $this->initializeObjectManagerForTests();

        $this->objectManagerHelper = new ObjectManager($this);
    }

    /**
     * Initialize ObjectManager for unit tests
     */
    private function initializeObjectManagerForTests(): void
    {
        if (!class_exists('\Magento\Framework\App\ObjectManager')) {
            return;
        }

        try {
            $currentInstance = \Magento\Framework\App\ObjectManager::getInstance();
            if ($currentInstance === null) {
                // Since we can't extend ObjectManager directly, we'll just skip setting it
                // Unit tests should properly mock their dependencies instead
            }
        } catch (\Throwable $e) {
            // If ObjectManager initialization fails, continue without it
            // Unit tests should properly mock dependencies instead
        }
    }

    /**
     * @param $method
     * @param $instance
     *
     * @return \ReflectionMethod
     */
    protected function getMethod($method, $instance)
    {
        $method = new \ReflectionMethod($instance, $method);

        return $method;
    }

    /**
     * @param      $method
     * @param object|null $instance
     *
     * @return mixed
     */
    protected function invoke($method, $instance = null)
    {
        if ($instance === null) {
            $instance = $this->getInstance();
        }

        $method = $this->getMethod($method, $instance);

        return $method->invoke($instance);
    }

    /**
     * @param       $method
     * @param array $args
     * @param object|null $instance
     *
     * @return mixed
     */
    protected function invokeArgs($method, $args = [], $instance = null)
    {
        if ($instance === null) {
            $instance = $this->getInstance();
        }

        $method = $this->getMethod($method, $instance);

        return $method->invokeArgs($instance, $args);
    }

    /**
     * @param      $property
     * @param object|null $instance
     *
     * @return \ReflectionProperty
     */
    protected function getProperty($property, $instance = null)
    {
        if ($instance === null) {
            $instance = $this->getInstance();
        }

        $reflection = new \ReflectionObject($instance);
        $property = $reflection->getProperty($property);
        return $property->getValue($instance);
    }

    /**
     * @param      $property
     * @param      $value
     * @param object|null $instance
     *
     * @return \ReflectionProperty
     */
    protected function setProperty($property, $value, $instance = null)
    {
        if ($instance === null) {
            $instance = $this->getInstance();
        }

        $reflection = new \ReflectionObject($instance);
        $property = $reflection->getProperty($property);
        $property->setValue($instance, $value);

        return $property;
    }

    /**
     * @param      $class
     * @param bool $return Immediate call getMock.
     *
     * @return \PHPUnit\Framework\MockObject\MockBuilder|\PHPUnit\Framework\MockObject\MockObject
     */
    protected function getFakeMock($class, $return = false)
    {
        $mock = $this->getMockBuilder($class);
        $mock->disableOriginalConstructor();

        if ($return) {
            return $mock->getMock();
        }

        return $mock;
    }

    /**
     * @param       $class
     * @param array $args
     *
     * @throws \Exception
     *
     * @return object
     */
    protected function getObject($class, $args = [])
    {
        if ($this->objectManagerHelper === null) {
            throw new \Exception('The object manager is not loaded. Dit you forget to call parent::setUp();?');
        }

        return $this->objectManagerHelper->getObject($class, $args);
    }

    /**
     * Quickly mock a function.
     *
     * @param                                          $function
     * @param                                          $response
     * @param \PHPUnit\Framework\MockObject\MockObject $instance
     * @param                                          $with
     */
    protected function mockFunction(
        \PHPUnit\Framework\MockObject\MockObject $instance,
        $function,
        $response,
        $with = []
    ) {
        $method = $instance->method($function);

        if ($with) {
            $method->with($with);
        }

        $method->willReturn($response);
    }

    /**
     * Return Full Path of Payment Method Config
     *
     * @param string $code
     * @param string $configPath
     *
     * @return string
     */
    public function getPaymentMethodConfigPath(string $code, string $configPath): string
    {
        return sprintf(self::DEFAULT_PATH_PATTERN, $code, $configPath);
    }
}
