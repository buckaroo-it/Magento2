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
use Buckaroo\Magento2\Model\Config\Source\AfterpayPaymentMethods;
use Buckaroo\Magento2\Test\BaseTest;

class AfterpayPaymentMethodsTest extends BaseTest
{
    protected $instanceClass = AfterpayPaymentMethods::class;

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

        $found = false;
        foreach ($result as $opt) {
            if ($opt['value'] == $paymentOption['value'] && $opt['label']->getText() == $paymentOption['label']) {
                $found = true;
                break;
            }
        }
        $this->assertTrue($found);
    }
}
