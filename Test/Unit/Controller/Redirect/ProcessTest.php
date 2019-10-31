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
namespace TIG\Buckaroo\Test\Unit\Controller\Redirect;

use Magento\Checkout\Model\ConfigProviderInterface;
use Magento\Framework\App\Response\RedirectInterface;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Message\ManagerInterface;
use Magento\Sales\Api\Data\TransactionInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Payment;
use TIG\Buckaroo\Model\ConfigProvider\Factory;
use TIG\Buckaroo\Model\OrderStatusFactory;
use TIG\Buckaroo\Test\BaseTest;
use TIG\Buckaroo\Helper\Data;
use TIG\Buckaroo\Controller\Redirect\Process;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\RequestInterface;
use Magento\Checkout\Model\Cart;

class ProcessTest extends BaseTest
{
    protected $instanceClass = Process::class;

    /**
     * Test the path with no parameters set.
     */
    public function testExecute()
    {
        $response = $this->getFakeMock(ResponseInterface::class)->getMockForAbstractClass();

        $request = $this->getFakeMock(RequestInterface::class)->setMethods(['getParams'])->getMockForAbstractClass();
        $request->expects($this->once())->method('getParams')->willReturn([]);

        $redirect = $this->getFakeMock(RedirectInterface::class)->setMethods(['redirect'])->getMockForAbstractClass();
        $redirect->expects($this->once())->method('redirect');

        $contextMock = $this->getFakeMock(Context::class)
            ->setMethods(['getRequest', 'getRedirect', 'getResponse'])
            ->getMock();
        $contextMock->expects($this->once())->method('getRequest')->willReturn($request);
        $contextMock->expects($this->once())->method('getRedirect')->willReturn($redirect);
        $contextMock->expects($this->once())->method('getResponse')->willReturn($response);

        $instance = $this->getInstance(['context' => $contextMock]);
        $instance->execute();
    }

    /**
     * Test the path when we are unable to create a quote.
     */
    public function testExecuteUnableToCreateQuote()
    {
        $failureStatus = 'failure';
        $params = [
            'brq_ordernumber' => null,
            'brq_invoicenumber' => null,
            'brq_statuscode' => null,
            'brq_transactions' => null,
            'brq_datarequest' => null
        ];

        $response = $this->getFakeMock(ResponseInterface::class)->getMockForAbstractClass();

        $request = $this->getFakeMock(RequestInterface::class)->setMethods(['getParams'])->getMockForAbstractClass();
        $request->expects($this->once())->method('getParams')->willReturn($params);

        $redirect = $this->getFakeMock(RedirectInterface::class)->setMethods(['redirect'])->getMockForAbstractClass();
        $redirect->expects($this->once())->method('redirect')->with($response, 'failure_url', []);

        $messageManagerMock = $this->getFakeMock(ManagerInterface::class)
            ->setMethods(['addErrorMessage'])
            ->getMockForAbstractClass();
        $messageManagerMock->expects($this->once())->method('addErrorMessage');

        $contextMock = $this->getFakeMock(Context::class)
            ->setMethods(['getRequest', 'getRedirect', 'getResponse', 'getMessageManager'])
            ->getMock();
        $contextMock->expects($this->once())->method('getRequest')->willReturn($request);
        $contextMock->expects($this->once())->method('getRedirect')->willReturn($redirect);
        $contextMock->expects($this->once())->method('getResponse')->willReturn($response);
        $contextMock->expects($this->once())->method('getMessageManager')->willReturn($messageManagerMock);

        $configProviderMock = $this->getFakeMock(ConfigProviderInterface::class)
            ->setMethods(['getFailureRedirect', 'getCancelOnFailed'])
            ->getMockForAbstractClass();
        $configProviderMock->expects($this->once())->method('getFailureRedirect')->willReturn('failure_url');
        $configProviderMock->expects($this->once())->method('getCancelOnFailed')->willReturn(true);

        $configProviderFactoryMock = $this->getFakeMock(Factory::class)->setMethods(['get'])->getMock();
        $configProviderFactoryMock->expects($this->once())->method('get')->willReturn($configProviderMock);

        $cartMock = $this->getFakeMock(Cart::class)->setMethods(['setQuote', 'save'])->getMock();
        $cartMock->expects($this->once())->method('setQuote')->willReturnSelf();
        $cartMock->expects($this->once())->method('save')->willReturn(false);

        $payment = $this->getFakeMock(Payment::class)
            ->setMethods(['getMethodInstance', 'canProcessPostData'])
            ->getMock();
        $payment->expects($this->once())->method('getMethodInstance')->willReturnSelf();
        $payment->expects($this->once())->method('canProcessPostData')->with($payment, $params)->willReturn(true);

        $orderMock = $this->getFakeMock(Order::class)
            ->setMethods([
                'loadByIncrementId', 'getId', 'getState', 'canCancel',
                'cancel', 'setStatus', 'getStore', 'save', 'getPayment'
            ])
            ->getMock();
        $orderMock->expects($this->once())->method('loadByIncrementId')->with(null)->willReturnSelf();
        $orderMock->expects($this->exactly(2))->method('getId')->willReturn(null);
        $orderMock->expects($this->once())->method('getState')->willReturn('!canceled');
        $orderMock->expects($this->once())->method('canCancel')->willReturn(true);
        $orderMock->expects($this->once())->method('cancel')->willReturnSelf();
        $orderMock->expects($this->once())->method('setStatus')->with($failureStatus)->willReturnSelf();
        $orderMock->method('getStore')->willReturnSelf();
        $orderMock->expects($this->once())->method('save')->willReturnSelf();
        $orderMock->expects($this->once())->method('getPayment')->willReturn($payment);

        $helperMock = $this->getFakeMock(Data::class)->setMethods(null)->getMock();

        $orderStatusFactoryMock = $this->getFakeMock(OrderStatusFactory::class)->setMethods(['get'])->getMock();
        $orderStatusFactoryMock->expects($this->once())
            ->method('get')
            ->with($this->anything(), $orderMock)
            ->willReturn($failureStatus);

        $transactionMock = $this->getFakeMock(TransactionInterface::class)
            ->setMethods(['load', 'getOrder'])
            ->getMockForAbstractClass();
        $transactionMock->expects($this->once())->method('load')->with(null, 'txn_id');
        $transactionMock->expects($this->once())->method('getOrder')->willReturn($orderMock);

        $instance = $this->getInstance([
            'context' => $contextMock,
            'configProviderFactory' => $configProviderFactoryMock,
            'cart' => $cartMock,
            'order' => $orderMock,
            'transaction' => $transactionMock,
            'helper' => $helperMock,
            'orderStatusFactory' => $orderStatusFactoryMock
        ]);
        $instance->execute();
    }

    /**
     * Test what happens when we are unable to cancel the order.
     */
    public function testExecuteUnableToCancelOrder()
    {
        $params = [
            'brq_ordernumber' => null,
            'brq_invoicenumber' => null,
            'brq_statuscode' => null,
            'brq_transactions' => null,
            'brq_datarequest' => null
        ];

        $response = $this->getFakeMock(ResponseInterface::class)->getMockForAbstractClass();

        $request = $this->getFakeMock(RequestInterface::class)->setMethods(['getParams'])->getMockForAbstractClass();
        $request->expects($this->once())->method('getParams')->willReturn($params);

        $redirect = $this->getFakeMock(RedirectInterface::class)->setMethods(['redirect'])->getMockForAbstractClass();
        $redirect->expects($this->once())->method('redirect')->with($response, 'failure_url', []);

        $messageManagerMock = $this->getFakeMock(ManagerInterface::class)
            ->setMethods(['addErrorMessage'])
            ->getMockForAbstractClass();
        $messageManagerMock->expects($this->once())->method('addErrorMessage');

        $contextMock = $this->getFakeMock(Context::class)
            ->setMethods(['getRequest', 'getRedirect', 'getResponse', 'getMessageManager'])
            ->getMock();
        $contextMock->expects($this->once())->method('getRequest')->willReturn($request);
        $contextMock->expects($this->once())->method('getRedirect')->willReturn($redirect);
        $contextMock->expects($this->once())->method('getResponse')->willReturn($response);
        $contextMock->expects($this->once())->method('getMessageManager')->willReturn($messageManagerMock);

        $configProviderMock = $this->getFakeMock(ConfigProviderInterface::class)
            ->setMethods(['getFailureRedirect', 'getCancelOnFailed'])
            ->getMockForAbstractClass();
        $configProviderMock->expects($this->once())->method('getFailureRedirect')->willReturn('failure_url');
        $configProviderMock->expects($this->once())->method('getCancelOnFailed')->willReturn(true);

        $configProviderFactoryMock = $this->getFakeMock(Factory::class)->setMethods(['get'])->getMock();
        $configProviderFactoryMock->expects($this->once())->method('get')->willReturn($configProviderMock);

        $cartMock = $this->getFakeMock(Cart::class)->setMethods(['setQuote', 'save'])->getMock();
        $cartMock->expects($this->once())->method('setQuote')->willReturnSelf();
        $cartMock->expects($this->once())->method('save')->willReturn(true);

        $payment = $this->getFakeMock(Payment::class)
            ->setMethods(['getMethodInstance', 'canProcessPostData'])
            ->getMock();
        $payment->expects($this->once())->method('getMethodInstance')->willReturnSelf();
        $payment->expects($this->once())->method('canProcessPostData')->with($payment, $params)->willReturn(true);

        $orderMock = $this->getFakeMock(Order::class)
            ->setMethods(['loadByIncrementId', 'getId', 'canCancel', 'getStore','getPayment'])
            ->getMock();
        $orderMock->expects($this->once())->method('loadByIncrementId')->with(null)->willReturnSelf();
        $orderMock->expects($this->exactly(2))->method('getId')->willReturn(null);
        $orderMock->expects($this->once())->method('canCancel')->willReturn(false);
        $orderMock->method('getStore')->willReturnSelf();
        $orderMock->expects($this->once())->method('getPayment')->willReturn($payment);

        $helperMock = $this->getFakeMock(Data::class)->setMethods(null)->getMock();

        $transactionMock = $this->getFakeMock(TransactionInterface::class)
            ->setMethods(['load', 'getOrder'])
            ->getMockForAbstractClass();
        $transactionMock->expects($this->once())->method('load')->with(null, 'txn_id');
        $transactionMock->expects($this->once())->method('getOrder')->willReturn($orderMock);

        $instance = $this->getInstance([
            'context' => $contextMock,
            'configProviderFactory' => $configProviderFactoryMock,
            'cart' => $cartMock,
            'order' => $orderMock,
            'transaction' => $transactionMock,
            'helper' => $helperMock,
        ]);
        $instance->execute();
    }

    /**
     * Test a success status update.
     */
    public function testExecuteSuccessStatus()
    {
        $params = [
            'brq_ordernumber' => null,
            'brq_invoicenumber' => null,
            'brq_statuscode' => 190,
        ];

        $response = $this->getFakeMock(ResponseInterface::class)->getMockForAbstractClass();

        $request = $this->getFakeMock(RequestInterface::class)->setMethods(['getParams'])->getMockForAbstractClass();
        $request->expects($this->once())->method('getParams')->willReturn($params);

        $redirect = $this->getFakeMock(RedirectInterface::class)->setMethods(['redirect'])->getMockForAbstractClass();
        $redirect->expects($this->once())->method('redirect')->with($response, 'success_url', []);

        $messageManagerMock = $this->getFakeMock(ManagerInterface::class)
            ->setMethods(['addSuccessMessage'])
            ->getMockForAbstractClass();
        $messageManagerMock->expects($this->once())->method('addSuccessMessage');

        $contextMock = $this->getFakeMock(Context::class)
            ->setMethods(['getRequest', 'getRedirect', 'getResponse', 'getMessageManager'])
            ->getMock();
        $contextMock->expects($this->once())->method('getRequest')->willReturn($request);
        $contextMock->expects($this->once())->method('getRedirect')->willReturn($redirect);
        $contextMock->expects($this->once())->method('getResponse')->willReturn($response);
        $contextMock->expects($this->once())->method('getMessageManager')->willReturn($messageManagerMock);

        $configProviderMock = $this->getFakeMock(ConfigProviderInterface::class)
            ->setMethods(['getSuccessRedirect'])
            ->getMockForAbstractClass();
        $configProviderMock->expects($this->once())->method('getSuccessRedirect')->willReturn('success_url');

        $configProviderFactoryMock = $this->getFakeMock(Factory::class)->setMethods(['get'])->getMock();
        $configProviderFactoryMock->expects($this->once())->method('get')->willReturn($configProviderMock);

        $payment = $this->getFakeMock(Payment::class)
            ->setMethods(['getMethodInstance', 'canProcessPostData', 'processCustomPostData'])
            ->getMock();
        $payment->expects($this->exactly(3))->method('getMethodInstance')->willReturnSelf();
        $payment->expects($this->once())->method('canProcessPostData')->with($payment, $params)->willReturn(true);
        $payment->expects($this->once())->method('processCustomPostData')->with($payment, $params);

        $orderMock = $this->getFakeMock(Order::class)
            ->setMethods([
                'loadByIncrementId', 'getId', 'canInvoice', 'getQuoteId',
                'setStatus', 'save', 'getEmailSent', 'getStore','getPayment'
            ])
            ->getMock();
        $orderMock->expects($this->once())->method('loadByIncrementId')->with(null)->willReturnSelf();
        $orderMock->expects($this->exactly(2))->method('getId')->willReturn(true);
        $orderMock->expects($this->once())->method('canInvoice')->willReturn(true);
        $orderMock->expects($this->once())->method('getQuoteId')->willReturn(1);
        $orderMock->expects($this->once())->method('setStatus')->willReturnSelf();
        $orderMock->expects($this->once())->method('save')->willReturnSelf();
        $orderMock->expects($this->once())->method('getEmailSent')->willReturn(1);
        $orderMock->method('getStore')->willReturnSelf();
        $orderMock->expects($this->exactly(2))->method('getPayment')->willReturn($payment);

        $orderStatusFactoryMock = $this->getFakeMock(OrderStatusFactory::class)->setMethods(['get'])->getMock();
        $orderStatusFactoryMock->expects($this->once())
            ->method('get')
            ->with($this->anything(), $orderMock)
            ->willReturn('success');

        $helperMock = $this->getFakeMock(Data::class)->setMethods(null)->getMock();

        $instance = $this->getInstance([
            'context' => $contextMock,
            'configProviderFactory' => $configProviderFactoryMock,
            'order' => $orderMock,
            'helper' => $helperMock,
            'orderStatusFactory' => $orderStatusFactoryMock
        ]);
        $instance->execute();
    }

    public function loadOrderProvider()
    {
        return [
            'by invoicenumber' => [
                'brq_invoicenumber',
                'TIG-001',
                false
            ],
            'by ordernumber' => [
                'brq_ordernumber',
                'TIG-002',
                false
            ],
            'by transaction' => [
                'brq_transactions',
                'TG321',
                true
            ],
            'by datarequest' => [
                'brq_datarequest',
                'TG654',
                true
            ],
            'throws exception' => [
                'brq_random_key',
                null,
                true
            ]
        ];
    }

    /**
     * @param $paramKey
     * @param $paramValue
     * @param $byTransaction
     *
     * @dataProvider loadOrderProvider
     */
    public function testLoadOrder($paramKey, $paramValue, $byTransaction)
    {
        $params = [
            'brq_invoicenumber' => null,
            'brq_ordernumber' => null,
            'brq_transactions' => null,
            'brq_datarequest' => null,
        ];

        $params[$paramKey] = $paramValue;

        $orderId = false;
        if (!$byTransaction) {
            $orderId = $paramValue;
        }

        $orderMock = $this->getFakeMock(Order::class)->setMethods(['loadByIncrementId', 'getId'])->getMock();
        $orderMock->expects($this->once())->method('loadByIncrementId')->with($orderId)->willReturnSelf();
        $orderMock->expects($this->once())->method('getId')->willReturn($orderId);

        $transactionResult = $orderMock;
        if (!$paramValue) {
            $transactionResult = false;

            $exceptionMessage = 'There was no order found by transaction Id';
            $this->setExpectedException(\TIG\Buckaroo\Exception::class, $exceptionMessage);
        }

        $transactionMock = $this->getFakeMock(TransactionInterface::class)
            ->setMethods(['load', 'getOrder'])
            ->getMockForAbstractClass();
        $transactionMock->method('load')->with($paramValue, 'txn_id');
        $transactionMock->method('getOrder')->willReturn($transactionResult);

        $instance = $this->getInstance(['order' => $orderMock, 'transaction' => $transactionMock]);

        $this->setProperty('response', $params, $instance);
        $this->invoke('loadOrder', $instance);
    }
}
