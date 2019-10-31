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
namespace TIG\Buckaroo\Test\Unit\Service\CreditManagement\ServiceParameters;

use Magento\Sales\Model\Order\Payment;
use TIG\Buckaroo\Service\CreditManagement\ServiceParameters;
use TIG\Buckaroo\Test\BaseTest;

class ServiceParametersTest extends BaseTest
{
    protected $instanceClass = ServiceParameters::class;

    /**
     * @return array
     */
    public function serviceParametersGetProvider()
    {
        return [
            'filter nothing' => [
                [
                    'RequestParameter' => [
                        ['Name' => 'name abc', 'Group' => 'group abc', '_' => 'value abc'],
                        ['Name' => 'name def', '_' => 'value def'],
                        ['Name' => 'name ghi', 'Group' => 'group ghi', '_' => 'value ghi'],
                        ['Name' => 'name jkl', '_' => 'value jkl']
                    ]
                ],
                [],
                [
                    'RequestParameter' => [
                        ['Name' => 'name abc', 'Group' => 'group abc', '_' => 'value abc'],
                        ['Name' => 'name def', '_' => 'value def'],
                        ['Name' => 'name ghi', 'Group' => 'group ghi', '_' => 'value ghi'],
                        ['Name' => 'name jkl', '_' => 'value jkl']
                    ]
                ]
            ],
            'filter only by name' => [
                [
                    'RequestParameter' => [
                        ['Name' => 'name abc', 'Group' => 'group abc', '_' => 'value abc'],
                        ['Name' => 'name def', '_' => 'value def'],
                        ['Name' => 'name ghi', 'Group' => 'group ghi', '_' => 'value ghi'],
                        ['Name' => 'name jkl', '_' => 'value jkl']
                    ]
                ],
                [
                    ['Name' => 'name def']
                ],
                [
                    'RequestParameter' => [
                        ['Name' => 'name abc', 'Group' => 'group abc', '_' => 'value abc'],
                        ['Name' => 'name ghi', 'Group' => 'group ghi', '_' => 'value ghi'],
                        ['Name' => 'name jkl', '_' => 'value jkl']
                    ]
                ]
            ],
            'filter by name and group' => [
                [
                    'RequestParameter' => [
                        ['Name' => 'name abc', 'Group' => 'group abc', '_' => 'value abc'],
                        ['Name' => 'name def', '_' => 'value def'],
                        ['Name' => 'name ghi', 'Group' => 'group ghi', '_' => 'value ghi'],
                        ['Name' => 'name jkl', '_' => 'value jkl']
                    ]
                ],
                [
                    ['Name' => 'name abc', 'Group' => 'group abc']
                ],
                [
                    'RequestParameter' => [
                        ['Name' => 'name def', '_' => 'value def'],
                        ['Name' => 'name ghi', 'Group' => 'group ghi', '_' => 'value ghi'],
                        ['Name' => 'name jkl', '_' => 'value jkl']
                    ]
                ]
            ],
            'filter multiple parameters' => [
                [
                    'RequestParameter' => [
                        ['Name' => 'name abc', 'Group' => 'group abc', '_' => 'value abc'],
                        ['Name' => 'name def', '_' => 'value def'],
                        ['Name' => 'name ghi', 'Group' => 'group ghi', '_' => 'value ghi'],
                        ['Name' => 'name jkl', '_' => 'value jkl']
                    ]
                ],
                [
                    ['Name' => 'name ghi', 'Group' => 'group ghi'],
                    ['Name' => 'name jkl']
                ],
                [
                    'RequestParameter' => [
                        ['Name' => 'name abc', 'Group' => 'group abc', '_' => 'value abc'],
                        ['Name' => 'name def', '_' => 'value def'],
                    ]
                ]
            ]
        ];
    }

    /**
     * @param $requestParameters
     * @param $filter
     * @param $expected
     *
     * @dataProvider serviceParametersGetProvider
     */
    public function testGetCreateCombinedInvoice($requestParameters, $filter, $expected)
    {
        $infoInstanceMock = $this->getFakeMock(Payment::class, true);

        $createCombinedInvoiceMock = $this->getFakeMock(ServiceParameters\CreateCombinedInvoice::class)
            ->setMethods(['get'])
            ->getMock();
        $createCombinedInvoiceMock->expects($this->once())
            ->method('get')
            ->with($infoInstanceMock, 'payment_method')
            ->willReturn($requestParameters);

        $instance = $this->getInstance(['createCombinedInvoice' => $createCombinedInvoiceMock]);
        $result = $instance->getCreateCombinedInvoice($infoInstanceMock, 'payment_method', $filter);

        $this->assertEquals($expected, $result);
    }

    /**
     * @param $requestParameters
     * @param $filter
     * @param $expected
     *
     * @dataProvider serviceParametersGetProvider
     */
    public function testGetCreateCreditNote($requestParameters, $filter, $expected)
    {
        $infoInstanceMock = $this->getFakeMock(Payment::class, true);

        $createCreditNoteMock = $this->getFakeMock(ServiceParameters\CreateCreditNote::class)
            ->setMethods(['get'])
            ->getMock();
        $createCreditNoteMock->expects($this->once())
            ->method('get')
            ->with($infoInstanceMock)
            ->willReturn($requestParameters);

        $instance = $this->getInstance(['createCreditNote' => $createCreditNoteMock]);
        $result = $instance->getCreateCreditNote($infoInstanceMock, $filter);

        $this->assertEquals($expected, $result);
    }
}
