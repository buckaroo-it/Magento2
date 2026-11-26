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

use Magento\Framework\Phrase;
use Buckaroo\Magento2\Model\Config\Source\Afterpay2PaymentMethods;
use Buckaroo\Magento2\Test\BaseTest;

class Afterpay2PaymentMethodsTest extends BaseTest
{
    protected $instanceClass = Afterpay2PaymentMethods::class;

    /**
     * The source offers exactly Acceptgiro (1) and Digiaccept (2), in that order.
     */
    public function testToOptionArray()
    {
        $instance = $this->getInstance();
        $result = $instance->toOptionArray();

        $this->assertCount(2, $result);
        $this->assertContainsOnlyInstancesOf(Phrase::class, array_column($result, 'label'));

        $options = array_map(function ($option) {
            return ['value' => $option['value'], 'label' => (string)$option['label']];
        }, $result);

        $this->assertSame(
            [
                ['value' => 1, 'label' => 'Acceptgiro'],
                ['value' => 2, 'label' => 'Digiaccept'],
            ],
            $options
        );
    }
}
