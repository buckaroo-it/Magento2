<?php
/**
 *                  ___________       __            __
 *                  \__    ___/____ _/  |_ _____   |  |
 *                    |    |  /  _ \\   __\\__  \  |  |
 *                    |    | |  |_| ||  |   / __ \_|  |__
 *                    |____|  \____/ |__|  (____  /|____/
 *                                              \/
 *          ___          __                                   __
 *         |   |  ____ _/  |_   ____ _______   ____    ____ _/  |_
 *         |   | /    \\   __\_/ __ \\_  __ \ /    \ _/ __ \\   __\
 *         |   ||   |  \|  |  \  ___/ |  | \/|   |  \\  ___/ |  |
 *         |___||___|  /|__|   \_____>|__|   |___|  / \_____>|__|
 *                  \/                           \/
 *                  ________
 *                 /  _____/_______   ____   __ __ ______
 *                /   \  ___\_  __ \ /  _ \ |  |  \\____ \
 *                \    \_\  \|  | \/|  |_| ||  |  /|  |_| |
 *                 \______  /|__|    \____/ |____/ |   __/
 *                        \/                       |__|
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Creative Commons License.
 * It is available through the world-wide-web at this URL:
 * http://creativecommons.org/licenses/by-nc-nd/3.0/nl/deed.en_US
 * If you are unable to obtain it through the world-wide-web, please send an email
 * to servicedesk@tig.nl so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade this module to newer
 * versions in the future. If you wish to customize this module for your
 * needs please contact servicedesk@tig.nl for more information.
 *
 * @copyright Copyright (c) Total Internet Group B.V. https://tig.nl/copyright
 * @license   http://creativecommons.org/licenses/by-nc-nd/3.0/nl/deed.en_US
 */
namespace TIG\Buckaroo\Test\Unit\Model\ConfigProvider\Method;

use Mockery as m;
use TIG\Buckaroo\Test\BaseTest;
use TIG\Buckaroo\Model\ConfigProvider\Method\Factory;

class FactoryTest extends BaseTest
{
    /**
     * @var Factory
     */
    protected $object;

    /**
     * @var m\MockInterface
     */
    protected $objectManagerInterface;

    /**
     * @var array
     */
    protected $configProviders = [];

    /**
     * Setup the default mock object.
     */
    public function setUp()
    {
        parent::setUp();

        $this->configProviders = [
            ['type' => 'model1', 'model' => 'model1'],
            ['type' => 'model2', 'model' => 'model2'],
        ];

        $this->objectManagerInterface = m::mock('\Magento\Framework\ObjectManagerInterface');
        $this->object = $this->objectManagerHelper->getObject(
            Factory::class,
            [
            'objectManager' => $this->objectManagerInterface,
            'configProviders' => $this->configProviders,
            ]
        );
    }

    /**
     * Test the happy path.
     *
     * @throws \TIG\Buckaroo\Exception
     */
    public function testGetHappyPath()
    {
        $model = 'model1';
        $mockObject = m::mock('\TIG\Buckaroo\Model\ConfigProvider\Method\ConfigProviderInterface');
        $this->objectManagerInterface->shouldReceive('get')->with($model)->andReturn($mockObject);

        $result = $this->object->get($model);

        $this->assertEquals($mockObject, $result);
        $this->assertInstanceOf(get_class($mockObject), $result);
    }

    /**
     * Test what happens when we provide a class that does not exists in the configProvider array
     */
    public function testGetInvalidClass()
    {
        try {
            $this->object->get('');
            $this->fail('An exception should be thrown');
        } catch (\Exception $e) {
            $this->assertInstanceOf(\TIG\Buckaroo\Exception::class, $e);
        }
    }

    /**
     * Test what happens if there is a wrong class in the ConfigProviders array.
     */
    public function testLogicException()
    {
        $model = 'model1';
        /**
         * The classname is fine, as long as its not an instance of ConfigProviderInterface
         */
        $mockObject = m::mock(static::class);
        $this->objectManagerInterface->shouldReceive('get')->with($model)->andReturn($mockObject);

        try {
            $this->object->get($model);
            $this->fail('An exception should be thrown');
        } catch (\Exception $e) {
            $this->assertInstanceOf(\LogicException::class, $e);
        }
    }

    /**
     * Test that we get an exception when there are no ConfigProviders.
     */
    public function testGetNoConfigProviders()
    {
        $this->object = $this->objectManagerHelper->getObject(Factory::class);

        try {
            $this->object->get('');
            $this->fail('An exception should be thrown');
        } catch (\Exception $e) {
            $this->assertInstanceOf(\LogicException::class, $e);
        }
    }

    /**
     * Test the happy path for the has() method.
     */
    public function testHasHappyPath()
    {
        $this->assertTrue($this->object->has('model1'));
    }

    /**
     * Test the not found path.
     */
    public function testHasValidProvider()
    {
        $this->assertFalse($this->object->has('non-existing'));
    }

    /**
     * Test what happens where there are no ConfigProviders
     */
    public function testHasNoProviders()
    {
        $this->object = $this->objectManagerHelper->getObject(Factory::class);

        try {
            $this->object->has('');
            $this->fail('An exception should be thrown');
        } catch (\Exception $e) {
            $this->assertInstanceOf(\LogicException::class, $e);
        }
    }
}
