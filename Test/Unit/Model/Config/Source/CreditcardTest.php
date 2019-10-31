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
namespace TIG\Buckaroo\Test\Unit\Model\Config\Source;

use TIG\Buckaroo\Model\Config\Source\Creditcard;
use TIG\Buckaroo\Model\ConfigProvider\Method\Creditcard as CreditcardProvider;

class CreditcardTest extends \TIG\Buckaroo\Test\BaseTest
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

        $configProviderMock = $this->getFakeMock(CreditcardProvider::class)->setMethods(['getIssuers'])->getMock();
        $configProviderMock->expects($this->once())->method('getIssuers')->willReturn($issuers);

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
