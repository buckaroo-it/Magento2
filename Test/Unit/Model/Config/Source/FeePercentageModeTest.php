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
use Buckaroo\Magento2\Model\Config\Source\FeePercentageMode;
use Buckaroo\Magento2\Test\BaseTest;
use Magento\Framework\Phrase;

class FeePercentageModeTest extends BaseTest
{
    protected $instanceClass = FeePercentageMode::class;

    /**
     * @return array
     */
    public static function toOptionArrayProvider()
    {
        return [
            [
                ['value' => 'subtotal',          'label' => new Phrase('Subtotal')]
            ],
            [
                ['value' => 'subtotal_incl_tax', 'label' => new Phrase('Subtotal incl. tax')]
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
            if ($opt['value'] == $paymentOption['value'] && $opt['label']->getText() == $paymentOption['label']->getText()) {
                $found = true;
                break;
            }
        }
        $this->assertTrue($found);
    }
}
