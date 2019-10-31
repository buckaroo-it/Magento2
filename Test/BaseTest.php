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
namespace TIG\Buckaroo\Test;

use Magento\Framework\TestFramework\Unit\Helper\ObjectManager;

class BaseTest extends TestCaseFinder
{
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

    public function setUp()
    {
        /**
         * Require functions.php to be able to use the translate function
         */
        if (strpos(__DIR__, 'vendor') === false) {
            include_once __DIR__ . '/../../../../functions.php';
        } else {
            include_once __DIR__ . '/../../../../app/functions.php';
        }

        ini_set('error_reporting', E_ALL);
        ini_set('display_errors', '1');
        ini_set('display_startup_errors', '1');

        $this->objectManagerHelper = new ObjectManager($this);
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
        $method->setAccessible(true);

        return $method;
    }

    /**
     * @param      $method
     * @param null $instance
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
     * @param null  $instance
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
     * @param null $instance
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
        $property->setAccessible(true);
        return $property->getValue($instance);
    }

    /**
     * @param      $property
     * @param      $value
     * @param null $instance
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
        $property->setAccessible(true);
        $property->setValue($instance, $value);

        return $property;
    }

    /**
     * @param      $class
     * @param bool $return Immediate call getMock.
     *
     * @return \PHPUnit_Framework_MockObject_MockBuilder|\PHPUnit_Framework_MockObject_MockObject
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
     * @return object
     * @throws \Exception
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
     * @param \PHPUnit_Framework_MockObject_MockObject $instance
     * @param                                          $with
     */
    protected function mockFunction(
        \PHPUnit_Framework_MockObject_MockObject $instance,
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
     * Test the assignData method. In its root it is the same for every payment method.
     *
     * @param $fixture
     *
     * @return $this
     */
    public function assignDataTest($fixture)
    {
        if (!array_key_exists('buckaroo_skip_validation', $fixture)) {
            $fixture['buckaroo_skip_validation'] = false;
        }

        $data = $this->getFakeMock(\Magento\Framework\DataObject::class)->getMock();
        $infoInterface = $this->getFakeMock(\Magento\Payment\Model\InfoInterface::class)->getMockForAbstractClass();

        foreach ($fixture as $key => $value) {
            $camelCase = preg_replace_callback(
                "/(?:^|_)([a-z])/",
                function ($matches) {
                    return strtoupper($matches[1]);
                },
                $key
            );

            $data->shouldReceive('get' . $camelCase)->andReturn($value);
            $infoInterface->shouldReceive('setAdditionalInformation')->with($key, $value);
        }

        /**
         * @noinspection PhpUndefinedMethodInspection
         */
        $this->object->setData('info_instance', $infoInterface);
        /**
         * @noinspection PhpUndefinedMethodInspection
         */
        $this->assertEquals($this->object, $this->object->assignData($data));

        return $this;
    }

    public function getPartialObject($object, $arguments = array(), $mockMethods = array())
    {
        $constructorArgs = $this->objectManagerHelper->getConstructArguments($object, $arguments);

        $mock = $this->getMock($object, $mockMethods, $constructorArgs);

        return $mock;
    }
}
