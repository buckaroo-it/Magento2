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

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Model\ResourceModel\AbstractResource;
use TIG\Buckaroo\Model\Config\Backend\Number;

class NumberTest extends \TIG\Buckaroo\Test\BaseTest
{
    protected $instanceClass = Number::class;

    /**
     * Test what happens when a empty value is provided.
     */
    public function testEmptyValue()
    {
        $resourceMock = $this->getFakeMock(AbstractResource::class)->setMethods(['save'])->getMockForAbstractClass();
        $resourceMock->expects($this->once())->method('save');

        $instance = $this->getInstance(['resource' => $resourceMock]);

        $result = $instance->save();
        $this->assertInstanceOf(Number::class, $result);
    }

    /**
     * Test what happens when there is a valid value is provided.
     */
    public function testValidValue()
    {
        $resourceMock = $this->getFakeMock(AbstractResource::class)->setMethods(['save'])->getMockForAbstractClass();
        $resourceMock->expects($this->once())->method('save');

        $instance = $this->getInstance(['resource' => $resourceMock]);
        $instance->setValue("10");

        $result = $instance->save();
        $this->assertInstanceOf(Number::class, $result);
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
