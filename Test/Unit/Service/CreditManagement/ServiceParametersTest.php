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

namespace Buckaroo\Magento2\Test\Unit\Service\CreditManagement\ServiceParameters;


use PHPUnit\Framework\Attributes\DataProvider;
use Magento\Sales\Model\Order\Payment;
use Buckaroo\Magento2\Service\CreditManagement\ServiceParameters;
use Buckaroo\Magento2\Test\BaseTest;

class ServiceParametersTest extends BaseTest
{
    protected $instanceClass = ServiceParameters::class;

    /**
     * @return array
     */
    public static function serviceParametersGetProvider()
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
     */
    #[DataProvider('serviceParametersGetProvider')]
    public function testGetCreateCombinedInvoice($requestParameters, $filter, $expected)
    {
        $infoInstanceMock = $this->getFakeMock(Payment::class, true);

        $createCombinedInvoiceMock = $this->getFakeMock(ServiceParameters\CreateCombinedInvoice::class)
            ->onlyMethods(['get'])
            ->getMock();
        $createCombinedInvoiceMock->method('get')
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
     */
    #[DataProvider('serviceParametersGetProvider')]
    public function testGetCreateCreditNote($requestParameters, $filter, $expected)
    {
        $infoInstanceMock = $this->getFakeMock(Payment::class, true);

        $createCreditNoteMock = $this->getFakeMock(ServiceParameters\CreateCreditNote::class)
            ->onlyMethods(['get'])
            ->getMock();
        $createCreditNoteMock->method('get')
            ->with($infoInstanceMock)
            ->willReturn($requestParameters);

        $instance = $this->getInstance(['createCreditNote' => $createCreditNoteMock]);
        $result = $instance->getCreateCreditNote($infoInstanceMock, $filter);

        $this->assertEquals($expected, $result);
    }
}
