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

namespace Buckaroo\Magento2\Test\Unit\Model\Validator;

use Buckaroo\Magento2\Helper\Data;
use Buckaroo\Magento2\Model\Validator\TransactionResponseStatus;
use Buckaroo\Magento2\Test\BaseTest;

class TransactionResponseStatusTest extends BaseTest
{
    protected $instanceClass = TransactionResponseStatus::class;

    public function getStatusCodeProvider()
    {
        return [
            'no transaction data' => [
                (object)[],
                null
            ],
            'has status code' => [
                (object)[
                    'Status' => (object)[
                        'Code' => (object)[
                            'Code' => 890
                        ]
                    ]
                ],
                890
            ],
            'has status code, is canceled' => [
                (object)[
                    'Status' => (object)[
                        'Code' => (object)[
                            'Code' => 791
                        ]
                    ],
                    'Transaction' => (object)[
                        'IsCanceled' => true
                    ]
                ],
                791
            ],
            'has status code, is not canceled' => [
                (object)[
                    'Status' => (object)[
                        'Code' => (object)[
                            'Code' => 675
                        ]
                    ],
                    'Transaction' => (object)[
                        'IsCanceled' => false
                    ]
                ],
                675
            ],
            'null status code, is canceled' => [
                (object)[
                    'Status' => (object)[
                        'Code' => (object)[
                            'Code' => null
                        ]
                    ],
                    'Transaction' => (object)[
                        'IsCanceled' => true
                    ]
                ],
                190
            ],
            'null status code, is not canceled' => [
                (object)[
                    'Status' => (object)[
                        'Code' => (object)[
                            'Code' => null
                        ]
                    ],
                    'Transaction' => (object)[
                        'IsCanceled' => false
                    ]
                ],
                null
            ],
            'no status code, is canceled' => [
                (object)[
                    'Transaction' => (object)[
                        'IsCanceled' => true
                    ]
                ],
                190
            ],
            'no status code, is not canceled' => [
                (object)[
                    'Transaction' => (object)[
                        'IsCanceled' => false
                    ]
                ],
                null
            ]
        ];
    }

    /**
     * @param $transaction
     * @param $expected
     *
     * @dataProvider getStatusCodeProvider
     */
    public function testGetStatusCode($transaction, $expected)
    {
        $helperMock = $this->getFakeMock(Data::class)->setMethods(null)->getMock();

        $instance = $this->getInstance(['helper' => $helperMock]);
        $this->setProperty('transaction', $transaction, $instance);
        $result = $this->invoke('getStatusCode', $instance);

        $this->assertEquals($expected, $result);
    }
}
