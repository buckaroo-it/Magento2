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
namespace TIG\Buckaroo\Test\Unit\Gateway\Http\TransactionBuilder;

use Magento\Framework\Data\Form\FormKey;
use Magento\Framework\Url;
use Magento\Framework\UrlInterface;
use Magento\Sales\Model\Order as MagentoOrder;
use Magento\Sales\Model\Order\Payment;
use TIG\Buckaroo\Gateway\Http\TransactionBuilder\Order;
use TIG\Buckaroo\Model\ConfigProvider\Account;
use TIG\Buckaroo\Model\ConfigProvider\Method\Factory;
use TIG\Buckaroo\Test\BaseTest;

class OrderTest extends BaseTest
{
    protected $instanceClass = Order::class;

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
                'Service' => [
                    'Action' => 'actionstring'
                ],
            ],
            'AdditionalParameters' => [
                'AdditionalParameter' => [
                    [
                        '_'    => 'actionstring',
                        'Name' => 'service_action_from_magento',
                    ],
                    [
                        '_'    => 1,
                        'Name' => 'initiated_by_magento',
                    ]
                ],
            ],
        ];

        $orderMock = $this->getFakeMock(MagentoOrder::class)
            ->setMethods(['getIncrementId', 'getRemoteIp', 'getStore', 'setState', 'setStatus', 'getStoreId', 'save'])
            ->getMock();
        $orderMock->expects($this->once())->method('getIncrementId')->willReturn($expected['Invoice']);
        $orderMock->method('getRemoteIp')->willReturn($expected['ClientIP']['_']);
        $orderMock->expects($this->once())->method('getStore');
        $orderMock->expects($this->once())->method('setState');
        $orderMock->expects($this->once())->method('setStatus');
        $orderMock->method('getStoreId')->willReturn(1);
        $orderMock->method('save');

        $configProviderAccountMock = $this->getFakeMock(Account::class)
            ->setMethods(['getTransactionLabel', 'getCreateOrderBeforeTransaction', 'getOrderStatusNew'])
            ->getMock();
        $configProviderAccountMock->method('getTransactionLabel')->willReturn($expected['Description']);
        $configProviderAccountMock->method('getCreateOrderBeforeTransaction')->willReturn(1);
        $configProviderAccountMock->method('getOrderStatusNew')->willReturn(1);

        $urlBuilderMock = $this->getFakeMock(Url::class)
            ->setMethods(['setScope', 'getRouteUrl', 'getDirectUrl'])
            ->getMock();
        $urlBuilderMock->method('setScope')->willReturnSelf();
        $urlBuilderMock->method('getRouteUrl')->willReturn('');
        $urlBuilderMock->method('getDirectUrl')->willReturnSelf();

        $instance = $this->getInstance([
            'configProviderAccount' => $configProviderAccountMock,
            'urlBuilder' => $urlBuilderMock
        ]);
        $instance->setAmount(50);
        $instance->setCurrency('EUR');
        $instance->setInvoiceId($expected['Invoice']);
        $instance->setStartRecurrent($expected['StartRecurrent']);
        $instance->setServices($expected['Services']['Service']);
        $instance->setOrder($orderMock);

        $result = $instance->getBody();

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
            'filtered capayable payininstallments' => [
                [
                    'Name' => 'capayable',
                    'Action' => 'PayInInstallments'
                ],
                [
                    'Invoice' => '#3571',
                    'Order' => '#8294',
                    'AmountCredit' => '42.00',
                    'OriginalTransactionKey' => 'reyk879',
                ],
                [
                    'Invoice' => '#3571',
                    'AmountCredit' => '42.00',
                    'OriginalTransactionKey' => 'reyk879',
                ]
            ],
            'cancel transaction method' => [
                [],
                [
                    'OriginalTransactionKey' => 'stu2345',
                ],
                [
                    'Transaction' => ['Key' => 'stu2345'],
                ],
                'CancelTransaction'
            ],
            'not cancel transaction method' => [
                [],
                [
                    'OriginalTransactionKey' => 'stu2345',
                ],
                [
                    'OriginalTransactionKey' => 'stu2345',
                ],
                'DataRequest'
            ],
        ];
    }

    /**
     * @param        $service
     * @param        $body
     * @param        $expected
     * @param string $method
     *
     * @dataProvider filterBodyProvider
     */
    public function testFilterBody($service, $body, $expected, $method = '')
    {
        $instance = $this->getInstance();
        $instance->setServices($service);

        if (strlen($method) > 0) {
            $instance->setMethod($method);
        }

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
                '123abc',
                'tig.nl?form_key=123abc'
            ],
            'instance has return url' => [
                'magento.com',
                'google.com',
                'def456',
                'magento.com'
            ]
        ];
    }

    /**
     * @param $existingUrl
     * @param $generatedUrl
     * @param $formKey
     * @param $expected
     *
     * @dataProvider getReturnUrlProvider
     */
    public function testGetReturnUrl($existingUrl, $generatedUrl, $formKey, $expected)
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
            ->withConsecutive(['buckaroo/redirect/process'], [$generatedUrl . '?form_key=' . $formKey])
            ->willReturnOnConsecutiveCalls($generatedUrl, $generatedUrl . '?form_key=' . $formKey);

        $formKeyMock = $this->getFakeMock(FormKey::class)->setMethods(['getFormKey'])->getMock();
        $formKeyMock->expects($this->exactly($methodIsCalled))->method('getFormKey')->willReturn($formKey);

        $instance = $this->getInstance(['urlBuilder' => $urlBuilderMock, 'formKey' => $formKeyMock]);
        $this->setProperty('order', $orderMock, $instance);
        $this->setProperty('returnUrl', $existingUrl, $instance);

        $result = $instance->getReturnUrl();
        $this->assertEquals($expected, $result);
    }

    public function testGetAllowedCurrencies()
    {
        $paymentMethod = 'tig_payment_method';
        $paymentMock = $this->getFakeMock(Payment::class)->setMethods(['getMethodInstance'])->getMock();
        $paymentMock->expects($this->once())->method('getMethodInstance')->willReturnSelf();
        $paymentMock->buckarooPaymentMethodCode = $paymentMethod;

        $orderMock = $this->getFakeMock(MagentoOrder::class)->setMethods(['getPayment'])->getMock();
        $orderMock->expects($this->once())->method('getPayment')->willReturn($paymentMock);

        $configFactoryMock = $this->getFakeMock(Factory::class)->setMethods(['get', 'getAllowedCurrencies'])->getMock();
        $configFactoryMock->expects($this->once())->method('get')->with($paymentMethod)->willReturnSelf();
        $configFactoryMock->expects($this->once())->method('getAllowedCurrencies')->willReturn(['EUR']);

        $instance = $this->getInstance(['configProviderMethodFactory' => $configFactoryMock]);
        $instance->setOrder($orderMock);

        $result = $this->invoke('getAllowedCurrencies', $instance);
        $this->assertEquals(['EUR'], $result);
    }

    public function setOrderAmountProvider()
    {
        return [
            'same currency' => [
                'EUR',
                'EUR',
                '10',
                '15',
                '10'
            ],
            'different currency' => [
                'USD',
                'EUR',
                '25',
                '30',
                '30'
            ],
        ];
    }

    /**
     * @param $orderCurrency
     * @param $trxCurrency
     * @param $total
     * @param $baseTotal
     * @param $expected
     *
     * @dataProvider setOrderAmountProvider
     */
    public function testSetOrderAmount($orderCurrency, $trxCurrency, $total, $baseTotal, $expected)
    {
        $orderMock = $this->getFakeMock(MagentoOrder::class)
            ->setMethods(['getOrderCurrencyCode', 'getGrandTotal', 'getBaseGrandTotal'])
            ->getMock();
        $orderMock->expects($this->once())->method('getOrderCurrencyCode')->willReturn($orderCurrency);
        $orderMock->method('getGrandTotal')->willReturn($total);
        $orderMock->method('getBaseGrandTotal')->willReturn($baseTotal);

        $instance = $this->getInstance();
        $instance->setCurrency($trxCurrency);
        $instance->setOrder($orderMock);

        $result = $this->invoke('setOrderAmount', $instance);
        $this->assertInstanceOf(Order::class, $result);
        $this->assertEquals($expected, $instance->getAmount());
    }
}
