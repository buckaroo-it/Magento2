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
namespace TIG\Buckaroo\Test\Unit\Model\Config\Backend;

class NumberTest extends \TIG\Buckaroo\Test\BaseTest
{
    /**
     * @var \TIG\Buckaroo\Model\Config\Backend\Number
     */
    protected $object;

    /**
     * @var \Mockery\MockInterface
     */
    protected $resource;

    /**
     * Setup the base mocks.
     */
    public function setUp()
    {
        parent::setUp();

        $this->resource = \Mockery::mock(\Magento\Framework\Model\ResourceModel\AbstractResource::class);
        $this->resource->shouldReceive('save');

        $this->object = $this->objectManagerHelper->getObject(
            \TIG\Buckaroo\Model\Config\Backend\Number::class,
            [
            'resource' => $this->resource,
            ]
        );
    }

    /**
     * Test what happens when a empty value is provided.
     *
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function testEmptyValue()
    {
        $this->assertInstanceOf(\TIG\Buckaroo\Model\Config\Backend\Number::class, $this->object->save());
    }

    /**
     * Test what happens when there is a valid value is provided.
     *
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function testValidValue()
    {
        $this->object->setData('value', '10');
        $this->assertInstanceOf(\TIG\Buckaroo\Model\Config\Backend\Number::class, $this->object->save());

        $this->object->setData('value', '0');
        $this->assertInstanceOf(\TIG\Buckaroo\Model\Config\Backend\Number::class, $this->object->save());
    }

    /**
     * Test what happens when an invalid value is provided.
     */
    public function testInvalidValue()
    {
        foreach (['10.1', '-2', 'wrong'] as $value) {
            try {
                $this->object->setData('value', $value);
                $this->object->save();
                $this->fail();
            } catch (\Exception $e) {
                $this->assertInstanceOf(\Magento\Framework\Exception\LocalizedException::class, $e);
            }
        }
    }
}
