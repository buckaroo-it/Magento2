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

namespace Buckaroo\Magento2\Test\Unit\Model\Config\Source;


use PHPUnit\Framework\Attributes\DataProvider;
use Buckaroo\Magento2\Model\Config\Source\Afterpay2PaymentMethods;
use Buckaroo\Magento2\Test\BaseTest;

class Afterpay2PaymentMethodsTest extends BaseTest
{
    protected $instanceClass = Afterpay2PaymentMethods::class;

    /**
     * @return array
     */
    public static function toOptionArrayProvider()
    {
        return [
            [
                ['value' => 1, 'label' => 'Acceptgiro']
            ],
            [
                ['value' => 2, 'label' => 'Digiaccept']
            ]
        ];
    }

    /**
     * @param $paymentOption
     *
     */
    #[DataProvider('toOptionArrayProvider')]
    public function testToOptionArray($paymentOption)
    {
        $instance = $this->getInstance();
        $result = $instance->toOptionArray();

        $this->assertTrue(in_array($paymentOption, $result));
    }
}
