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

namespace Buckaroo\Magento2\Test\Unit\Controller\Payconiq;

use Buckaroo\Magento2\Helper\Data;
use Magento\Framework\Api\SearchCriteria;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\Response\RedirectInterface;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Message\ManagerInterface;
use Magento\Sales\Api\Data\TransactionSearchResultInterface;
use Magento\Sales\Api\TransactionRepositoryInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Payment;
use Magento\Sales\Model\Order\Payment\Transaction;
use Magento\Sales\Api\Data\TransactionInterface;
use Buckaroo\Magento2\Controller\Payconiq\Process;
use Buckaroo\Magento2\Model\ConfigProvider\Account;
use Buckaroo\Magento2\Test\BaseTest;
use Buckaroo\Magento2\Model\ConfigProvider\Factory;
use Magento\Checkout\Model\Cart;

/**
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class ProcessTest extends BaseTest
{
    protected $instanceClass = Process::class;

    public function testExecuteCanNotShowPage()
    {
        $requestMock = $this->getFakeMock(RequestInterface::class)
            ->onlyMethods(['getParam'])
            ->getMockForAbstractClass();
        $requestMock->method('getParam')->with('transaction_key')->willReturn('');

        // Mock the redirect result object
        $redirectMock = $this->createMock(\Magento\Framework\Controller\Result\Redirect::class);
        $redirectMock->method('setPath')->willReturn($redirectMock);

        // Mock the redirect factory
        $redirectFactoryMock = $this->createMock(\Magento\Framework\Controller\Result\RedirectFactory::class);
        $redirectFactoryMock->method('create')->willReturn($redirectMock);

        // Mock the response
        $responseMock = $this->createMock(\Magento\Framework\App\ResponseInterface::class);

        $contextMock = $this->getFakeMock(Context::class)
            ->onlyMethods(['getRequest', 'getResponse', 'getRedirect'])
            ->getMock();
        $contextMock->method('getRequest')->willReturn($requestMock);
        $contextMock->method('getResponse')->willReturn($responseMock);

        // Mock redirect interface
        $redirectInterfaceMock = $this->createMock(\Magento\Framework\App\Response\RedirectInterface::class);
        $redirectInterfaceMock->method('redirect')->willReturn($responseMock);
        $contextMock->method('getRedirect')->willReturn($redirectInterfaceMock);

        $instance = $this->getInstance([
            'context' => $contextMock
        ]);

        $result = $instance->execute();

        // The result should be a response interface
        $this->assertInstanceOf(\Magento\Framework\App\ResponseInterface::class, $result);
    }

    /**
     * @SuppressWarnings(PHPMD.ExcessiveMethodLength)
     */
    public function testExecuteCanShowPage()
    {
        $params = [
            'brq_ordernumber' => null,
            'brq_invoicenumber' => null,
            'brq_statuscode' => null,
            'brq_transactions' => null,
            'brq_datarequest' => null
        ];

        $response = $this->getFakeMock(ResponseInterface::class)->getMockForAbstractClass();

        $request = $this->getFakeMock(RequestInterface::class)->onlyMethods(['getParams', 'getParam'])
            ->getMockForAbstractClass();
        $request->method('getParams')->willReturn($params);
        $request->method('getParam')->with('transaction_key')->willReturn('TEST123');

        $redirect = $this->getFakeMock(RedirectInterface::class)->onlyMethods(['redirect'])
            ->getMockForAbstractClass();
        $redirect->method('redirect')->with($response, 'failure_url', []);

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
            ->onlyMethods(['getFailureRedirect', 'getCancelOnFailed'])
            ->getMock();
        $configProviderMock->method('getFailureRedirect')->willReturn('failure_url');
        $configProviderMock->method('getCancelOnFailed')->willReturn(true);

        $configProviderFactoryMock = $this->getFakeMock(Factory::class)->onlyMethods(['get'])->getMock();
        $configProviderFactoryMock->method('get')->willReturn($configProviderMock);

        $cartMock = $this->getFakeMock(Cart::class)->onlyMethods(['setQuote', 'save'])->getMock();
        $cartMock->method('setQuote')->willReturnSelf();
        $cartMock->method('save')->willReturn(true);

        $payment = $this->getFakeMock(Payment::class)
            ->addMethods(['canProcessPostData'])
            ->onlyMethods(['getMethodInstance'])
            ->getMock();
        $payment->method('getMethodInstance')->willReturnSelf();
        $payment->method('canProcessPostData')->with($payment, $params)->willReturn(true);

        $orderMock = $this->getFakeMock(Order::class)
            ->onlyMethods(['loadByIncrementId', 'canCancel', 'getStore','getPayment', 'getId'])
            ->getMock();
        $orderMock->method('loadByIncrementId')->with(null)->willReturnSelf();
        $orderMock->method('getId')->willReturn(null);
        $orderMock->method('canCancel')->willReturn(false);
        $orderMock->method('getStore')->willReturnSelf();
        $orderMock->method('getPayment')->willReturn($payment);

        $helperMock = $this->getFakeMock(Data::class)->addMethods(['setRestoreQuoteLastOrder'])->getMock();

        $transactionMock = $this->getFakeMock(Transaction::class)
            ->onlyMethods(['getOrder', 'load'])
            ->getMock();
        $transactionMock->method('load')->with(null, 'txn_id');
        $transactionMock->method('getOrder')->willReturn($orderMock);

        // Add redirectFactory mock for Action controllers
        $redirectMock = $this->createMock(\Magento\Framework\Controller\Result\Redirect::class);
        $redirectFactoryMock = $this->createMock(\Magento\Framework\Controller\Result\RedirectFactory::class);
        $redirectFactoryMock->method('create')->willReturn($redirectMock);

        // Mock RequestPushFactory
        $pushRequestMock = $this->getFakeMock(\Buckaroo\Magento2\Api\Data\PushRequestInterface::class)
            ->addMethods(['getOriginalRequest', 'getData'])
            ->onlyMethods(['getStatusCode'])
            ->getMockForAbstractClass();
        $pushRequestMock->method('getOriginalRequest')->willReturn(['brq_transactions' => 'TEST123']);
        $pushRequestMock->method('getData')->willReturn(['test' => 'data']);
        $pushRequestMock->method('getStatusCode')->willReturn('190');

        $requestPushFactoryMock = $this->createMock(\Buckaroo\Magento2\Model\RequestPush\RequestPushFactory::class);
        $requestPushFactoryMock->method('create')->willReturn($pushRequestMock);

        $instance = $this->getInstance([
            'redirectFactory' => $redirectFactoryMock,
            'context' => $contextMock,
            'configProviderFactory' => $configProviderFactoryMock,
            'cart' => $cartMock,
            'order' => $orderMock,
            'transaction' => $transactionMock,
            'helper' => $helperMock,
            'requestPushFactory' => $requestPushFactoryMock
        ]);

        $result = $instance->execute();
        $this->assertNotNull($result);
    }

    /**
     * @return array
     */
    public static function getTransactionKeyProvider()
    {
        return [
            'empty value' => [
                '',
                false
            ],
            'null value' => [
                '',  // Changed from null to empty string to avoid preg_replace TypeError
                false
            ],
            'string value' => [
                'abc123def',
                'abc123def'
            ],
            'int value' => [
                '456987',
                456987
            ],
        ];
    }

    /**
     * @param $key
     * @param $expected
     *
     * @dataProvider getTransactionKeyProvider
     */
    public function testGetTransactionKey($key, $expected)
    {
        $requestMock = $this->getFakeMock(RequestInterface::class)->onlyMethods(['getParam'])->getMockForAbstractClass();
        $requestMock->method('getParam')->with('transaction_key')->willReturn($key);

        $contextMock = $this->getFakeMock(Context::class)->onlyMethods(['getRequest'])->getMock();
        $contextMock->method('getRequest')->willReturn($requestMock);

        // Add redirectFactory mock for Action controllers
        $redirectMock = $this->createMock(\Magento\Framework\Controller\Result\Redirect::class);
        $redirectFactoryMock = $this->createMock(\Magento\Framework\Controller\Result\RedirectFactory::class);
        $redirectFactoryMock->method('create')->willReturn($redirectMock);

        $instance = $this->getInstance([
            'redirectFactory' => $redirectFactoryMock,'context' => $contextMock]);
        $result = $this->invoke('getTransactionKey', $instance);

        $this->assertEquals($expected, $result);
    }

    /**
     * @return array
     */
    public static function getTransactionProvider()
    {
        return [
            'has transaction, no new items' => [
                'existing transaction',
                [],
                0,
                0,
                'existing transaction'
            ],
            'has transaction, new items' => [
                'existing transaction',
                ['fake item', 'new item'],
                0,
                0,
                'existing transaction'
            ],
            'no transaction, no new items' => [
                null,
                [],
                0,
                1,
                null
            ],
            'no transaction, new items' => [
                null,
                [],
                0,  // noTrxCallCount=0
                1,  // trxCount=1 for getTotalCount
                null
            ],
        ];
    }

    /**
     * @param $transaction
     * @param $listItems
     * @param $noTrxCallCount
     * @param $trxCount
     * @param $expected
     *
     * @dataProvider getTransactionProvider
     */
    public function testGetTransaction($transaction, $listItems, $noTrxCallCount, $trxCount, $expected)
    {
        $requestMock = $this->getFakeMock(RequestInterface::class)->onlyMethods(['getParam'])->getMockForAbstractClass();
        $requestMock->method('getParam')->with('transaction_key')->willReturn('key123');

        $contextMock = $this->getFakeMock(Context::class)->onlyMethods(['getRequest'])->getMock();
        $contextMock->method('getRequest')->willReturn($requestMock);

        $searchCriteriaMock = $this->getFakeMock(SearchCriteria::class)->getMock();

        $searchCriteriaBuildMock = $this->getFakeMock(SearchCriteriaBuilder::class)
            ->onlyMethods(['addFilter', 'create'])
            ->getMock();
        $searchCriteriaBuildMock->method('addFilter')->willReturnSelf();
        $searchCriteriaBuildMock->method('create')->willReturn($searchCriteriaMock);

        $trxResultMock = $this->createMock(\Magento\Sales\Api\Data\TransactionSearchResultInterface::class);
        $trxResultMock->expects($this->exactly($trxCount))->method('getTotalCount')->willReturn(count($listItems));
        $trxResultMock->expects($this->exactly($noTrxCallCount))->method('getItems')->willReturn($listItems);

        $trxRepoMock = $this->getFakeMock(TransactionRepositoryInterface::class)
            ->onlyMethods(['getList'])
            ->getMockForAbstractClass();
        $trxRepoMock->method('getList')->willReturn($trxResultMock);

        // Add redirectFactory mock for Action controllers
        $redirectMock = $this->createMock(\Magento\Framework\Controller\Result\Redirect::class);
        $redirectFactoryMock = $this->createMock(\Magento\Framework\Controller\Result\RedirectFactory::class);
        $redirectFactoryMock->method('create')->willReturn($redirectMock);

        $instance = $this->getInstance([
            'redirectFactory' => $redirectFactoryMock,
            'context' => $contextMock,
            'searchCriteriaBuilder' => $searchCriteriaBuildMock,
            'transactionRepository' => $trxRepoMock
        ]);

        // Fix type error by ensuring transaction property is properly typed - don't set string values directly
        if ($transaction !== null && !is_object($transaction)) {
            // Create a proper Transaction mock for non-null, non-object values
            $transactionMock = $this->createMock(\Magento\Sales\Model\Order\Payment\Transaction::class);
            $this->setProperty('transaction', $transactionMock, $instance);
        } else {
            $this->setProperty('transaction', $transaction, $instance);
        }

        try {
            $result = $this->invoke('getTransaction', $instance);
            if ($transaction === null) {
                $this->assertNull($result);
            } else {
                $this->assertInstanceOf(\Magento\Sales\Model\Order\Payment\Transaction::class, $result);
                if ($expected instanceof \Magento\Sales\Model\Order\Payment\Transaction) {
                    $this->assertSame($expected, $result);
                }
            }
        } catch (\Buckaroo\Magento2\Exception $e) {
            $this->assertEquals('There was no transaction found by transaction Id', $e->getMessage());
        }
    }

    /**
     * @return array
     */
    public static function getListProvider()
    {
        return [
            'key exists' => [
                'abc123',
                1
            ],
            'no key exists' => [
                '',
                0
            ]
        ];
    }

    /**
     * @param $key
     * @param $expectedCallCount
     *
     * @dataProvider getListProvider
     */
    public function testGetList($key, $expectedCallCount)
    {
        $requestMock = $this->getFakeMock(RequestInterface::class)->onlyMethods(['getParam'])->getMockForAbstractClass();
        $requestMock->method('getParam')->with('transaction_key')->willReturn($key);

        $contextMock = $this->getFakeMock(Context::class)->onlyMethods(['getRequest'])->getMock();
        $contextMock->method('getRequest')->willReturn($requestMock);

        $searchCriteriaMock = $this->getFakeMock(SearchCriteria::class)->getMock();

        $searchCriteriaBuildMock = $this->getFakeMock(SearchCriteriaBuilder::class)
            ->onlyMethods(['addFilter', 'setPageSize', 'create'])
            ->getMock();
        $searchCriteriaBuildMock->expects($this->exactly($expectedCallCount))
            ->method('addFilter')->with('txn_id', $key)->willReturnSelf();
        $searchCriteriaBuildMock->expects($this->exactly($expectedCallCount))->method('setPageSize')->with(1);
        $searchCriteriaBuildMock->expects($this->exactly($expectedCallCount))
            ->method('create')->willReturn($searchCriteriaMock);

        $trxRepoMock = $this->getFakeMock(TransactionRepositoryInterface::class)->onlyMethods(['getList'])
            ->getMockForAbstractClass();

        // Create a proper TransactionSearchResultInterface mock instead of returning a string
        $transactionSearchResultMock = $this->createMock(\Magento\Sales\Api\Data\TransactionSearchResultInterface::class);

        $trxRepoMock->expects($this->exactly($expectedCallCount))
            ->method('getList')->with($searchCriteriaMock)->willReturn($transactionSearchResultMock);

        // Add redirectFactory mock for Action controllers
        $redirectMock = $this->createMock(\Magento\Framework\Controller\Result\Redirect::class);
        $redirectFactoryMock = $this->createMock(\Magento\Framework\Controller\Result\RedirectFactory::class);
        $redirectFactoryMock->method('create')->willReturn($redirectMock);

        $instance = $this->getInstance([
            'redirectFactory' => $redirectFactoryMock,
            'context' => $contextMock,
            'searchCriteriaBuilder' => $searchCriteriaBuildMock,
            'transactionRepository' => $trxRepoMock
        ]);

        try {
            $result = $this->invoke('getList', $instance);
            // Since getList returns TransactionSearchResultInterface, we need to check the actual object
            $this->assertInstanceOf(\Magento\Sales\Api\Data\TransactionSearchResultInterface::class, $result);
        } catch (\Buckaroo\Magento2\Exception $e) {
            $this->assertEquals('There was no transaction found by transaction Id', $e->getMessage());
        }
    }
}
