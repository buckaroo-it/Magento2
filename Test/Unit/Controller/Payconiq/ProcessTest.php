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
use Magento\Sales\Model\Order\Payment\Transaction;
use Buckaroo\Magento2\Controller\Payconiq\Process;
use Buckaroo\Magento2\Model\ConfigProvider\Account;
use Buckaroo\Magento2\Test\BaseTest;
use Buckaroo\Magento2\Model\ConfigProvider\Factory;

/**
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
    class ProcessTest extends BaseTest
{
    protected $instanceClass = Process::class;

    public function testExecuteCanNotShowPage()
    {
        $requestMock = $this->getFakeMock(RequestInterface::class)
            ->setMethods(['getParam', 'initForward', 'setActionName', 'setDispatched'])
            ->getMockForAbstractClass();
        $requestMock->expects($this->once())->method('getParam')->with('transaction_key')->willReturn(null);
        $requestMock->expects($this->once())->method('initForward');
        $requestMock->expects($this->once())->method('setActionName')->with('defaultNoRoute');
        $requestMock->expects($this->once())->method('setDispatched')->with(false);

        $contextMock = $this->getFakeMock(Context::class)->setMethods(['getRequest'])->getMock();
        $contextMock->expects($this->once())->method('getRequest')->willReturn($requestMock);

        $instance = $this->getInstance(['context' => $contextMock]);

        $instance->execute();
    }

    public function testExecuteCanShowPage()
    {
        $params = [
            'brq_ordernumber' => null,
            'brq_invoicenumber' => null,
            'brq_statuscode' => null,
            'brq_transactions' => null,
            'brq_datarequest' => null
        ];
        $this->markTestIncomplete(
            'This test needs to be reviewed.'
        );
        $response = $this->getFakeMock(ResponseInterface::class)->getMockForAbstractClass();

        $request = $this->getFakeMock(RequestInterface::class)->setMethods(['getParams'])
            ->getMockForAbstractClass();
        $request->expects($this->atLeastOnce())->method('getParams')->willReturn($params);

        $redirect = $this->getFakeMock(RedirectInterface::class)->setMethods(['redirect'])
            ->getMockForAbstractClass();
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

        $helperMock = $this->getFakeMock(Data::class)->setMethods(['setRestoreQuoteLastOrder'])->getMock();

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
     * @return array
     */
    public function getTransactionKeyProvider()
    {
        return [
            'empty value' => [
                '',
                false
            ],
            'null value' => [
                null,
                false
            ],
            'string value' => [
                'abc123def',
                'abc123def'
            ],
            'int value' => [
                456987,
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
        $requestMock = $this->getFakeMock(RequestInterface::class)->setMethods(['getParam'])->getMockForAbstractClass();
        $requestMock->expects($this->once())->method('getParam')->with('transaction_key')->willReturn($key);

        $contextMock = $this->getFakeMock(Context::class)->setMethods(['getRequest'])->getMock();
        $contextMock->expects($this->once())->method('getRequest')->willReturn($requestMock);

        $instance = $this->getInstance(['context' => $contextMock]);
        $result = $this->invoke('getTransactionKey', $instance);

        $this->assertEquals($expected, $result);
    }

    /**
     * @return array
     */
    public function getTransactionProvider()
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
                ['some item', 'more items'],
                1,
                1,
                'some item'
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
        $requestMock = $this->getFakeMock(RequestInterface::class)->setMethods(['getParam'])->getMockForAbstractClass();
        $requestMock->method('getParam')->with('transaction_key')->willReturn('key123');

        $contextMock = $this->getFakeMock(Context::class)->setMethods(['getRequest'])->getMock();
        $contextMock->method('getRequest')->willReturn($requestMock);

        $searchCriteriaMock = $this->getFakeMock(SearchCriteria::class)->getMock();

        $searchCriteriaBuildMock = $this->getFakeMock(SearchCriteriaBuilder::class)
            ->setMethods(['addFilter', 'create'])
            ->getMock();
        $searchCriteriaBuildMock->method('addFilter')->willReturnSelf();
        $searchCriteriaBuildMock->method('create')->willReturn($searchCriteriaMock);

        $trxResultMock = $this->getFakeMock(TransactionSearchResultInterface::class)
            ->setMethods(['getTotalCount', 'getItems'])
            ->getMockForAbstractClass();
        $trxResultMock->expects($this->exactly($trxCount))->method('getTotalCount')->willReturn(count($listItems));
        $trxResultMock->expects($this->exactly($noTrxCallCount))->method('getItems')->willReturn($listItems);

        $trxRepoMock = $this->getFakeMock(TransactionRepositoryInterface::class)
            ->setMethods(['getList'])
            ->getMockForAbstractClass();
        $trxRepoMock->method('getList')->willReturn($trxResultMock);

        $instance = $this->getInstance([
            'context' => $contextMock,
            'searchCriteriaBuilder' => $searchCriteriaBuildMock,
            'transactionRepository' => $trxRepoMock
        ]);

        $this->setProperty('transaction', $transaction, $instance);

        try {
            $result = $this->invoke('getTransaction', $instance);
            $this->assertEquals($expected, $result);
        } catch (\Buckaroo\Magento2\Exception $e) {
            $this->assertEquals('There was no transaction found by transaction Id', $e->getMessage());
        }
    }

    /**
     * @return array
     */
    public function getListProvider()
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
        $requestMock = $this->getFakeMock(RequestInterface::class)->setMethods(['getParam'])->getMockForAbstractClass();
        $requestMock->expects($this->once())->method('getParam')->with('transaction_key')->willReturn($key);

        $contextMock = $this->getFakeMock(Context::class)->setMethods(['getRequest'])->getMock();
        $contextMock->expects($this->once())->method('getRequest')->willReturn($requestMock);

        $searchCriteriaMock = $this->getFakeMock(SearchCriteria::class)->getMock();

        $searchCriteriaBuildMock = $this->getFakeMock(SearchCriteriaBuilder::class)
            ->setMethods(['addFilter', 'setPageSize', 'create'])
            ->getMock();
        $searchCriteriaBuildMock->expects($this->exactly($expectedCallCount))
            ->method('addFilter')->with('txn_id', $key)->willReturnSelf();
        $searchCriteriaBuildMock->expects($this->exactly($expectedCallCount))->method('setPageSize')->with(1);
        $searchCriteriaBuildMock->expects($this->exactly($expectedCallCount))
            ->method('create')->willReturn($searchCriteriaMock);

        $trxRepoMock = $this->getFakeMock(TransactionRepositoryInterface::class)->setMethods(['getList'])
            ->getMockForAbstractClass();
        $trxRepoMock->expects($this->exactly($expectedCallCount))
            ->method('getList')->with($searchCriteriaMock)->willReturn('list of items');

        $instance = $this->getInstance([
            'context' => $contextMock,
            'searchCriteriaBuilder' => $searchCriteriaBuildMock,
            'transactionRepository' => $trxRepoMock
        ]);

        try {
            $result = $this->invoke('getList', $instance);
            $this->assertEquals('list of items', $result);
        } catch (\Buckaroo\Magento2\Exception $e) {
            $this->assertEquals('There was no transaction found by transaction Id', $e->getMessage());
        }
    }
}
