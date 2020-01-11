<?php
/**
 *                  ___________       __            __
 *                  \__    ___/____ _/  |_ _____   |  |
 *                    |    |  /  _ \\   __\\__  \  |  |
 *                    |    | |  |_| ||  |   / __ \_|  |__
 *                    |____|  \____/ |__|  (____  /|____/
 *                                              \/
 *          ___          __                                   __
 *         |   |  ____ _/  |_   ____ _______   ____    ____ _/  |_
 *         |   | /    \\   __\_/ __ \\_  __ \ /    \ _/ __ \\   __\
 *         |   ||   |  \|  |  \  ___/ |  | \/|   |  \\  ___/ |  |
 *         |___||___|  /|__|   \_____>|__|   |___|  / \_____>|__|
 *                  \/                           \/
 *                  ________
 *                 /  _____/_______   ____   __ __ ______
 *                /   \  ___\_  __ \ /  _ \ |  |  \\____ \
 *                \    \_\  \|  | \/|  |_| ||  |  /|  |_| |
 *                 \______  /|__|    \____/ |____/ |   __/
 *                        \/                       |__|
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Creative Commons License.
 * It is available through the world-wide-web at this URL:
 * http://creativecommons.org/licenses/by-nc-nd/3.0/nl/deed.en_US
 * If you are unable to obtain it through the world-wide-web, please send an email
 * to servicedesk@tig.nl so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade this module to newer
 * versions in the future. If you wish to customize this module for your
 * needs please contact servicedesk@tig.nl for more information.
 *
 * @copyright Copyright (c) Total Internet Group B.V. https://tig.nl/copyright
 * @license   http://creativecommons.org/licenses/by-nc-nd/3.0/nl/deed.en_US
 */
namespace TIG\Buckaroo\Test\Unit\Gateway\Http\TransactionBuilder;

use Magento\Framework\Url;
use Magento\Framework\UrlInterface;
use Magento\Sales\Model\Order as MagentoOrder;
use TIG\Buckaroo\Gateway\Http\TransactionBuilder\Order;
use TIG\Buckaroo\Test\BaseTest;

class OrderTest extends BaseTest
{
    protected $instanceClass = Order::class;

    /**
     * @var Order
     */
    protected $object;

    /**
     * @var \TIG\Buckaroo\Model\ConfigProvider\Account|\Mockery\MockInterface
     */
    protected $configProviderAccount;

    /**
     * @var Url|\Mockery\MockInterface
     */
    protected $urlBuilderMock;

    public function setUp()
    {
        parent::setUp();

        $this->configProviderAccount = \Mockery::mock(\TIG\Buckaroo\Model\ConfigProvider\Account::class);
        $this->urlBuilderMock = \Mockery::mock(Url::class);

        $this->object = $this->objectManagerHelper->getObject(
            Order::class,
            [
                'configProviderAccount' => $this->configProviderAccount,
                'urlBuilder' => $this->urlBuilderMock
            ]
        );
    }

    public function testGetBody()
    {
        $expected = [
            'Currency' => 'EUR',
            'AmountDebit' => 50,
            'AmountCredit' => 0,
            'Invoice' => 999,
            'Order' => 999,
            'Description' => 'transactionLabel',
            'ClientIP' => [
                '_' => '127.0.0.1',
                'Type' => 'IPv4',
            ],
            'StartRecurrent' => 1,
            'Services' => [
                'Service' => 'servicesString',
            ],
        ];

        $this->object->amount = 50;
        $this->object->currency = 'EUR';
        $this->object->invoiceId = $expected['Invoice'];
        $this->object->setStartRecurrent($expected['StartRecurrent']);
        $this->object->setServices($expected['Services']['Service']);

        $this->configProviderAccount->shouldReceive('getTransactionLabel')->andReturn($expected['Description']);
        $this->configProviderAccount->shouldReceive('getCreateOrderBeforeTransaction')->andReturn(1);
        $this->configProviderAccount->shouldReceive('getOrderStatusNew')->andReturn(1);

        $this->urlBuilderMock->shouldReceive('setScope')->andReturnSelf();
        $this->urlBuilderMock->shouldReceive('getRouteUrl')->andReturnSelf();
        $this->urlBuilderMock->shouldReceive('getDirectUrl')->andReturnSelf();

        $order = \Mockery::mock(MagentoOrder::class);
        $order->shouldReceive('getIncrementId')->once()->andReturn($expected['Invoice']);
        $order->shouldReceive('getRemoteIp')->andReturn($expected['ClientIP']['_']);
        $order->shouldReceive('getStore')->once();
        $order->shouldReceive('setState')->once();
        $order->shouldReceive('setStatus')->once();
        $order->shouldReceive('getStoreId')->andReturn(1);
        $order->shouldReceive('save');

        $this->object->setOrder($order);

        $result = $this->object->getBody();
        foreach ($expected as $key => $value) {
            $valueToTest = $value;

            if (is_array($valueToTest)) {
                $valueToTest = (object)$value;
            }

            $this->assertEquals($valueToTest, $result[$key]);
        }
    }

    /**
     * @return array
     */
    public function filterBodyProvider()
    {
        return [
            'no service name and action' => [
                [],
                [
                    'Invoice' => '#1234',
                    'AmountCredit' => '5.00',
                    'OriginalTransactionKey' => 'abc1234',
                ],
                [
                    'Invoice' => '#1234',
                    'AmountCredit' => '5.00',
                    'OriginalTransactionKey' => 'abc1234',
                ]
            ],
            'no service name' => [
                [
                    'Action' => 'Order'
                ],
                [
                    'Invoice' => '#5678',
                    'AmountCredit' => '10.00',
                    'OriginalTransactionKey' => 'def5678',
                ],
                [
                    'Invoice' => '#5678',
                    'AmountCredit' => '10.00',
                    'OriginalTransactionKey' => 'def5678',
                ]
            ],
            'no service action' => [
                [
                    'Name' => 'paymentguarantee'
                ],
                [
                    'Invoice' => '#9012',
                    'AmountCredit' => '15.00',
                    'OriginalTransactionKey' => 'ghi9012',
                ],
                [
                    'Invoice' => '#9012',
                    'AmountCredit' => '15.00',
                    'OriginalTransactionKey' => 'ghi9012',
                ]
            ],
            'filtered paymentguarantee order' => [
                [
                    'Name' => 'paymentguarantee',
                    'Action' => 'Order'
                ],
                [
                    'Invoice' => '#3456',
                    'AmountCredit' => '20.00',
                    'OriginalTransactionKey' => 'jkl3456',
                ],
                [
                    'AmountCredit' => '20.00',
                    'OriginalTransactionKey' => 'jkl3456',
                ]
            ],
            'filtered paymentguarantee partialinvoice' => [
                [
                    'Name' => 'paymentguarantee',
                    'Action' => 'PartialInvoice'
                ],
                [
                    'Invoice' => '#7890',
                    'AmountCredit' => '25.00',
                    'OriginalTransactionKey' => 'mno7890',
                ],
                [
                    'Invoice' => '#7890',
                    'AmountCredit' => '25.00',
                ]
            ],
            'filtered creditmanagement3 createcreditnote' => [
                [
                    'Name' => 'CreditManagement3',
                    'Action' => 'CreateCreditNote'
                ],
                [
                    'Invoice' => '#3571',
                    'AmountCredit' => '30.00',
                    'OriginalTransactionKey' => 'pqr3571',
                ],
                [
                    'Invoice' => '#3571',
                ]
            ],
        ];
    }

    /**
     * @param $service
     * @param $body
     * @param $expected
     *
     * @dataProvider filterBodyProvider
     */
    public function testFilterBody($service, $body, $expected)
    {
        $instance = $this->getInstance();
        $instance->setServices($service);

        $result = $this->invokeArgs('filterBody', [$body], $instance);
        $this->assertEquals($expected, $result);
    }

    /**
     * @return array
     */
    public function getReturnUrlProvider()
    {
        return [
            'instance has no return url' => [
                null,
                'tig.nl',
                'tig.nl'
            ],
            'instance has return url' => [
                'magento.com',
                'google.com',
                'magento.com'
            ]
        ];
    }

    /**
     * @param $existingUrl
     * @param $generatedUrl
     * @param $expected
     *
     * @dataProvider getReturnUrlProvider
     */
    public function testGetReturnUrl($existingUrl, $generatedUrl, $expected)
    {
        $methodIsCalled = (int)!((bool)$existingUrl);

        $orderMock = $this->getFakeMock(MagentoOrder::class)->setMethods(['getStoreId'])->getMock();
        $orderMock->expects($this->exactly($methodIsCalled))->method('getStoreId')->willReturn(1);

        $urlBuilderMock = $this->getFakeMock(UrlInterface::class)
            ->setMethods(['setScope', 'getRouteUrl'])
            ->getMockForAbstractClass();
        $urlBuilderMock->expects($this->exactly($methodIsCalled))->method('setScope')->with(1)->willReturnSelf();
        $urlBuilderMock->expects($this->exactly($methodIsCalled*2))
            ->method('getRouteUrl')
            ->withConsecutive(['buckaroo/redirect/process'], [$generatedUrl])
            ->willReturn($generatedUrl);

        $instance = $this->getInstance(['urlBuilder' => $urlBuilderMock]);
        $this->setProperty('order', $orderMock, $instance);
        $this->setProperty('returnUrl', $existingUrl, $instance);

        $result = $instance->getReturnUrl();
        $this->assertEquals($expected, $result);
    }
}
