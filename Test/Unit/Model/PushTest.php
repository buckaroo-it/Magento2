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
namespace TIG\Buckaroo\Test\Unit\Model;

use Magento\Framework\Webapi\Rest\Request;
use Magento\Payment\Model\MethodInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Email\Sender\OrderSender;
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
     * @var Push
     */
    protected $object;

    /**
     * @var \Mockery\MockInterface
     */
    protected $objectManager;

    /**
     * @var \Mockery\MockInterface
     */
    protected $request;

    /**
     * @var \Mockery\MockInterface
     */
    protected $helper;

    /**
     * @var \Mockery\MockInterface
     */
    protected $configAccount;

    /**
     * @var \Mockery\MockInterface
     */
    public $orderSender;

    /**
     * Setup the standard mocks
     */
    public function setUp()
    {
        parent::setUp();

        $this->objectManager = \Mockery::mock(\Magento\Framework\ObjectManagerInterface::class);
        $this->request = \Mockery::mock(Request::class);
        $this->helper = \Mockery::mock(\TIG\Buckaroo\Helper\Data::class);
        $this->configAccount = \Mockery::mock(\TIG\Buckaroo\Model\ConfigProvider\Account::class);

        $this->orderSender = \Mockery::mock(OrderSender::class);

        /**
         * We are using the temporary class declared above, but it could be any class extending from the AbstractMethod
         * class.
         */
        $this->object = $this->objectManagerHelper->getObject(
            Push::class,
            [
                'objectManager' => $this->objectManager,
                'request' => $this->request,
                'helper' => $this->helper,
                'configAccount' => $this->configAccount,
                'orderSender' => $this->orderSender,
            ]
        );
    }

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

        $instance = $this->getInstance(['logging' => $debuggerMock]);

        $this->setExpectedException(Exception::class, 'There was no order found by transaction Id');
        $this->invoke('loadOrder', $instance);
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
        $paymentMock = $this->getFakeMock(Payment::class)
            ->setMethods(['getMethod', 'setAdditionalInformation'])
            ->getMock();
        $paymentMock->expects($this->once())->method('getMethod')->willReturn($methodCode);
        $paymentMock->method('setAdditionalInformation');

        $orderMock = $this->getFakeMock(Order::class)->setMethods(['getPayment', 'getGrandTotal'])->getMock();
        $orderMock->expects($this->atLeastOnce())->method('getPayment')->willReturn($paymentMock);
        $orderMock->method('getGrandTotal')->willReturn($orderAmount);

        $this->object->order = $orderMock;
        $this->object->postData = [
            'brq_amount' => $pushAmount,
            'brq_relatedtransaction_partialpayment' => $relatedTransaction
        ];

        $result = $this->invoke('giftcardPartialPayment', $this->object);

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

        $orderMock = \Mockery::mock(Order::class);
        $orderMock->shouldReceive('getState')->atLeast(1)->andReturn($state);
        $orderMock->shouldReceive('getStore')->andReturn(0);
        $orderMock->shouldReceive('getPayment')->andReturnSelf();
        $orderMock->shouldReceive('getMethodInstance')->andReturnSelf();
        $orderMock->shouldReceive('getEmailSent')->andReturn(true);

        if ($state == $pendingPaymentState) {
            $orderMock->shouldReceive('addStatusHistoryComment')->once()->with($expectedDescription, $status);
        } else {
            $orderMock->shouldReceive('addStatusHistoryComment')->once()->with($expectedDescription);
        }
        $this->object->order = $orderMock;

        $this->assertTrue($this->object->processPendingPaymentPush($status, $message));
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

        $this->configAccount->shouldReceive('getCancelOnFailed')->andReturn($cancelOnFailed);

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

        $this->object->order = $orderMock;

        $this->assertTrue($this->object->processFailedPush($status, $message));
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

        /**
         * Only orders with this state should have their status updated
         */
        $successPaymentState = Order::STATE_PROCESSING;

        /**
         * Set config values on config provider mock
         */
        $this->configAccount->shouldReceive('getOrderConfirmationEmail')
            ->andReturn($sendOrderConfirmationEmail);
        $this->configAccount->shouldReceive('getInvoiceEmail');

        /**
         * Build an order mock and set several non mandatory method calls
         */
        $orderMock = \Mockery::mock(Order::class);
        $orderMock->shouldReceive('getEmailSent')->andReturn($orderEmailSent);
        $orderMock->shouldReceive('getGrandTotal')->andReturn($amount);
        $orderMock->shouldReceive('getBaseGrandTotal')->andReturn($amount);
        $orderMock->shouldReceive('getTotalDue')->andReturn($amount);
        $orderMock->shouldReceive('getStore')->andReturnSelf();
        $orderMock->shouldReceive('setIsInProcess');

        /**
         * The order state has to be checked at least once
         */
        $orderMock->shouldReceive('getState')->atLeast(1)->andReturn($state);

        /**
         * If order email is not sent and order email should be sent, expect sending of order email
         */
        if (!$orderEmailSent && $sendOrderConfirmationEmail) {
            $this->orderSender->shouldReceive('send')->with($orderMock);
        }

        /**
         * Build a payment mock and set the payment action
         */
        $paymentMock = \Mockery::mock(Payment::class);
        $paymentMock->shouldReceive('getMethodInstance')->andReturnSelf();
        $paymentMock->shouldReceive('getConfigData')->with('payment_action')->andReturn($paymentAction);
        $paymentMock->shouldReceive('getConfigData');
        $paymentMock->shouldReceive('getMethod');
        $paymentMock->shouldReceive('setTransactionAdditionalInfo');
        $paymentMock->shouldReceive('setTransactionId');
        $paymentMock->shouldReceive('setParentTransactionId');
        $paymentMock->shouldReceive('setAdditionalInformation');

        /**
         * Build a currency mock
         */
        $currencyMock = \Mockery::mock(\Magento\Directory\Model\Currency::class);
        $currencyMock->shouldReceive('formatTxt')->andReturn($textAmount);

        /**
         * Update order mock with payment and currency mock
         */
        $orderMock->shouldReceive('getPayment')->andReturn($paymentMock);
        $orderMock->shouldReceive('getBaseCurrency')->andReturn($currencyMock);

        $forced = false;

        /**
         * If no auto invoicing is required, or if auto invoice is required and the order can be invoiced and
         *  has no invoices, expect a status update
         */
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

            /**
             * Only orders with the success state should have their status updated
             */
            if ($state == $successPaymentState || $forced) {
                $orderMock->shouldReceive('addStatusHistoryComment')->once()->with($expectedDescription, $status);
            } else {
                $orderMock->shouldReceive('addStatusHistoryComment')->once()->with($expectedDescription);
            }
        }

        /**
         * If autoInvoice is required, also test protected method saveInvoice
         */
        if ($autoInvoice) {
            $orderMock->shouldReceive('canInvoice')->andReturn($orderCanInvoice);
            $orderMock->shouldReceive('hasInvoices')->andReturn($orderHasInvoices);

            if (!$orderCanInvoice || $orderHasInvoices) {
                /**
                 * If order cannot be invoiced or if order already has invoices, expect an exception
                 */
                $this->setExpectedException(Exception::class);
            } else {

                /**
                 * Payment should receive register capture notification only once and payment should be saved
                 */
                $paymentMock->shouldReceive('registerCaptureNotification')->once()->with($amount);
                $paymentMock->shouldReceive('save')->once()->withNoArgs();

                /**
                 * Order should be saved at least once
                 */
                $orderMock->shouldReceive('save')->atLeast(1)->withNoArgs();

                $this->object->postData = $postData;

                $invoiceMock = \Mockery::mock(\Magento\Sales\Model\Order\Invoice::class);
                $invoiceMock->shouldReceive('getEmailSent')->andReturn(false);

                /**
                 * Invoice collection should be array iterable so a simple array is used for a mock collection
                 */
                $orderMock->shouldReceive('getInvoiceCollection')->andReturn([$invoiceMock]);

                /**
                 * If key brq_transactions is set in postData, invoice should expect a transaction id to be set
                 */
                if (isset($postData['brq_transactions'])) {
                    $invoiceMock->shouldReceive('setTransactionId')
                        ->with($postData['brq_transactions'])
                        ->andReturnSelf();
                    $invoiceMock->shouldReceive('save');
                }
            }
        }


        $this->helper->shouldReceive('getTransactionAdditionalInfo');

        $this->object->order = $orderMock;

        /**
         * @noinspection PhpUndefinedMethodInspection
         */
        $this->assertTrue($this->object->processSucceededPush($status, $message));
    }

    public function processSucceededPushDataProvider()
    {
        return [
            /**
             * CANCELED && AUTHORIZE
             */
            0 => [
                /**
                 * $state
                 */
                Order::STATE_CANCELED,
                /**
                 * $orderEmailSent
                 */
                true,
                /**
                 * $sendOrderConfirmationEmail
                 */
                true,
                /**
                 * $paymentAction
                 */
                'authorize',
                /**
                 * $amount
                 */
                '15.95',
                /**
                 * $textAmount
                 */
                '$15.95',
                /**
                 * $autoInvoice
                 */
                false,
                /**
                 * $orderCanInvoice
                 */
                false,
                /**
                 * $orderHasInvoices
                 */
                false,
                /**
                 * $postData
                 */
                [],
            ],
            1 => [
                /**
                 * $state
                 */
                Order::STATE_CANCELED,
                /**
                 * $orderEmailSent
                 */
                false,
                /**
                 * $sendOrderConfirmationEmail
                 */
                true,
                /**
                 * $paymentAction
                 */
                'authorize',
                /**
                 * $amount
                 */
                '15.95',
                /**
                 * $textAmount
                 */
                '$15.95',
                /**
                 * $autoInvoice
                 */
                false,
                /**
                 * $orderCanInvoice
                 */
                false,
                /**
                 * $orderHasInvoices
                 */
                false,
                /**
                 * $postData
                 */
                [],
            ],
            2 => [
                /**
                 * $state
                 */
                Order::STATE_CANCELED,
                /**
                 * $orderEmailSent
                 */
                true,
                /**
                 * $sendOrderConfirmationEmail
                 */
                false,
                /**
                 * $paymentAction
                 */
                'authorize',
                /**
                 * $amount
                 */
                '15.95',
                /**
                 * $textAmount
                 */
                '$15.95',
                /**
                 * $autoInvoice
                 */
                false,
                /**
                 * $orderCanInvoice
                 */
                false,
                /**
                 * $orderHasInvoices
                 */
                false,
                /**
                 * $postData
                 */
                [],
            ],
            3 => [
                /**
                 * $state
                 */
                Order::STATE_CANCELED,
                /**
                 * $orderEmailSent
                 */
                false,
                /**
                 * $sendOrderConfirmationEmail
                 */
                false,
                /**
                 * $paymentAction
                 */
                'authorize',
                /**
                 * $amount
                 */
                '15.95',
                /**
                 * $textAmount
                 */
                '$15.95',
                /**
                 * $autoInvoice
                 */
                false,
                /**
                 * $orderCanInvoice
                 */
                false,
                /**
                 * $orderHasInvoices
                 */
                false,
                /**
                 * $postData
                 */
                [],
            ],
            /**
             * CANCELED && NOT AUTHORIZE
             */
            4 => [
                /**
                 * $state
                 */
                Order::STATE_CANCELED,
                /**
                 * $orderEmailSent
                 */
                true,
                /**
                 * $sendOrderConfirmationEmail
                 */
                true,
                /**
                 * $paymentAction
                 */
                'not_authorize',
                /**
                 * $amount
                 */
                '15.95',
                /**
                 * $textAmount
                 */
                '$15.95',
                /**
                 * $autoInvoice
                 */
                true,
                /**
                 * $orderCanInvoice
                 */
                false,
                /**
                 * $orderHasInvoices
                 */
                false,
                /**
                 * $postData
                 */
                [],
            ],
            5 => [
                /**
                 * $state
                 */
                Order::STATE_CANCELED,
                /**
                 * $orderEmailSent
                 */
                false,
                /**
                 * $sendOrderConfirmationEmail
                 */
                true,
                /**
                 * $paymentAction
                 */
                'not_authorize',
                /**
                 * $amount
                 */
                '15.95',
                /**
                 * $textAmount
                 */
                '$15.95',
                /**
                 * $autoInvoice
                 */
                true,
                /**
                 * $orderCanInvoice
                 */
                false,
                /**
                 * $orderHasInvoices
                 */
                false,
                /**
                 * $postData
                 */
                [],
            ],
            6 => [
                /**
                 * $state
                 */
                Order::STATE_CANCELED,
                /**
                 * $orderEmailSent
                 */
                true,
                /**
                 * $sendOrderConfirmationEmail
                 */
                false,
                /**
                 * $paymentAction
                 */
                'not_authorize',
                /**
                 * $amount
                 */
                '15.95',
                /**
                 * $textAmount
                 */
                '$15.95',
                /**
                 * $autoInvoice
                 */
                true,
                /**
                 * $orderCanInvoice
                 */
                false,
                /**
                 * $orderHasInvoices
                 */
                false,
                /**
                 * $postData
                 */
                [],
            ],
            7 => [
                /**
                 * $state
                 */
                Order::STATE_CANCELED,
                /**
                 * $orderEmailSent
                 */
                false,
                /**
                 * $sendOrderConfirmationEmail
                 */
                false,
                /**
                 * $paymentAction
                 */
                'not_authorize',
                /**
                 * $amount
                 */
                '15.95',
                /**
                 * $textAmount
                 */
                '$15.95',
                /**
                 * $autoInvoice
                 */
                true,
                /**
                 * $orderCanInvoice
                 */
                false,
                /**
                 * $orderHasInvoices
                 */
                false,
                /**
                 * $postData
                 */
                [],
            ],
            /**
             * CANCELED && NOT AUTHORIZE && AUTO INVOICE
             */
            8 => [
                /**
                 * $state
                 */
                Order::STATE_CANCELED,
                /**
                 * $orderEmailSent
                 */
                true,
                /**
                 * $sendOrderConfirmationEmail
                 */
                true,
                /**
                 * $paymentAction
                 */
                'not_authorize',
                /**
                 * $amount
                 */
                '15.95',
                /**
                 * $textAmount
                 */
                '$15.95',
                /**
                 * $autoInvoice
                 */
                true,
                /**
                 * $orderCanInvoice
                 */
                false,
                /**
                 * $orderHasInvoices
                 */
                false,
                /**
                 * $postData
                 */
                [],
            ],
            9 => [
                /**
                 * $state
                 */
                Order::STATE_CANCELED,
                /**
                 * $orderEmailSent
                 */
                true,
                /**
                 * $sendOrderConfirmationEmail
                 */
                true,
                /**
                 * $paymentAction
                 */
                'not_authorize',
                /**
                 * $amount
                 */
                '15.95',
                /**
                 * $textAmount
                 */
                '$15.95',
                /**
                 * $autoInvoice
                 */
                true,
                /**
                 * $orderCanInvoice
                 */
                true,
                /**
                 * $orderHasInvoices
                 */
                false,
                /**
                 * $postData
                 */
                ['brq_transactions' => 'test_transaction_id'],
            ],
            10 => [
                /**
                 * $state
                 */
                Order::STATE_CANCELED,
                /**
                 * $orderEmailSent
                 */
                true,
                /**
                 * $sendOrderConfirmationEmail
                 */
                true,
                /**
                 * $paymentAction
                 */
                'not_authorize',
                /**
                 * $amount
                 */
                '15.95',
                /**
                 * $textAmount
                 */
                '$15.95',
                /**
                 * $autoInvoice
                 */
                true,
                /**
                 * $orderCanInvoice
                 */
                true,
                /**
                 * $orderHasInvoices
                 */
                false,
                /**
                 * $postData
                 */
                ['brq_transactions' => 'test_transaction_id'],
            ],
            11 => [
                /**
                 * $state
                 */
                Order::STATE_CANCELED,
                /**
                 * $orderEmailSent
                 */
                true,
                /**
                 * $sendOrderConfirmationEmail
                 */
                true,
                /**
                 * $paymentAction
                 */
                'not_authorize',
                /**
                 * $amount
                 */
                '15.95',
                /**
                 * $textAmount
                 */
                '$15.95',
                /**
                 * $autoInvoice
                 */
                true,
                /**
                 * $orderCanInvoice
                 */
                false,
                /**
                 * $orderHasInvoices
                 */
                true,
                /**
                 * $postData
                 */
                [],
            ],
            12 => [
                /**
                 * $state
                 */
                Order::STATE_CANCELED,
                /**
                 * $orderEmailSent
                 */
                true,
                /**
                 * $sendOrderConfirmationEmail
                 */
                true,
                /**
                 * $paymentAction
                 */
                'not_authorize',
                /**
                 * $amount
                 */
                '15.95',
                /**
                 * $textAmount
                 */
                '$15.95',
                /**
                 * $autoInvoice
                 */
                true,
                /**
                 * $orderCanInvoice
                 */
                true,
                /**
                 * $orderHasInvoices
                 */
                true,
                /**
                 * $postData
                 */
                [],
            ],
            /**
             * PROCESSING && AUTHORIZE
             */
            13 => [
                /**
                 * $state
                 */
                Order::STATE_PROCESSING,
                /**
                 * $orderEmailSent
                 */
                true,
                /**
                 * $sendOrderConfirmationEmail
                 */
                true,
                /**
                 * $paymentAction
                 */
                'authorize',
                /**
                 * $amount
                 */
                '15.95',
                /**
                 * $textAmount
                 */
                '$15.95',
                /**
                 * $autoInvoice
                 */
                false,
                /**
                 * $orderCanInvoice
                 */
                false,
                /**
                 * $orderHasInvoices
                 */
                false,
                /**
                 * $postData
                 */
                [],
            ],
            14 => [
                /**
                 * $state
                 */
                Order::STATE_PROCESSING,
                /**
                 * $orderEmailSent
                 */
                false,
                /**
                 * $sendOrderConfirmationEmail
                 */
                true,
                /**
                 * $paymentAction
                 */
                'authorize',
                /**
                 * $amount
                 */
                '15.95',
                /**
                 * $textAmount
                 */
                '$15.95',
                /**
                 * $autoInvoice
                 */
                false,
                /**
                 * $orderCanInvoice
                 */
                false,
                /**
                 * $orderHasInvoices
                 */
                false,
                /**
                 * $postData
                 */
                [],
            ],
            15 => [
                /**
                 * $state
                 */
                Order::STATE_PROCESSING,
                /**
                 * $orderEmailSent
                 */
                true,
                /**
                 * $sendOrderConfirmationEmail
                 */
                false,
                /**
                 * $paymentAction
                 */
                'authorize',
                /**
                 * $amount
                 */
                '15.95',
                /**
                 * $textAmount
                 */
                '$15.95',
                /**
                 * $autoInvoice
                 */
                false,
                /**
                 * $orderCanInvoice
                 */
                false,
                /**
                 * $orderHasInvoices
                 */
                false,
                /**
                 * $postData
                 */
                [],
            ],
            16 => [
                /**
                 * $state
                 */
                Order::STATE_PROCESSING,
                /**
                 * $orderEmailSent
                 */
                false,
                /**
                 * $sendOrderConfirmationEmail
                 */
                false,
                /**
                 * $paymentAction
                 */
                'authorize',
                /**
                 * $amount
                 */
                '15.95',
                /**
                 * $textAmount
                 */
                '$15.95',
                /**
                 * $autoInvoice
                 */
                false,
                /**
                 * $orderCanInvoice
                 */
                false,
                /**
                 * $orderHasInvoices
                 */
                false,
                /**
                 * $postData
                 */
                [],
            ],
            /**
             * PROCESSING && NOT AUTHORIZE
             */
            17 => [
                /**
                 * $state
                 */
                Order::STATE_PROCESSING,
                /**
                 * $orderEmailSent
                 */
                true,
                /**
                 * $sendOrderConfirmationEmail
                 */
                true,
                /**
                 * $paymentAction
                 */
                'not_authorize',
                /**
                 * $amount
                 */
                '15.95',
                /**
                 * $textAmount
                 */
                '$15.95',
                /**
                 * $autoInvoice
                 */
                true,
                /**
                 * $orderCanInvoice
                 */
                false,
                /**
                 * $orderHasInvoices
                 */
                false,
                /**
                 * $postData
                 */
                [],
            ],
            18 => [
                /**
                 * $state
                 */
                Order::STATE_PROCESSING,
                /**
                 * $orderEmailSent
                 */
                false,
                /**
                 * $sendOrderConfirmationEmail
                 */
                true,
                /**
                 * $paymentAction
                 */
                'not_authorize',
                /**
                 * $amount
                 */
                '15.95',
                /**
                 * $textAmount
                 */
                '$15.95',
                /**
                 * $autoInvoice
                 */
                true,
                /**
                 * $orderCanInvoice
                 */
                false,
                /**
                 * $orderHasInvoices
                 */
                false,
                /**
                 * $postData
                 */
                [],
            ],
            19 => [
                /**
                 * $state
                 */
                Order::STATE_PROCESSING,
                /**
                 * $orderEmailSent
                 */
                true,
                /**
                 * $sendOrderConfirmationEmail
                 */
                false,
                /**
                 * $paymentAction
                 */
                'not_authorize',
                /**
                 * $amount
                 */
                '15.95',
                /**
                 * $textAmount
                 */
                '$15.95',
                /**
                 * $autoInvoice
                 */
                true,
                /**
                 * $orderCanInvoice
                 */
                false,
                /**
                 * $orderHasInvoices
                 */
                false,
                /**
                 * $postData
                 */
                [],
            ],
            20 => [
                /**
                 * $state
                 */
                Order::STATE_PROCESSING,
                /**
                 * $orderEmailSent
                 */
                false,
                /**
                 * $sendOrderConfirmationEmail
                 */
                false,
                /**
                 * $paymentAction
                 */
                'not_authorize',
                /**
                 * $amount
                 */
                '15.95',
                /**
                 * $textAmount
                 */
                '$15.95',
                /**
                 * $autoInvoice
                 */
                true,
                /**
                 * $orderCanInvoice
                 */
                false,
                /**
                 * $orderHasInvoices
                 */
                false,
                /**
                 * $postData
                 */
                [],
            ],
            /**
             * PROCESSING && NOT AUTHORIZE && AUTO INVOICE
             */
            21 => [
                /**
                 * $state
                 */
                Order::STATE_PROCESSING,
                /**
                 * $orderEmailSent
                 */
                true,
                /**
                 * $sendOrderConfirmationEmail
                 */
                true,
                /**
                 * $paymentAction
                 */
                'not_authorize',
                /**
                 * $amount
                 */
                '15.95',
                /**
                 * $textAmount
                 */
                '$15.95',
                /**
                 * $autoInvoice
                 */
                true,
                /**
                 * $orderCanInvoice
                 */
                false,
                /**
                 * $orderHasInvoices
                 */
                false,
                /**
                 * $postData
                 */
                [],
            ],
            22 => [
                /**
                 * $state
                 */
                Order::STATE_PROCESSING,
                /**
                 * $orderEmailSent
                 */
                true,
                /**
                 * $sendOrderConfirmationEmail
                 */
                true,
                /**
                 * $paymentAction
                 */
                'not_authorize',
                /**
                 * $amount
                 */
                '15.95',
                /**
                 * $textAmount
                 */
                '$15.95',
                /**
                 * $autoInvoice
                 */
                true,
                /**
                 * $orderCanInvoice
                 */
                true,
                /**
                 * $orderHasInvoices
                 */
                false,
                /**
                 * $postData
                 */
                ['brq_transactions' => 'test_transaction_id'],
            ],
            23 => [
                /**
                 * $state
                 */
                Order::STATE_PROCESSING,
                /**
                 * $orderEmailSent
                 */
                true,
                /**
                 * $sendOrderConfirmationEmail
                 */
                true,
                /**
                 * $paymentAction
                 */
                'not_authorize',
                /**
                 * $amount
                 */
                '15.95',
                /**
                 * $textAmount
                 */
                '$15.95',
                /**
                 * $autoInvoice
                 */
                true,
                /**
                 * $orderCanInvoice
                 */
                true,
                /**
                 * $orderHasInvoices
                 */
                false,
                /**
                 * $postData
                 */
                ['brq_transactions' => 'test_transaction_id'],
            ],
            24 => [
                /**
                 * $state
                 */
                Order::STATE_PROCESSING,
                /**
                 * $orderEmailSent
                 */
                true,
                /**
                 * $sendOrderConfirmationEmail
                 */
                true,
                /**
                 * $paymentAction
                 */
                'not_authorize',
                /**
                 * $amount
                 */
                '15.95',
                /**
                 * $textAmount
                 */
                '$15.95',
                /**
                 * $autoInvoice
                 */
                true,
                /**
                 * $orderCanInvoice
                 */
                false,
                /**
                 * $orderHasInvoices
                 */
                true,
                /**
                 * $postData
                 */
                [],
            ],
            25 => [
                /**
                 * $state
                 */
                Order::STATE_PROCESSING,
                /**
                 * $orderEmailSent
                 */
                true,
                /**
                 * $sendOrderConfirmationEmail
                 */
                true,
                /**
                 * $paymentAction
                 */
                'not_authorize',
                /**
                 * $amount
                 */
                '15.95',
                /**
                 * $textAmount
                 */
                '$15.95',
                /**
                 * $autoInvoice
                 */
                true,
                /**
                 * $orderCanInvoice
                 */
                true,
                /**
                 * $orderHasInvoices
                 */
                true,
                /**
                 * $postData
                 */
                [],
            ],
        ];
    }
}
