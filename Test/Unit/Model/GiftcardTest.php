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
namespace Buckaroo\Magento2\Test\Unit\Model;

use Buckaroo\Magento2\Model\Giftcard;

class GiftcardTest extends \Buckaroo\Magento2\Test\BaseTest
{
    protected $instanceClass = Giftcard::class;

    /**
     * @return array
     */
    public function servicecodeProvider()
    {
        return [
            [
                'servicecode' => 'shopgiftcard',
                'expected' => 'shopgiftcard',
            ],
            [
                'servicecode' => 'bookgiftcard',
                'expected' => 'bookgiftcard',
            ],
            [
                'servicecode' => 'discountcard',
                'expected' => 'discountcard',
            ],
        ];
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
        return [
            [
                'label' => 'Webshop Giftcard',
                'expected' => 'Webshop Giftcard',
            ],
            [
                'label' => 'Book Giftcard',
                'expected' => 'Book Giftcard',
            ],
            [
                'label' => 'Discount Card',
                'expected' => 'Discount Card',
            ],
        ];
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
