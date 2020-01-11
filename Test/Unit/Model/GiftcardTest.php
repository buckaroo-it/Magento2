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
namespace TIG\Buckaroo\Test\Unit\Model;

use TIG\Buckaroo\Model\Giftcard;

class GiftcardTest extends \TIG\Buckaroo\Test\BaseTest
{
    protected $instanceClass = Giftcard::class;

    /**
     * @return array
     */
    public function servicecodeProvider()
    {
        return array(
            array(
                'servicecode' => 'shopgiftcard',
                'expected' => 'shopgiftcard'
            ),
            array(
                'servicecode' => 'bookgiftcard',
                'expected' => 'bookgiftcard'
            ),
            array(
                'servicecode' => 'discountcard',
                'expected' => 'discountcard'
            )
        );
    }

    /**
     * @param $servicecode
     * @param $expected
     *
     * @dataProvider servicecodeProvider
     */
    public function testShouldBeAbleToSetAndGetServicecode($servicecode, $expected)
    {
        $instance = $this->getInstance();
        $instance->setServicecode($servicecode);

        $this->assertEquals($expected, $instance->getServicecode());
    }

    /**
     * @return array
     */
    public function labelProvider()
    {
        return array(
            array(
                'label' => 'Webshop Giftcard',
                'expected' => 'Webshop Giftcard'
            ),
            array(
                'label' => 'Book Giftcard',
                'expected' => 'Book Giftcard'
            ),
            array(
                'label' => 'Discount Card',
                'expected' => 'Discount Card'
            )
        );
    }

    /**
     * @param $label
     * @param $expected
     *
     * @dataProvider labelProvider
     */
    public function testShouldBeAbleToSetAndGetLabel($label, $expected)
    {
        $instance = $this->getInstance();
        $instance->setLabel($label);

        $this->assertEquals($expected, $instance->getLabel());
    }
}
