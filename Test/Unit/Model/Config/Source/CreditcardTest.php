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

use Buckaroo\Magento2\Model\Config\Source\Creditcard;
use Buckaroo\Magento2\Model\ConfigProvider\Method\Creditcard as CreditcardProvider;

class CreditcardTest extends \Buckaroo\Magento2\Test\BaseTest
{
    protected $instanceClass = Creditcard::class;

    public function testToOptionArray()
    {
        $issuers = [
            [
                'name' => 'Test 1',
                'code' => 'code1',
            ],
            [
                'name' => 'Test 2',
                'code' => 'code2',
            ],
            [
                'name' => 'Test 3',
                'code' => 'code3',
            ],
        ];

        $configProviderMock = $this->getFakeMock(CreditcardProvider::class)->onlyMethods(['getIssuers'])->getMock();
        $configProviderMock->method('getIssuers')->willReturn($issuers);

        $instance = $this->getInstance(['configProvider' => $configProviderMock]);
        $result = $instance->toOptionArray();

        $expected = [
            [
                'value' => 'code1',
                'label' => 'Test 1',
            ],
            [
                'value' => 'code2',
                'label' => 'Test 2',
            ],
            [
                'value' => 'code3',
                'label' => 'Test 3',
            ],
        ];

        $this->assertEquals($expected, $result);
    }
}
