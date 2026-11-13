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

namespace Buckaroo\Magento2\Test\Unit\Model;


use PHPUnit\Framework\Attributes\DataProvider;
use Buckaroo\Magento2\Model\Giftcard;

class GiftcardTest extends \Buckaroo\Magento2\Test\BaseTest
{
    protected $instanceClass = Giftcard::class;

    /**
     * @return array
     */
    public static function servicecodeProvider()
    {
        return [
            [
                'servicecode' => 'shopgiftcard',
                'expected' => 'shopgiftcard'
            ],
            [
                'servicecode' => 'bookgiftcard',
                'expected' => 'bookgiftcard'
            ],
            [
                'servicecode' => 'discountcard',
                'expected' => 'discountcard'
            ]
        ];
    }

    /**
     * @param $servicecode
     * @param $expected
     *
     */
    #[DataProvider('servicecodeProvider')]
    public function testShouldBeAbleToSetAndGetServicecode($servicecode, $expected)
    {
        $instance = $this->getInstance();
        $instance->setServicecode($servicecode);

        $this->assertEquals($expected, $instance->getServicecode());
    }

    /**
     * @return array
     */
    public static function labelProvider()
    {
        return [
            [
                'label' => 'Webshop Giftcard',
                'expected' => 'Webshop Giftcard'
            ],
            [
                'label' => 'Book Giftcard',
                'expected' => 'Book Giftcard'
            ],
            [
                'label' => 'Discount Card',
                'expected' => 'Discount Card'
            ]
        ];
    }

    /**
     * @param $label
     * @param $expected
     *
     */
    #[DataProvider('labelProvider')]
    public function testShouldBeAbleToSetAndGetLabel($label, $expected)
    {
        $instance = $this->getInstance();
        $instance->setLabel($label);

        $this->assertEquals($expected, $instance->getLabel());
    }
}
