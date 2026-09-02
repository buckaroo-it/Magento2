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

use Buckaroo\Magento2\Model\Config\Source\SequenceType;
use Buckaroo\Magento2\Test\BaseTest;

class SequenceTypeTest extends BaseTest
{
    protected $instanceClass = SequenceType::class;

    public function testToOptionArray()
    {
        $expectedResult = [
            ['value' => '1', 'label' => 'One-Off'],
            ['value' => '0', 'label' => 'Recurring']
        ];

        $instance = $this->getInstance();
        $result = $instance->toOptionArray();

        $this->assertEquals($expectedResult, $result);
    }
}
