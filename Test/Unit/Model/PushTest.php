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
namespace TIG\Buckaroo\Test\Unit\Model;

use Magento\Directory\Model\Currency;
use Magento\Framework\Webapi\Rest\Request;
use Magento\Payment\Model\MethodInterface;
use Magento\Sales\Api\Data\TransactionInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Email\Sender\OrderSender;
use Magento\Sales\Model\Order\Invoice;
use Magento\Sales\Model\Order\Payment;
use TIG\Buckaroo\Model\ConfigProvider\Account;
use TIG\Buckaroo\Model\Method\AbstractMethod;
use TIG\Buckaroo\Model\Method\Giftcards;
use TIG\Buckaroo\Logging\Log;
use TIG\Buckaroo\Exception;
use TIG\Buckaroo\Model\Push;

class PushTest extends \TIG\Buckaroo\Test\BaseTest
{
    protected $instanceClass = Push::class;

    /**
     * @return array
     */
    public function giftcardPartialPaymentProvider()
    {
        return [
            'processed partial giftcard payment' => [
                Giftcards::PAYMENT_METHOD_CODE,
                5,
                2,
                'abc',
                true
            ],
            'incorrect method code' => [
                'fake_method_code',
                4,
                1,
                'def',
                false
            ],
            'push amount equals order amount' => [
                Giftcards::PAYMENT_METHOD_CODE,
                3,
                6,
                'ghi',
                false
            ],
            'no related transaction key' => [
                Giftcards::PAYMENT_METHOD_CODE,
                8,
                7,
                null,
                false
            ],
        ];
    }

    /**
     * @return array
     */
    public function getPostDataProvider()
    {
        return [
            'valid post data' => [
                [],
                ['brq_INVOICENUMBER' => '0001', 'brq_STATUSCODE' => 190],
                ['brq_INVOICENUMBER' => '0001', 'brq_STATUSCODE' => 190],
                ['brq_invoicenumber' => '0001', 'brq_statuscode' => 190]
            ],
            'sid in post' => [
                ['SID' => '123ABC'],
                ['brq_INVOICENUMBER' => '0001', 'brq_STATUSCODE' => 190],
                ['brq_INVOICENUMBER' => '0001', 'brq_STATUSCODE' => 190],
                ['brq_invoicenumber' => '0001', 'brq_statuscode' => 190]
            ],
            'sid in get' => [
                [],
                ['brq_INVOICENUMBER' => '0001', 'SID' => '123ABC', 'brq_STATUSCODE' => 190],
                ['brq_INVOICENUMBER' => '0001', 'brq_STATUSCODE' => 190],
                ['brq_invoicenumber' => '0001', 'brq_statuscode' => 190]
            ],
            'mixed post and get data' => [
                ['brq_CURRENCY' => 'EUR', 'getData' => 'DEF456'],
                ['SID' => '789GHI', 'brq_INVOICENUMBER' => '0001', 'brq_STATUSCODE' => 190],
                ['brq_INVOICENUMBER' => '0001', 'brq_STATUSCODE' => 190],
                ['brq_invoicenumber' => '0001', 'brq_statuscode' => 190]
            ]
        ];
    }

    /**
     * @param $getData
     * @param $postData
     * @param $expectedPost
     * @param $expectedPostLowerCase
     *
     * @dataProvider getPostDataProvider
     */
    public function testGetPostData($getData, $postData, $expectedPost, $expectedPostLowerCase)
    {
        $requestMock = $this->getFakeMock(Request::class)->setMethods(null)->getMock();
        $requestMock->setQueryValue($getData);
        $requestMock->setPostValue($postData);

        $instance = $this->getInstance(['request' => $requestMock]);
        $this->invoke('getPostData', $instance);
        $this->assertEquals($expectedPost, $instance->originalPostData);
        $this->assertEquals($expectedPostLowerCase, $instance->postData);
    }

    /**
     * @return array
     */
    public function loadOrderProvider()
    {
        return [
            'by invoicenumber' => [
                ['brq_invoicenumber' => '#1234'],
                321
            ],
            'by ordernumber' => [
                ['brq_ordernumber' => '#5678'],
                765
            ],
        ];
    }

    /**
     * @param $postData
     * @param $orderId
     *
     * @dataProvider loadOrderProvider
     */
    public function testLoadOrder($postData, $orderId)
    {
        $orderMock = $this->getFakeMock(Order::class)->setMethods(['loadByIncrementId'])->getMock();
        $orderMock->expects($this->once())
            ->method('loadByIncrementId')
            ->willReturnCallback(
                function () use ($orderMock, $orderId) {
                    $orderMock->setId($orderId);
                }
            );

        $instance = $this->getInstance(['order' => $orderMock]);
        $instance->postData = $postData;
        $this->invoke('loadOrder', $instance);
        $this->assertEquals($orderId, $orderMock->getId());
    }

    public function testLoadOrderWillThrowException()
    {
        $debuggerMock = $this->getFakeMock(Log::class)->setMethods(['addDebug', '__destruct'])->getMock();
        $debuggerMock->expects($this->once())
            ->method('addDebug')
            ->with('Order could not be loaded by brq_invoicenumber or brq_ordernumber');


        $transactionMock = $this->getFakeMock(TransactionInterface::class)
            ->setMethods(['load', 'getOrder'])
            ->getMockForAbstractClass();
        $transactionMock->expects($this->once())->method('load')->with('', 'txn_id');
        $transactionMock->expects($this->once())->method('getOrder')->willReturn(null);

        $instance = $this->getInstance(['transaction' => $transactionMock, 'logging' => $debuggerMock]);

        $this->setExpectedException(Exception::class, 'There was no order found by transaction Id');
        $this->invoke('loadOrder', $instance);
    }

    public function getTransactionKeyProvider()
    {
        return [
            'no key' => [
                [
                    'brq_some_key' => 'abc',
                    'brq_amount' => '1.23'
                ],
                ''
            ],
            'transaction key' => [
                [
                    'brq_transactions' => '456def',
                    'brq_comment' => 'Transaction Comment'
                ],
                '456def'
            ],
            'datarequest key' => [
                [
                    'brq_status' => 'success',
                    'brq_datarequest' => 'ghi789'
                ],
                'ghi789'
            ]
        ];
    }

    /**
     * @param $postData
     * @param $expected
     *
     * @dataProvider getTransactionKeyProvider
     */
    public function testGetTransactionKey($postData, $expected)
    {
        $instance = $this->getInstance();
        $this->setProperty('postData', $postData, $instance);

        $result = $this->invoke('getTransactionKey', $instance);
        $this->assertEquals($expected, $result);
    }

    /**
     * @return array
     */
    public function getTransactionTypeProvider()
    {
        return [
            'invalid type' => [
                ['brq_service_creditmanagement3_invoicekey' => 'key'],
                null,
                false
            ],
            'invoice type' => [
                ['brq_invoicekey' => 'send key', 'brq_schemekey' => 'scheme key'],
                'saved key',
                Push::BUCK_PUSH_TYPE_INVOICE
            ],
            'datarequest type' => [
                ['brq_datarequest' => 'request push'],
                null,
                Push::BUCK_PUSH_TYPE_DATAREQUEST
            ],
            'transaction type' => [
                [],
                null,
                Push::BUCK_PUSH_TYPE_TRANSACTION
            ],
        ];
    }

    /**
     * @param $methodCode
     * @param $orderAmount
     * @param $pushAmount
     * @param $relatedTransaction
     * @param $expected
     *
     * @dataProvider giftcardPartialPaymentProvider
     */
    public function testGiftcardPartialPayment($methodCode, $orderAmount, $pushAmount, $relatedTransaction, $expected)
    {
        $postData = [
            'brq_amount' => $pushAmount,
            'brq_relatedtransaction_partialpayment' => $relatedTransaction
        ];

        $paymentMock = $this->getFakeMock(Payment::class)
            ->setMethods(['getMethod', 'setAdditionalInformation', 'getAdditionalInformation'])
            ->getMock();
        $paymentMock->expects($this->once())->method('getMethod')->willReturn($methodCode);
        $paymentMock->method('getAdditionalInformation')
            ->with(AbstractMethod::BUCKAROO_ALL_TRANSACTIONS)->willReturn([]);
        $paymentMock->method('setAdditionalInformation')
            ->withConsecutive(
                [AbstractMethod::BUCKAROO_ORIGINAL_TRANSACTION_KEY_KEY],
                [AbstractMethod::BUCKAROO_ALL_TRANSACTIONS]
            );

        $orderMock = $this->getFakeMock(Order::class)->setMethods(['getPayment', 'getGrandTotal'])->getMock();
        $orderMock->expects($this->atLeastOnce())->method('getPayment')->willReturn($paymentMock);
        $orderMock->method('getGrandTotal')->willReturn($orderAmount);

        $instance = $this->getInstance();
        $instance->order = $orderMock;
        $instance->postData = $postData;

        $result = $this->invoke('giftcardPartialPayment', $instance);

        $this->assertEquals($expected, $result);
    }

    /**
     * @param $postData
     * @param $savedInvoiceKey
     * @param $expected
     *
     * @dataProvider getTransactionTypeProvider
     */
    public function testGetTransactionType($postData, $savedInvoiceKey, $expected)
    {
        $orderMock = $this->getFakeMock(Order::class)
            ->setMethods(['getPayment', 'getAdditionalInformation'])
            ->getMock();
        $orderMock->method('getPayment')->willReturnSelf();
        $orderMock->method('getAdditionalInformation')->with('buckaroo_cm3_invoice_key')->willReturn($savedInvoiceKey);

        $instance = $this->getInstance(['order' => $orderMock]);
        $instance->postData = $postData;

        $result = $instance->getTransactionType();

        $this->assertEquals($expected, $result);
    }

    /**
     * @return array
     */
    public function sendCm3ConfirmationMailProvider()
    {
        return [
            'mail send via account config' => [
                false,
                true,
                false,
                ['brq_invoicestatuscode' => 10],
                1
            ],
            'mail send via method config' => [
                true,
                false,
                false,
                ['brq_invoicestatuscode' => 10],
                1
            ],
            'mail already sent' => [
                false,
                true,
                true,
                ['brq_invoicestatuscode' => 10],
                0
            ],
            'incorrect post status code' => [
                false,
                true,
                false,
                ['brq_invoicestatuscode' => 5],
                0
            ],
            'incorrect post parameter' => [
                false,
                true,
                false,
                ['brq_invoicekey' => 10],
                0
            ],
            'configuration disabled' => [
                false,
                false,
                false,
                ['brq_invoicestatuscode' => 10],
                0
            ],
        ];
    }

    /**
     * @param $configData
     * @param $accountConfig
     * @param $emailSent
     * @param $postData
     * @param $sendTimesCalled
     *
     * @dataProvider sendCm3ConfirmationMailProvider
     */
    public function testSendCm3ConfirmationMail($configData, $accountConfig, $emailSent, $postData, $sendTimesCalled)
    {
        $methodMock = $this->getMockBuilder(AbstractMethod::class)
            ->disableOriginalConstructor()
            ->setMethods(['getConfigData'])
            ->getMockForAbstractClass();
        $methodMock->method('getConfigData')->with('order_email', 1)->willReturn($configData);

        $orderMock = $this->getFakeMock(Order::class)
            ->setMethods(['getStore', 'getPayment', 'getMethodInstance', 'getEmailSent'])
            ->getMock();
        $orderMock->expects($this->once())->method('getStore')->willReturn(1);
        $orderMock->expects($this->once())->method('getPayment')->willReturnSelf();
        $orderMock->expects($this->once())->method('getMethodInstance')->willReturn($methodMock);
        $orderMock->expects($this->once())->method('getEmailSent')->willReturn($emailSent);

        $configAccountMock = $this->getFakeMock(Account::class)->setMethods(['getOrderConfirmationEmail'])->getMock();
        $configAccountMock->expects($this->once())->method('getOrderConfirmationEmail')->with(1)->willReturn($accountConfig);

        $orderSenderMock = $this->getFakeMock(OrderSender::class)->setMethods(['send'])->getMock();
        $orderSenderMock->expects($this->exactly($sendTimesCalled))->method('send')->with($orderMock);

        $instance = $this->getInstance([
            'order' => $orderMock,
            'orderSender' => $orderSenderMock,
            'configAccount' => $configAccountMock
        ]);
        $instance->postData = $postData;

        $this->invoke('sendCm3ConfirmationMail', $instance);
    }

    /**
     * @param $state
     *
     * @dataProvider processPendingPaymentPushDataProvider
     */
    public function testProcessPendingPaymentPush($state)
    {
        $message = 'testMessage';
        $status = 'testStatus';

        $expectedDescription = 'Payment push status : '.$message;

        $pendingPaymentState = Order::STATE_PROCESSING;

        $orderMock = $this->getFakeMock(Order::class)
            ->setMethods([
                'getState', 'getStore', 'getPayment', 'getMethodInstance', 'getEmailSent', 'addStatusHistoryComment'
            ])
            ->getMock();
        $orderMock->expects($this->once())->method('getState')->willReturn($state);
        $orderMock->expects($this->once())->method('getStore')->willReturn(0);
        $orderMock->expects($this->once())->method('getPayment')->willReturnSelf();
        $orderMock->expects($this->once())->method('getMethodInstance')->willReturnSelf();
        $orderMock->expects($this->once())->method('getEmailSent')->willReturn(true);

        if ($state == $pendingPaymentState) {
            $orderMock->expects($this->once())->method('addStatusHistoryComment')->with($expectedDescription, $status);
        } else {
            $orderMock->expects($this->once())->method('addStatusHistoryComment')->with($expectedDescription);
        }

        $instance = $this->getInstance();
        $instance->order = $orderMock;

        $result = $instance->processPendingPaymentPush($status, $message);

        $this->assertTrue($result);
    }

    public function processPendingPaymentPushDataProvider()
    {
        return [
            [
                Order::STATE_PROCESSING,
            ],
            [
                Order::STATE_NEW,
            ],
        ];
    }

    /**
     * @param $state
     * @param $canCancel
     * @param $cancelOnFailed
     *
     * @dataProvider processFailedPushDataProvider
     */
    public function testProcessFailedPush($state, $canCancel, $cancelOnFailed)
    {
        $message = 'testMessage';
        $status = 'testStatus';

        $expectedDescription = 'Payment status : '.$message;

        $canceledPaymentState = Order::STATE_CANCELED;

        $configAccountMock = $this->getFakeMock(Account::class)->setMethods(['getCancelOnFailed'])->getMock();
        $configAccountMock->expects($this->once())->method('getCancelOnFailed')->willReturn($cancelOnFailed);

        $orderMock = $this->getFakeMock(Order::class)
            ->setMethods(['getState', 'getStore', 'addStatusHistoryComment', 'canCancel', 'getPayment', 'cancel', 'save'])
            ->getMock();
        $orderMock->expects($this->atLeastOnce())->method('getState')->willReturn($state);
        $orderMock->expects($this->once())->method('getStore')->willReturnSelf();

        $addHistoryCommentExpects = $orderMock->expects($this->once());
        $addHistoryCommentExpects->method('addStatusHistoryComment');

        if ($state == $canceledPaymentState) {
            $addHistoryCommentExpects->with($expectedDescription, $status);
        } else {
            $addHistoryCommentExpects->with($expectedDescription);
        }

        if ($cancelOnFailed) {
            $methodInstanceMock = $this->getMockForAbstractClass(MethodInterface::class);
            $paymentMock = $this->getMockBuilder(Payment::class)
                ->disableOriginalConstructor()
                ->setMethods(['getMethodInstance'])
                ->getMock();
            $paymentMock->method('getMethodInstance')->willReturn($methodInstanceMock);

            $orderMock->expects($this->once())->method('canCancel')->willReturn($canCancel);
            $orderMock->expects($this->exactly((int)$canCancel))->method('getPayment')->willReturn($paymentMock);

            if ($canCancel) {
                $orderMock->expects($this->once())->method('cancel')->willReturnSelf();
                $orderMock->expects($this->once())->method('save')->willReturnSelf();
            }
        }

        $instance = $this->getInstance([
            'configAccount' => $configAccountMock
        ]);
        $instance->order = $orderMock;

        $result = $instance->processFailedPush($status, $message);

        $this->assertTrue($result);
    }

    public function processFailedPushDataProvider()
    {
        return [
            [
                Order::STATE_CANCELED,
                true,
                true,
            ],
            [
                Order::STATE_CANCELED,
                true,
                false,
            ],
            [
                Order::STATE_CANCELED,
                false,
                true,
            ],
            [
                Order::STATE_CANCELED,
                false,
                false,
            ],
            [
                Order::STATE_PROCESSING,
                true,
                true,
            ],
            [
                Order::STATE_PROCESSING,
                true,
                false,
            ],
            [
                Order::STATE_PROCESSING,
                false,
                true,
            ],
            [
                Order::STATE_PROCESSING,
                false,
                false,
            ],
        ];
    }

    /**
     * @param      $state
     * @param      $orderEmailSent
     * @param      $sendOrderConfirmationEmail
     * @param      $paymentAction
     * @param      $amount
     * @param bool                       $textAmount
     * @param bool                       $autoInvoice
     * @param bool                       $orderCanInvoice
     * @param bool                       $orderHasInvoices
     * @param array                      $postData
     *
     * @dataProvider processSucceededPushDataProvider
     */
    public function testProcessSucceededPush(
        $state,
        $orderEmailSent,
        $sendOrderConfirmationEmail,
        $paymentAction,
        $amount,
        $textAmount,
        $autoInvoice = false,
        $orderCanInvoice = false,
        $orderHasInvoices = false,
        $postData = []
    ) {
        $message = 'testMessage';
        $status = 'testStatus';
        $successPaymentState = Order::STATE_PROCESSING;

        $configAccountMock = $this->getFakeMock(Account::class)
            ->setMethods(['getOrderConfirmationEmail'])
            ->getMock();
        $configAccountMock->method('getOrderConfirmationEmail')->willReturn($sendOrderConfirmationEmail);

        $paymentMock = $this->getFakeMock(Payment::class)
            ->setMethods(['getMethodInstance', 'getConfigData', 'canPushInvoice', 'registerCaptureNotification', 'save'])
            ->getMock();
        $paymentMock->expects($this->once())->method('getMethodInstance')->willReturnSelf();
        $paymentMock->method('getConfigData')->willReturn($paymentAction);
        $paymentMock->method('canPushInvoice')->willReturn(($paymentAction == 'authorize' ? false : true));

        $currencyMock = $this->getFakeMock(Currency::class)->setMethods(['formatTxt'])->getMock();
        $currencyMock->expects($this->once())->method('formatTxt')->willReturn($textAmount);

        $orderMock = $this->getFakeMock(Order::class)
            ->setMethods([
                'getEmailSent', 'getGrandTotal', 'getBaseGrandTotal', 'getTotalDue', 'getStore', 'getState',
                'getPayment', 'getBaseCurrency', 'addStatusHistoryComment', 'canInvoice', 'hasInvoices', 'save', 'getInvoiceCollection'
            ])
            ->getMock();
        $orderMock->expects($this->once())->method('getEmailSent')->willReturn($orderEmailSent);
        $orderMock->method('getGrandTotal')->willReturn($amount);
        $orderMock->method('getBaseGrandTotal')->willReturn($amount);
        $orderMock->method('getTotalDue')->willReturn($amount);
        $orderMock->expects($this->once())->method('getStore')->willReturnSelf();
        $orderMock->method('getState')->willReturn($state);
        $orderMock->expects($this->once())->method('getPayment')->willReturn($paymentMock);
        $orderMock->expects($this->once())->method('getBaseCurrency')->willReturn($currencyMock);

        $orderSenderMock = $this->getFakeMock(OrderSender::class)->setMethods(['send'])->getMock();

        $instance = $this->getInstance([
            'configAccount' => $configAccountMock,
            'orderSender' => $orderSenderMock
        ]);

        if (!$orderEmailSent && $sendOrderConfirmationEmail) {
            $orderSenderMock->expects($this->once())->method('send')->with($orderMock);
        }

        $forced = false;

        if (!$autoInvoice || ($autoInvoice && $orderCanInvoice && !$orderHasInvoices)) {
            if ($paymentAction != 'authorize') {
                $expectedDescription = 'Payment status : <strong>' . $message . "</strong><br/>";
                $expectedDescription .= 'Total amount of ' . $textAmount . ' has been paid';
            } else {
                $expectedDescription = 'Authorization status : <strong>' . $message . "</strong><br/>";
                $expectedDescription .= 'Total amount of ' . $textAmount . ' has been ' .
                    'authorized. Please create an invoice to capture the authorized amount.';
                $forced = true;
            }

            if ($state == $successPaymentState || $forced) {
                $orderMock->expects($this->once())
                    ->method('addStatusHistoryComment')
                    ->willReturn($expectedDescription, $status);
            } else {
                $orderMock->expects($this->once())->method('addStatusHistoryComment')->willReturn($expectedDescription);
            }
        }

        if ($autoInvoice) {
            $orderMock->expects($this->once())->method('canInvoice')->willReturn($orderCanInvoice);
            $orderMock->method('hasInvoices')->willReturn($orderCanInvoice);

            if (!$orderCanInvoice || $orderHasInvoices) {
                $this->setExpectedException(Exception::class);
            } else {
                $paymentMock->expects($this->once())->method('registerCaptureNotification')->with($amount);
                $paymentMock->expects($this->once())->method('save');

                $orderMock->expects($this->once())->method('save');

                $instance->postData = $postData;

                $invoiceMock = $this->getFakeMock(Invoice::class)
                    ->setMethods(['getEmailSent', 'setTransactionId', 'save'])
                    ->getMock();
                $invoiceMock->expects($this->once())->method('getEmailSent')->willReturn(false);

                $orderMock->expects($this->once())->method('getInvoiceCollection')->willReturn([$invoiceMock]);

                if (isset($postData['brq_transactions'])) {
                    $invoiceMock->expects($this->once())
                        ->method('setTransactionId')
                        ->with($postData['brq_transactions'])
                        ->willReturnSelf();
                    $invoiceMock->expects($this->once())->method('save');
                }
            }
        }

        $instance->order = $orderMock;

        $result = $instance->processSucceededPush($status, $message);
        $this->assertTrue($result);
    }

    public function processSucceededPushDataProvider()
    {
        return [
            /**
             * Parameter order:
             * $state
             * $orderEmailSent
             * $sendOrderConfirmationEmail
             * $paymentAction
             * $amount
             * $textAmount
             * $autoInvoice
             * $orderCanInvoice
             * $orderHasInvoices
             * $postData
             */
            /** CANCELED && AUTHORIZE */
            0 => [
                Order::STATE_CANCELED,
                true,
                true,
                'authorize',
                '15.95',
                '$15.95',
                false,
                false,
                false,
                false,
                [],
            ],
            1 => [
                Order::STATE_CANCELED,
                false,
                true,
                'authorize',
                '15.95',
                '$15.95',
                false,
                false,
                false,
                [],
            ],
            2 => [
                Order::STATE_CANCELED,
                true,
                false,
                'authorize',
                '15.95',
                '$15.95',
                false,
                false,
                false,
                [],
            ],
            3 => [
                Order::STATE_CANCELED,
                false,
                false,
                'authorize',
                '15.95',
                '$15.95',
                false,
                false,
                false,
                [],
            ],
            /** CANCELED && NOT AUTHORIZE */
            4 => [
                Order::STATE_CANCELED,
                true,
                true,
                'not_authorize',
                '15.95',
                '$15.95',
                true,
                false,
                false,
                [],
            ],
            5 => [
                Order::STATE_CANCELED,
                false,
                true,
                'not_authorize',
                '15.95',
                '$15.95',
                true,
                false,
                false,
                [],
            ],
            6 => [
                Order::STATE_CANCELED,
                true,
                false,
                'not_authorize',
                '15.95',
                '$15.95',
                true,
                false,
                false,
                [],
            ],
            7 => [
                Order::STATE_CANCELED,
                false,
                false,
                'not_authorize',
                '15.95',
                '$15.95',
                true,
                false,
                false,
                [],
            ],
            /** CANCELED && NOT AUTHORIZE && AUTO INVOICE*/
            8 => [
                Order::STATE_CANCELED,
                true,
                true,
                'not_authorize',
                '15.95',
                '$15.95',
                true,
                false,
                false,
                [],
            ],
            9 => [
                Order::STATE_CANCELED,
                true,
                true,
                'not_authorize',
                '15.95',
                '$15.95',
                true,
                false,
                true,
                [],
            ],
            10 => [
                Order::STATE_CANCELED,
                true,
                true,
                'not_authorize',
                '15.95',
                '$15.95',
                true,
                true,
                true,
                [],
            ],
            /** PROCESSING && AUTHORIZE*/
            11 => [
                Order::STATE_PROCESSING,
                true,
                true,
                'authorize',
                '15.95',
                '$15.95',
                false,
                false,
                false,
                [],
            ],
            12 => [
                Order::STATE_PROCESSING,
                false,
                true,
                'authorize',
                '15.95',
                '$15.95',
                false,
                false,
                false,
                [],
            ],
            13 => [
                Order::STATE_PROCESSING,
                true,
                false,
                'authorize',
                '15.95',
                '$15.95',
                false,
                false,
                false,
                [],
            ],
            14 => [
                Order::STATE_PROCESSING,
                false,
                false,
                'authorize',
                '15.95',
                '$15.95',
                false,
                false,
                false,
                [],
            ],
            /** PROCESSING && NOT AUTHORIZE*/
            15 => [
                Order::STATE_PROCESSING,
                true,
                true,
                'not_authorize',
                '15.95',
                '$15.95',
                true,
                false,
                false,
                [],
            ],
            16 => [
                Order::STATE_PROCESSING,
                false,
                true,
                'not_authorize',
                '15.95',
                '$15.95',
                true,
                false,
                false,
                [],
            ],
            17 => [
                Order::STATE_PROCESSING,
                true,
                false,
                'not_authorize',
                '15.95',
                '$15.95',
                true,
                false,
                false,
                [],
            ],
            18 => [
                Order::STATE_PROCESSING,
                false,
                false,
                'not_authorize',
                '15.95',
                '$15.95',
                true,
                false,
                false,
                [],
            ],
            /** PROCESSING && NOT AUTHORIZE && AUTO INVOICE */
            19 => [
                Order::STATE_PROCESSING,
                true,
                true,
                'not_authorize',
                '15.95',
                '$15.95',
                true,
                false,
                false,
                [],
            ],
            20 => [
                Order::STATE_PROCESSING,
                true,
                true,
                'not_authorize',
                '15.95',
                '$15.95',
                true,
                false,
                true,
                [],
            ],
            21 => [
                Order::STATE_PROCESSING,
                true,
                true,
                'not_authorize',
                '15.95',
                '$15.95',
                true,
                true,
                true,
                [],
            ],
        ];
    }
}
