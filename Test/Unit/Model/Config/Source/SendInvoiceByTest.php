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

use Buckaroo\Magento2\Model\Config\Source\SendInvoiceBy;
use Buckaroo\Magento2\Test\BaseTest;

class SendInvoiceByTest extends BaseTest
{
    protected $instanceClass = SendInvoiceBy::class;

    public function testToOptionArray()
    {
        $expectedResult = [
            ['value' => 'email', 'label' => 'By e-mail'],
            ['value' => 'mail', 'label' => 'By mail (Includes fee from Klarna)']
        ];

        $instance = $this->getInstance();
        $result = $instance->toOptionArray();

        $this->assertEquals($expectedResult, $result);
    }
}
