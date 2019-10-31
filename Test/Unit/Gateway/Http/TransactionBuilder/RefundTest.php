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
use TIG\Buckaroo\Gateway\Http\TransactionBuilder\Refund;
use TIG\Buckaroo\Model\ConfigProvider\Account;
use TIG\Buckaroo\Model\ConfigProvider\Method\Factory;
use TIG\Buckaroo\Test\BaseTest;

class RefundTest extends BaseTest
{
    protected $instanceClass = Refund::class;

    public function testGetBody()
    {
        $expected = [
            'Currency' => 'EUR',
            'AmountDebit' => 0,
            'AmountCredit' => 50,
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
            ->setMethods(['getIncrementId', 'getRemoteIp', 'getStore', 'getStoreId'])
            ->getMock();
        $orderMock->expects($this->once())->method('getIncrementId')->willReturn($expected['Invoice']);
        $orderMock->expects($this->once())->method('getRemoteIp')->willReturn($expected['ClientIP']['_']);
        $orderMock->expects($this->once())->method('getStore');
        $orderMock->expects($this->once())->method('getStoreId')->willReturn(1);

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

    public function testSetRefundCurrencyAndAmount()
    {
        $paymentMethod = 'tig_payment_method';
        $paymentMock = $this->getFakeMock(Payment::class)->setMethods(['getMethodInstance'])->getMock();
        $paymentMock->expects($this->once())->method('getMethodInstance')->willReturnSelf();
        $paymentMock->buckarooPaymentMethodCode = $paymentMethod;

        $orderMock = $this->getFakeMock(MagentoOrder::class)
            ->setMethods(['getPayment', 'getOrderCurrencyCode', 'getBaseToOrderRate'])
            ->getMock();
        $orderMock->expects($this->once())->method('getPayment')->willReturn($paymentMock);
        $orderMock->expects($this->exactly(2))->method('getOrderCurrencyCode')->willReturn('EUR');
        $orderMock->expects($this->once())->method('getBaseToOrderRate');

        $configFactoryMock = $this->getFakeMock(Factory::class)->setMethods(['get', 'getAllowedCurrencies'])->getMock();
        $configFactoryMock->expects($this->once())->method('get')->with($paymentMethod)->willReturnSelf();
        $configFactoryMock->expects($this->once())->method('getAllowedCurrencies')->willReturn(['EUR']);

        $instance = $this->getInstance(['configProviderMethodFactory' => $configFactoryMock]);
        $instance->setOrder($orderMock);

        $this->invoke('setRefundCurrencyAndAmount', $instance);
    }

    public function testSetRefundCurrencyAndAmountThrowsException()
    {
        $paymentMethod = 'tig_payment_method';
        $paymentMock = $this->getFakeMock(Payment::class)->setMethods(['getMethodInstance'])->getMock();
        $paymentMock->expects($this->once())->method('getMethodInstance')->willReturnSelf();
        $paymentMock->buckarooPaymentMethodCode = $paymentMethod;

        $orderMock = $this->getFakeMock(MagentoOrder::class)
            ->setMethods(['getPayment', 'getOrderCurrencyCode', 'getBaseToOrderRate'])
            ->getMock();
        $orderMock->expects($this->once())->method('getPayment')->willReturn($paymentMock);
        $orderMock->expects($this->once())->method('getOrderCurrencyCode')->willReturn('EUR');

        $configFactoryMock = $this->getFakeMock(Factory::class)->setMethods(['get', 'getAllowedCurrencies'])->getMock();
        $configFactoryMock->expects($this->once())->method('get')->with($paymentMethod)->willReturnSelf();
        $configFactoryMock->expects($this->once())->method('getAllowedCurrencies')->willReturn([]);

        $instance = $this->getInstance(['configProviderMethodFactory' => $configFactoryMock]);
        $instance->setOrder($orderMock);

        try {
            $this->invoke('setRefundCurrencyAndAmount', $instance);
        } catch (\TIG\Buckaroo\Exception $e) {
            $msg = "The selected payment method does not support the selected currency or the store's base currency.";
            $this->assertEquals($msg, $e->getMessage());
        }
    }
}
