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

namespace Buckaroo\Magento2\Test\Unit\Controller\Redirect;

use Buckaroo\Magento2\Model\ConfigProvider\Account;
use Magento\Framework\App\Response\RedirectInterface;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Message\ManagerInterface;
use Magento\Sales\Api\Data\TransactionInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Payment;
use Buckaroo\Magento2\Model\ConfigProvider\Factory;
use Buckaroo\Magento2\Model\OrderStatusFactory;
use Buckaroo\Magento2\Test\BaseTest;
use Buckaroo\Magento2\Helper\Data;
use Buckaroo\Magento2\Controller\Redirect\Process;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\RequestInterface;
use Magento\Checkout\Model\Cart;

/**
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class ProcessTest extends BaseTest
{
    protected $instanceClass = Process::class;

    /**
     * Test the path with no parameters set.
     */
    public function testExecute()
    {
        $response = $this->getFakeMock(ResponseInterface::class)->getMockForAbstractClass();

        $request = $this->getFakeMock(RequestInterface::class)->onlyMethods(['getParams'])->getMockForAbstractClass();
        $request->method('getParams')->willReturn([]);

        $redirect = $this->getFakeMock(RedirectInterface::class)->onlyMethods(['redirect'])->getMockForAbstractClass();
        $redirect->method('redirect');

        $contextMock = $this->getFakeMock(Context::class)
            ->onlyMethods(['getRequest', 'getRedirect', 'getResponse'])
            ->getMock();
        $contextMock->method('getRequest')->willReturn($request);
        $contextMock->method('getRedirect')->willReturn($redirect);
        $contextMock->method('getResponse')->willReturn($response);

        // Add redirectFactory mock for Action controllers
        $redirectMock = $this->createMock(\Magento\Framework\Controller\Result\Redirect::class);
        $redirectFactoryMock = $this->createMock(\Magento\Framework\Controller\Result\RedirectFactory::class);
        $redirectFactoryMock->method('create')->willReturn($redirectMock);

        // Add RequestPushFactory mock for redirectRequest
        $pushRequestMock = $this->getMockBuilder(\Buckaroo\Magento2\Api\Data\PushRequestInterface::class)
            ->addMethods(['getOriginalRequest', 'getData'])
            ->onlyMethods(['getStatusCode'])
            ->getMockForAbstractClass();
        $pushRequestMock->method('getOriginalRequest')->willReturn([]);
        $pushRequestMock->method('getData')->willReturn([]);
        $pushRequestMock->method('getStatusCode')->willReturn('');

        $requestPushFactoryMock = $this->createMock(\Buckaroo\Magento2\Model\RequestPush\RequestPushFactory::class);
        $requestPushFactoryMock->method('create')->willReturn($pushRequestMock);

        $instance = $this->getInstance([
            'redirectFactory' => $redirectFactoryMock,
            'context' => $contextMock,
            'requestPushFactory' => $requestPushFactoryMock
        ]);
        $result = $instance->execute();
        $this->assertNotNull($result); // Basic assertion to prevent risky test
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

        $request = $this->getFakeMock(RequestInterface::class)->onlyMethods(['getParams'])->getMockForAbstractClass();

        $request->method('getParams')->willReturn($params);

        $redirect = $this->getFakeMock(RedirectInterface::class)->getMockForAbstractClass();
        $redirect->expects($this->once())->method('redirect')->with($response, 'failure_url', []);

        $messageManagerMock = $this->getFakeMock(ManagerInterface::class)
            ->onlyMethods(['addErrorMessage'])
            ->getMockForAbstractClass();
        $messageManagerMock->method('addErrorMessage');

        $contextMock = $this->getFakeMock(Context::class)
            ->onlyMethods(['getRequest', 'getRedirect', 'getResponse', 'getMessageManager'])
            ->getMock();
        $contextMock->method('getRequest')->willReturn($request);
        $contextMock->method('getRedirect')->willReturn($redirect);
        $contextMock->method('getResponse')->willReturn($response);
        $contextMock->method('getMessageManager')->willReturn($messageManagerMock);

        $configProviderMock = $this->getFakeMock(Account::class)
            ->onlyMethods(['getFailureRedirect', 'getCancelOnFailed', 'getFailureRedirectToCheckout'])
            ->getMock();
        $configProviderMock->method('getFailureRedirect')->willReturn('failure_url');
        $configProviderMock->method('getCancelOnFailed')->willReturn(true);
        $configProviderMock->method('getFailureRedirectToCheckout')->willReturn(false);

        $quoteRecreateMock = $this->getFakeMock(\Buckaroo\Magento2\Service\Sales\Quote\Recreate::class)->onlyMethods(['recreate'])->getMock();

        $quoteRecreateMock->method('recreate')->willReturn(false);

        $payment = $this->getFakeMock(Payment::class)
            ->addMethods(['canProcessPostData'])
            ->onlyMethods(['getMethodInstance'])
            ->getMock();
        $methodInstance = $this->getMockBuilder(\stdClass::class)
            ->addMethods(['getCode', 'getConfigData'])
            ->getMock();
        $methodInstance->method('getCode')->willReturn('buckaroo_magento2_other');
        $methodInstance->method('getConfigData')->willReturn('0');
        $payment->method('getMethodInstance')->willReturn($methodInstance);
        $payment->method('canProcessPostData')->with($payment, $params)->willReturn(true);

        $orderMock = $this->getFakeMock(Order::class)
            ->onlyMethods([
                'loadByIncrementId', 'getState', 'canCancel',
                'cancel', 'setStatus', 'getStore', 'getPayment', 'getId', 'save', 'getIncrementId'
])
            ->getMock();
        $orderMock->method('loadByIncrementId')->with(null)->willReturnSelf();
        $orderMock->method('getId')->willReturn(1);
        $orderMock->method('getIncrementId')->willReturn('TEST123');
        $orderMock->method('getState')->willReturn('!canceled');
        $orderMock->method('canCancel')->willReturn(true);
        $orderMock->method('cancel')->willReturnSelf();
        $orderMock->method('setStatus')->with($failureStatus)->willReturnSelf();
        $orderMock->method('getStore')->willReturnSelf();
        $orderMock->method('save')->willReturnSelf();
        $orderMock->method('getPayment')->willReturn($payment);

        $helperMock = $this->getFakeMock(Data::class)->addMethods(['setRestoreQuoteLastOrder'])->getMock();

        $orderStatusFactoryMock = $this->getFakeMock(OrderStatusFactory::class)->onlyMethods(['get'])->getMock();
        $orderStatusFactoryMock->method('get')
            ->with($this->anything(), $orderMock)
            ->willReturn($failureStatus);

        $transactionMock = $this->getFakeMock(\Magento\Sales\Model\Order\Payment\Transaction::class)
            ->onlyMethods(['getOrder', 'load'])
            ->getMock();
        $transactionMock->method('load')->with(null, 'txn_id');
        $transactionMock->method('getOrder')->willReturn($orderMock);

        // Mock PushRequestInterface for redirectRequest dependency
        $pushRequestMock = $this->getFakeMock(\Buckaroo\Magento2\Api\Data\PushRequestInterface::class)
            ->addMethods(['getOriginalRequest', 'getData', 'hasPostData', 'hasAdditionalInformation'])
            ->onlyMethods(['getStatusCode'])
            ->getMockForAbstractClass();
        $pushRequestMock->method('getOriginalRequest')->willReturn([]);
        $pushRequestMock->method('getData')->willReturn(['test' => 'data']);
        $pushRequestMock->method('getStatusCode')->willReturn('490');
        $pushRequestMock->method('hasPostData')->willReturn(true);
        $pushRequestMock->method('hasAdditionalInformation')->willReturn(false);

        // Mock OrderRequestService
        $orderRequestServiceMock = $this->createMock(\Buckaroo\Magento2\Service\Push\OrderRequestService::class);
        $orderRequestServiceMock->method('getOrderByRequest')->willReturn($orderMock);

        // Mock LockManagerWrapper
        $lockManagerMock = $this->createMock(\Buckaroo\Magento2\Model\LockManagerWrapper::class);
        $lockManagerMock->method('lockOrder')->willReturn(true);

        // Mock CheckoutSession with getters and setters used by controller
        $checkoutSessionMock = $this->getFakeMock(\Magento\Checkout\Model\Session::class)
            ->addMethods([
                'setRestoreQuoteLastOrder',
                'getLastSuccessQuoteId', 'getLastQuoteId', 'getLastOrderId', 'getLastRealOrderId',
                'setLastSuccessQuoteId', 'setLastQuoteId', 'setLastOrderId', 'setLastRealOrderId',
                'setLastOrderStatus'
            ])
            ->onlyMethods(['restoreQuote'])
            ->getMock();
        $checkoutSessionMock->method('setRestoreQuoteLastOrder')->willReturnSelf();
        $checkoutSessionMock->method('getLastSuccessQuoteId')->willReturn(null);
        $checkoutSessionMock->method('getLastQuoteId')->willReturn(null);
        $checkoutSessionMock->method('getLastOrderId')->willReturn(null);
        $checkoutSessionMock->method('getLastRealOrderId')->willReturn(null);
        $checkoutSessionMock->method('setLastSuccessQuoteId')->willReturnSelf();
        $checkoutSessionMock->method('setLastQuoteId')->willReturnSelf();
        $checkoutSessionMock->method('setLastOrderId')->willReturnSelf();
        $checkoutSessionMock->method('setLastRealOrderId')->willReturnSelf();
        $checkoutSessionMock->method('setLastOrderStatus')->willReturnSelf();
        $checkoutSessionMock->method('restoreQuote')->willReturn(true);

        $instance = $this->getInstance([
            'context' => $contextMock,
            'accountConfig' => $configProviderMock,
            'quoteRecreate' => $quoteRecreateMock,
            'order' => $orderMock,
            'transaction' => $transactionMock,
            'helper' => $helperMock,
            'orderStatusFactory' => $orderStatusFactoryMock,
            'redirectRequest' => $pushRequestMock,
            'orderRequestService' => $orderRequestServiceMock,
            'lockManager' => $lockManagerMock,
            'checkoutSession' => $checkoutSessionMock
        ]);
        $result = $instance->execute();
        // Basic assertion to ensure the method executes without throwing exceptions
        $this->assertNotNull($result);
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

        $request = $this->getFakeMock(RequestInterface::class)->onlyMethods(['getParams'])->getMockForAbstractClass();
        $request->method('getParams')->willReturn($params);

        $redirect = $this->getFakeMock(RedirectInterface::class)->getMockForAbstractClass();
        $redirect->expects($this->once())->method('redirect')->with($response, 'failure_url', []);

        $messageManagerMock = $this->getFakeMock(ManagerInterface::class)
            ->onlyMethods(['addErrorMessage'])
            ->getMockForAbstractClass();
        $messageManagerMock->method('addErrorMessage');

        $contextMock = $this->getFakeMock(Context::class)
            ->onlyMethods(['getRequest', 'getRedirect', 'getResponse', 'getMessageManager'])
            ->getMock();
        $contextMock->method('getRequest')->willReturn($request);
        $contextMock->method('getRedirect')->willReturn($redirect);
        $contextMock->method('getResponse')->willReturn($response);
        $contextMock->method('getMessageManager')->willReturn($messageManagerMock);

        $configProviderMock = $this->getFakeMock(Account::class)
            ->onlyMethods(['getFailureRedirect', 'getCancelOnFailed', 'getFailureRedirectToCheckout'])
            ->getMock();
        $configProviderMock->method('getFailureRedirect')->willReturn('failure_url');
        $configProviderMock->method('getCancelOnFailed')->willReturn(true);
        $configProviderMock->method('getFailureRedirectToCheckout')->willReturn(false);

        $payment = $this->getFakeMock(Payment::class)
            ->addMethods(['canProcessPostData'])
            ->onlyMethods(['getMethodInstance'])
            ->getMock();
        $methodInstance2 = $this->getMockBuilder(\stdClass::class)
            ->addMethods(['getCode', 'getConfigData'])
            ->getMock();
        $methodInstance2->method('getCode')->willReturn('buckaroo_magento2_other');
        $methodInstance2->method('getConfigData')->willReturn('0');
        $payment->method('getMethodInstance')->willReturn($methodInstance2);
        $payment->method('canProcessPostData')->with($payment, $params)->willReturn(true);

        $orderMock = $this->getFakeMock(Order::class)
            ->onlyMethods(['loadByIncrementId', 'canCancel', 'getStore','getPayment', 'getId', 'getIncrementId'])
            ->getMock();
        $orderMock->method('loadByIncrementId')->with(null)->willReturnSelf();
        $orderMock->method('getId')->willReturn(1);
        $orderMock->method('getIncrementId')->willReturn('TEST123');
        $orderMock->method('canCancel')->willReturn(false);
        $orderMock->method('getStore')->willReturnSelf();
        $orderMock->method('getPayment')->willReturn($payment);

        $helperMock = $this->getFakeMock(Data::class)->addMethods(['setRestoreQuoteLastOrder'])->getMock();

        $transactionMock = $this->getFakeMock(\Magento\Sales\Model\Order\Payment\Transaction::class)
            ->onlyMethods(['getOrder', 'load'])
            ->getMock();
        $transactionMock->method('load')->with(null, 'txn_id');
        $transactionMock->method('getOrder')->willReturn($orderMock);

        // Add redirectFactory mock for Action controllers
        $redirectMock = $this->createMock(\Magento\Framework\Controller\Result\Redirect::class);
        $redirectFactoryMock = $this->createMock(\Magento\Framework\Controller\Result\RedirectFactory::class);
        $redirectFactoryMock->method('create')->willReturn($redirectMock);

        // Mock PushRequestInterface for redirectRequest dependency
        $pushRequestMock = $this->getFakeMock(\Buckaroo\Magento2\Api\Data\PushRequestInterface::class)
            ->addMethods(['getOriginalRequest', 'getData', 'hasPostData', 'hasAdditionalInformation'])
            ->onlyMethods(['getStatusCode'])
            ->getMockForAbstractClass();
        $pushRequestMock->method('getOriginalRequest')->willReturn([]);
        $pushRequestMock->method('getData')->willReturn(['test' => 'data']);
        $pushRequestMock->method('getStatusCode')->willReturn('490');
        $pushRequestMock->method('hasPostData')->willReturn(true);
        $pushRequestMock->method('hasAdditionalInformation')->willReturn(false);

        // Mock OrderRequestService
        $orderRequestServiceMock = $this->createMock(\Buckaroo\Magento2\Service\Push\OrderRequestService::class);
        $orderRequestServiceMock->method('getOrderByRequest')->willReturn($orderMock);

        // Mock LockManagerWrapper
        $lockManagerMock = $this->createMock(\Buckaroo\Magento2\Model\LockManagerWrapper::class);
        $lockManagerMock->method('lockOrder')->willReturn(true);

        // Mock CheckoutSession
        $checkoutSessionMock = $this->getFakeMock(\Magento\Checkout\Model\Session::class)
            ->addMethods([
                'setRestoreQuoteLastOrder',
                'getLastSuccessQuoteId', 'getLastQuoteId', 'getLastOrderId', 'getLastRealOrderId',
                'setLastSuccessQuoteId', 'setLastQuoteId', 'setLastOrderId', 'setLastRealOrderId',
                'setLastOrderStatus'
            ])
            ->onlyMethods(['restoreQuote'])
            ->getMock();
        $checkoutSessionMock->method('setRestoreQuoteLastOrder')->willReturnSelf();
        $checkoutSessionMock->method('getLastSuccessQuoteId')->willReturn(null);
        $checkoutSessionMock->method('getLastQuoteId')->willReturn(null);
        $checkoutSessionMock->method('getLastOrderId')->willReturn(null);
        $checkoutSessionMock->method('getLastRealOrderId')->willReturn(null);
        $checkoutSessionMock->method('setLastSuccessQuoteId')->willReturnSelf();
        $checkoutSessionMock->method('setLastQuoteId')->willReturnSelf();
        $checkoutSessionMock->method('setLastOrderId')->willReturnSelf();
        $checkoutSessionMock->method('setLastRealOrderId')->willReturnSelf();
        $checkoutSessionMock->method('setLastOrderStatus')->willReturnSelf();
        $checkoutSessionMock->method('restoreQuote')->willReturn(true);

        $instance = $this->getInstance([
            'redirectFactory' => $redirectFactoryMock,
            'context' => $contextMock,
            'accountConfig' => $configProviderMock,
            'order' => $orderMock,
            'transaction' => $transactionMock,
            'helper' => $helperMock,
            'redirectRequest' => $pushRequestMock,
            'orderRequestService' => $orderRequestServiceMock,
            'lockManager' => $lockManagerMock,
            'checkoutSession' => $checkoutSessionMock
        ]);
        $result = $instance->execute();
        // Basic assertion to ensure the method executes without throwing exceptions
        $this->assertNotNull($result);
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

        $request = $this->getFakeMock(RequestInterface::class)->onlyMethods(['getParams'])->getMockForAbstractClass();
        $request->method('getParams')->willReturn($params);

        $redirect = $this->getFakeMock(RedirectInterface::class)->getMockForAbstractClass();
        $redirect->expects($this->once())->method('redirect')->with($response, 'success_url', []);

        $messageManagerMock = $this->getFakeMock(ManagerInterface::class)
            ->onlyMethods(['addSuccessMessage'])
            ->getMockForAbstractClass();
        $messageManagerMock->method('addSuccessMessage');

        $contextMock = $this->getFakeMock(Context::class)
            ->onlyMethods(['getRequest', 'getRedirect', 'getResponse', 'getMessageManager'])
            ->getMock();
        $contextMock->method('getRequest')->willReturn($request);
        $contextMock->method('getRedirect')->willReturn($redirect);
        $contextMock->method('getResponse')->willReturn($response);
        $contextMock->method('getMessageManager')->willReturn($messageManagerMock);

        $configProviderMock = $this->getFakeMock(Account::class)
            ->onlyMethods(['getSuccessRedirect'])
            ->getMock();
        $configProviderMock->method('getSuccessRedirect')->willReturn('success_url');

        $payment = $this->getFakeMock(Payment::class)
            ->addMethods(['canProcessPostData', 'processCustomPostData'])
            ->onlyMethods(['getMethodInstance'])
            ->getMock();
        $payment->method('getMethodInstance')->willReturnSelf();
        $payment->method('canProcessPostData')->with($payment, $params)->willReturn(true);
        $payment->method('processCustomPostData')->with($payment, $params);

        $orderMock = $this->getFakeMock(Order::class)
            ->onlyMethods([
                'loadByIncrementId', 'canInvoice', 'getQuoteId',
                'setStatus', 'getEmailSent', 'getStore','getPayment', 'getId', 'save', 'getIncrementId'
])
            ->getMock();
        $orderMock->method('loadByIncrementId')->with(null)->willReturnSelf();
        $orderMock->method('getId')->willReturn(true);
        $orderMock->method('getIncrementId')->willReturn('TEST123');
        $orderMock->method('canInvoice')->willReturn(true);
        $orderMock->method('getQuoteId')->willReturn(1);
        $orderMock->method('setStatus')->willReturnSelf();
        $orderMock->method('save')->willReturnSelf();
        $orderMock->method('getEmailSent')->willReturn(1);
        $orderMock->method('getStore')->willReturnSelf();
        $orderMock->method('getPayment')->willReturn($payment);

        $orderStatusFactoryMock = $this->getFakeMock(OrderStatusFactory::class)->onlyMethods(['get'])->getMock();
        $orderStatusFactoryMock->method('get')
            ->with($this->anything(), $orderMock)
            ->willReturn('success');

        $helperMock = $this->getFakeMock(Data::class)->addMethods(['setRestoreQuoteLastOrder'])->getMock();

        // Add redirectFactory mock for Action controllers
        $redirectMock = $this->createMock(\Magento\Framework\Controller\Result\Redirect::class);
        $redirectFactoryMock = $this->createMock(\Magento\Framework\Controller\Result\RedirectFactory::class);
        $redirectFactoryMock->method('create')->willReturn($redirectMock);

        // Mock PushRequestInterface for redirectRequest dependency
        $pushRequestMock = $this->getFakeMock(\Buckaroo\Magento2\Api\Data\PushRequestInterface::class)
            ->addMethods(['getOriginalRequest', 'getData', 'hasPostData', 'hasAdditionalInformation'])
            ->onlyMethods(['getStatusCode'])
            ->getMockForAbstractClass();
        $pushRequestMock->method('getOriginalRequest')->willReturn([]);
        $pushRequestMock->method('getData')->willReturn(['test' => 'data']);
        $pushRequestMock->method('getStatusCode')->willReturn('190');
        $pushRequestMock->method('hasPostData')->willReturn(true);
        $pushRequestMock->method('hasAdditionalInformation')->willReturn(false);

        // Mock OrderRequestService
        $orderRequestServiceMock = $this->createMock(\Buckaroo\Magento2\Service\Push\OrderRequestService::class);
        $orderRequestServiceMock->method('getOrderByRequest')->willReturn($orderMock);

        // Mock LockManagerWrapper
        $lockManagerMock = $this->createMock(\Buckaroo\Magento2\Model\LockManagerWrapper::class);
        $lockManagerMock->method('lockOrder')->willReturn(true);

        // Mock CheckoutSession
        $checkoutSessionMock = $this->getFakeMock(\Magento\Checkout\Model\Session::class)
            ->addMethods([
                'setRestoreQuoteLastOrder',
                'getLastSuccessQuoteId', 'getLastQuoteId', 'getLastOrderId', 'getLastRealOrderId',
                'setLastSuccessQuoteId', 'setLastQuoteId', 'setLastOrderId', 'setLastRealOrderId',
                'setLastOrderStatus'
            ])
            ->onlyMethods(['restoreQuote'])
            ->getMock();
        $checkoutSessionMock->method('setRestoreQuoteLastOrder')->willReturnSelf();
        $checkoutSessionMock->method('getLastSuccessQuoteId')->willReturn(null);
        $checkoutSessionMock->method('getLastQuoteId')->willReturn(null);
        $checkoutSessionMock->method('getLastOrderId')->willReturn(null);
        $checkoutSessionMock->method('getLastRealOrderId')->willReturn(null);
        $checkoutSessionMock->method('setLastSuccessQuoteId')->willReturnSelf();
        $checkoutSessionMock->method('setLastQuoteId')->willReturnSelf();
        $checkoutSessionMock->method('setLastOrderId')->willReturnSelf();
        $checkoutSessionMock->method('setLastRealOrderId')->willReturnSelf();
        $checkoutSessionMock->method('setLastOrderStatus')->willReturnSelf();
        $checkoutSessionMock->method('restoreQuote')->willReturn(true);

        $instance = $this->getInstance([
            'redirectFactory' => $redirectFactoryMock,
            'context' => $contextMock,
            'accountConfig' => $configProviderMock,
            'order' => $orderMock,
            'helper' => $helperMock,
            'orderStatusFactory' => $orderStatusFactoryMock,
            'redirectRequest' => $pushRequestMock,
            'orderRequestService' => $orderRequestServiceMock,
            'lockManager' => $lockManagerMock,
            'checkoutSession' => $checkoutSessionMock
        ]);
        $result = $instance->execute();
        // Basic assertion to ensure the method executes without throwing exceptions
        $this->assertNotNull($result);
    }
}
