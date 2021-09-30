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

namespace Buckaroo\Magento2\Model;

use Buckaroo\Magento2\Api\PushInterface;
use Buckaroo\Magento2\Helper\Data;
use Buckaroo\Magento2\Helper\PaymentGroupTransaction;
use Buckaroo\Magento2\Logging\Log;
use Buckaroo\Magento2\Model\ConfigProvider\Account;
use Buckaroo\Magento2\Model\ConfigProvider\Method\Factory;
use Buckaroo\Magento2\Model\Method\AbstractMethod;
use Buckaroo\Magento2\Model\Method\Afterpay;
use Buckaroo\Magento2\Model\Method\Afterpay2;
use Buckaroo\Magento2\Model\Method\Afterpay20;
use Buckaroo\Magento2\Model\Method\Creditcard;
use Buckaroo\Magento2\Model\Method\Klarnakp;
use Buckaroo\Magento2\Model\Method\Giftcards;
use Buckaroo\Magento2\Model\Method\Paypal;
use Buckaroo\Magento2\Model\Method\PayPerEmail;
use Buckaroo\Magento2\Model\Method\SepaDirectDebit;
use Buckaroo\Magento2\Model\Method\Sofortbanking;
use Buckaroo\Magento2\Model\Method\Transfer;
use Buckaroo\Magento2\Model\Refund\Push as RefundPush;
use Buckaroo\Magento2\Model\Validator\Push as ValidatorPush;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Webapi\Rest\Request;
use Magento\Sales\Api\Data\TransactionInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Email\Sender\InvoiceSender;
use Magento\Sales\Model\Order\Email\Sender\OrderSender;
use Magento\Sales\Model\Order\Payment\Transaction;
use Magento\Framework\Filesystem\DriverInterface;

class Push implements PushInterface
{
    const BUCK_PUSH_CANCEL_AUTHORIZE_TYPE = 'I014';
    const BUCK_PUSH_ACCEPT_AUTHORIZE_TYPE = 'I013';
    const BUCK_PUSH_GROUPTRANSACTION_TYPE = 'I150';

    const BUCK_PUSH_TYPE_TRANSACTION        = 'transaction_push';
    const BUCK_PUSH_TYPE_INVOICE            = 'invoice_push';
    const BUCK_PUSH_TYPE_INVOICE_INCOMPLETE = 'incomplete_invoice_push';
    const BUCK_PUSH_TYPE_DATAREQUEST        = 'datarequest_push';

    const BUCKAROO_RECEIVED_TRANSACTIONS          = 'buckaroo_received_transactions';
    const BUCKAROO_RECEIVED_TRANSACTIONS_STATUSES = 'buckaroo_received_transactions_statuses';

    /**
     * @var Request $request
     */
    public $request;

    /**
     * @var ValidatorPush $validator
     */
    public $validator;

    /**
     * @var Order $order
     */
    public $order;

    /** @var Transaction */
    private $transaction;

    /**
     * @var OrderSender $orderSender
     */
    public $orderSender;

    /**
     * @var InvoiceSender $invoiceSender
     */
    public $invoiceSender;

    /**
     * @var array $postData
     */
    public $postData;

    /**
     * @var array originalPostData
     */
    public $originalPostData;

    /**
     * @var $refundPush
     */
    public $refundPush;

    /**
     * @var Data
     */
    public $helper;

    /**
     * @var Log $logging
     */
    public $logging;

    /**
     * @var OrderStatusFactory OrderStatusFactory
     */
    public $orderStatusFactory;

    /**
     * @var Account
     */
    public $configAccount;

    /**
     * @var Factory
     */
    public $configProviderMethodFactory;

    protected $groupTransaction;

    protected $forceInvoice = false;

    /**
     * @var \Magento\Framework\ObjectManagerInterface
     */
    protected $objectManager;

    private $dontSaveOrderUponSuccessPush = false;

    protected $resourceConnection;

    private $isPayPerEmailB2BModePushInitial = false;

    protected $dirList;

    private $klarnakpConfig;
    private $afterpayConfig;

    private $fileSystemDriver;

    /**
     * @param Order $order
     * @param TransactionInterface $transaction
     * @param Request $request
     * @param ValidatorPush $validator
     * @param OrderSender $orderSender
     * @param InvoiceSender $invoiceSender
     * @param Data $helper
     * @param Account $configAccount
     * @param RefundPush $refundPush
     * @param Log $logging
     * @param Factory $configProviderMethodFactory
     * @param OrderStatusFactory $orderStatusFactory
     */
    public function __construct(
        Order $order,
        TransactionInterface $transaction,
        Request $request,
        ValidatorPush $validator,
        OrderSender $orderSender,
        InvoiceSender $invoiceSender,
        Data $helper,
        Account $configAccount,
        RefundPush $refundPush,
        Log $logging,
        Factory $configProviderMethodFactory,
        OrderStatusFactory $orderStatusFactory,
        PaymentGroupTransaction $groupTransaction,
        \Magento\Framework\ObjectManagerInterface $objectManager,
        ResourceConnection $resourceConnection,
        \Magento\Framework\Filesystem\DirectoryList $dirList,
        \Buckaroo\Magento2\Model\ConfigProvider\Method\Klarnakp $klarnakpConfig,
        \Buckaroo\Magento2\Model\ConfigProvider\Method\Afterpay20 $afterpayConfig,
        DriverInterface $fileSystemDriver
    ) {
        $this->order                       = $order;
        $this->transaction                 = $transaction;
        $this->request                     = $request;
        $this->validator                   = $validator;
        $this->orderSender                 = $orderSender;
        $this->invoiceSender               = $invoiceSender;
        $this->helper                      = $helper;
        $this->configAccount               = $configAccount;
        $this->refundPush                  = $refundPush;
        $this->logging                     = $logging;
        $this->configProviderMethodFactory = $configProviderMethodFactory;
        $this->orderStatusFactory          = $orderStatusFactory;

        $this->groupTransaction   = $groupTransaction;
        $this->objectManager      = $objectManager;
        $this->resourceConnection = $resourceConnection;
        $this->dirList            = $dirList;
        $this->klarnakpConfig     = $klarnakpConfig;
        $this->afterpayConfig     = $afterpayConfig;
        $this->fileSystemDriver   = $fileSystemDriver;
    }

    /**
     * {@inheritdoc}
     *
     * @todo Once Magento supports variable parameters, modify this method to no longer require a Request object
     */
    public function receivePush()
    {
        $this->getPostData();

        //Start debug mailing/logging with the postdata.
        $this->logging->addDebug(__METHOD__ . '|1|' . var_export($this->originalPostData, true));

        $this->logging->addDebug(__METHOD__ . '|1_2|');
        $lockHandler = $this->lockPushProcessingPpe();
        $this->logging->addDebug(__METHOD__ . '|1_3|');

        if ($this->isGroupTransactionInfo()) {
            if ($this->isGroupTransactionFailed()) {
                $this->savePartGroupTransaction();
            }
            return true;
        }

        if (!$this->isPushNeeded()) {
            return true;
        }

        $this->loadOrder();

        //Check if the push can be processed and if the order can be updated IMPORTANT => use the original post data.
        $validSignature = $this->validator->validateSignature(
            $this->originalPostData,
            $this->postData,
            $this->order ? $this->order->getStore() : null
        );

        $transactionType = $this->getTransactionType();
        //Validate status code and return response
        $postDataStatusCode = $this->getStatusCode();
        $this->logging->addDebug(__METHOD__ . '|1_5|' . var_export($postDataStatusCode, true));

        $this->logging->addDebug(__METHOD__ . '|1_10|' . var_export($transactionType, true));

        $response = $this->validator->validateStatusCode($postDataStatusCode);

        //Check if the push have PayLink
        $this->receivePushCheckPayLink($response, $validSignature);

        $payment       = $this->order->getPayment();

        if ($this->pushCheckPayPerEmailCancel($response, $validSignature, $payment)) {
            return true;
        }

        //Check second push for PayPerEmail
        $receivePushCheckPayPerEmailResult = $this->receivePushCheckPayPerEmail($response, $validSignature, $payment);

        $skipFirstPush = $payment->getAdditionalInformation('skip_push');

        $this->logging->addDebug(__METHOD__ . '|1_20|' . var_export($skipFirstPush, true));

        /**
         * Buckaroo Push is send before Response, for correct flow we skip the first push
         * for some payment methods
         * @todo when buckaroo changes the push / response order this can be removed
         */
        if ($skipFirstPush > 0) {
            $payment->setAdditionalInformation('skip_push', $skipFirstPush - 1);
            $payment->save();
            throw new \Buckaroo\Magento2\Exception(
                __('Skipped handling this push, first handle response, action will be taken on the next push.')
            );
        }

        if ($this->receivePushCheckDuplicates()) {
            throw new \Buckaroo\Magento2\Exception(__('Skipped handling this push, duplicate'));
        }

        $this->logging->addDebug(__METHOD__ . '|2|' . var_export($response, true));

        $canUpdateOrder = $this->canUpdateOrderStatus($response);

        $this->logging->addDebug(__METHOD__ . '|3|' . var_export($canUpdateOrder, true));

        //Check if the push is a refund request or cancel authorize
        if (isset($this->postData['brq_amount_credit'])) {
            if ($response['status'] !== 'BUCKAROO_MAGENTO2_STATUSCODE_SUCCESS'
                && $this->order->isCanceled()
                && $this->postData['brq_transaction_type'] == self::BUCK_PUSH_CANCEL_AUTHORIZE_TYPE
                && $validSignature
            ) {
                return $this->processCancelAuthorize();
            } elseif ($response['status'] !== 'BUCKAROO_MAGENTO2_STATUSCODE_SUCCESS'
                && !$this->order->hasInvoices()
            ) {
                throw new \Buckaroo\Magento2\Exception(
                    __('Refund failed ! Status : %1 and the order does not contain an invoice', $response['status'])
                );
            } elseif ($response['status'] !== 'BUCKAROO_MAGENTO2_STATUSCODE_SUCCESS'
                && $this->order->hasInvoices()
            ) {
                //don't proceed failed refund push
                $this->logging->addDebug(__METHOD__ . '|10|');
                $this->setOrderNotificationNote(__('push notification for refund has no success status, ignoring.'));
                return true;
            }
            return $this->refundPush->receiveRefundPush($this->postData, $validSignature, $this->order);
        }

        //Last validation before push can be completed
        if (!$validSignature) {
            $this->logging->addDebug('Invalid push signature');
            throw new \Buckaroo\Magento2\Exception(__('Signature from push is incorrect'));
            //If the signature is valid but the order cant be updated, try to add a notification to the order comments.
        } elseif ($validSignature && !$canUpdateOrder) {
            $this->logging->addDebug('Order can not receive updates');
            if ($receivePushCheckPayPerEmailResult) {
                $config = $this->configProviderMethodFactory->get(
                    PayPerEmail::PAYMENT_METHOD_CODE
                );
                if ($config->getEnabledB2B()) {
                    $this->logging->addDebug(__METHOD__ . '|$this->order->getState()|' . $this->order->getState());
                    if ($this->order->getState() === Order::STATE_COMPLETE) {
                        $this->order->setState(Order::STATE_PROCESSING);
                        $this->order->save();
                    }
                    return true;
                }
            }
            $this->setOrderNotificationNote(__('The order has already been processed.'));
            throw new \Buckaroo\Magento2\Exception(
                __('Signature from push is correct but the order can not receive updates')
            );
        }

        if (!$this->isGroupTransactionInfo()) {
            $this->setTransactionKey();
        }

        if (isset($this->postData['brq_statusmessage'])) {
            $this->order->addStatusHistoryComment($this->postData['brq_statusmessage']);
        }

        if (($payment->getMethod() != Giftcards::PAYMENT_METHOD_CODE) && $this->isGroupTransactionPart()) {
            $this->savePartGroupTransaction();
            return true;
        }

        switch ($transactionType) {
            case self::BUCK_PUSH_TYPE_TRANSACTION:
            case self::BUCK_PUSH_TYPE_DATAREQUEST:
                $this->processPush($response);
                break;
            case self::BUCK_PUSH_TYPE_INVOICE:
                $this->processCm3Push();
                break;
            case self::BUCK_PUSH_TYPE_INVOICE_INCOMPLETE:
                throw new \Buckaroo\Magento2\Exception(
                    __('Skipped handling this invoice push because it is too soon.')
                );
        }

        $this->logging->addDebug(__METHOD__ . '|5|');
        if (!$this->dontSaveOrderUponSuccessPush) {
            $this->logging->addDebug(__METHOD__ . '|5-1|');
            $this->order->save();
        }

        $this->unlockPushProcessingPpe($lockHandler);

        $this->logging->addDebug(__METHOD__ . '|6|');

        return true;
    }

    private function receivePushCheckDuplicates()
    {
        $this->logging->addDebug(__METHOD__ . '|1|' . var_export($this->order->getPayment()->getMethod(), true));
        $payment               = $this->order->getPayment();
        $ignoredPaymentMethods = [
            Giftcards::PAYMENT_METHOD_CODE,
            Transfer::PAYMENT_METHOD_CODE
        ];
        if ($payment
            && $payment->getMethod()
            && !empty($this->postData['brq_statuscode'])
            && ($this->getTransactionType() == self::BUCK_PUSH_TYPE_TRANSACTION)
            && (!in_array($payment->getMethod(), $ignoredPaymentMethods))
        ) {
            $this->logging->addDebug(__METHOD__ . '|5|');

            $receivedTrxStatuses = $payment->getAdditionalInformation(
                self::BUCKAROO_RECEIVED_TRANSACTIONS_STATUSES
            );
            $this->logging->addDebug(__METHOD__ . '|10|' .
                var_export([$receivedTrxStatuses, $this->postData['brq_statuscode']], true));
            if ($receivedTrxStatuses
                && is_array($receivedTrxStatuses)
                && !empty($this->postData['brq_transactions'])
                && isset($receivedTrxStatuses[$this->postData['brq_transactions']])
                && ($receivedTrxStatuses[$this->postData['brq_transactions']] == $this->postData['brq_statuscode'])
            ) {
                $orderStatus = $this->helper->getOrderStatusByState($this->order, Order::STATE_NEW);
                $statusCode = $this->helper->getStatusCode('BUCKAROO_MAGENTO2_STATUSCODE_SUCCESS');
                if (($this->order->getState() == Order::STATE_NEW)
                    && ($this->order->getStatus() == $orderStatus)
                    && ($this->postData['brq_statuscode'] == $statusCode)
                ) {
                    //allow duplicated pushes for 190 statuses in case if order stills to be new/pending
                    $this->logging->addDebug(__METHOD__ . '|13|');
                    return false;
                }

                $this->logging->addDebug(__METHOD__ . '|15|');
                return true;
            }
            $this->setReceivedTransactionStatuses();
            $payment->save();
        }
        $this->logging->addDebug(__METHOD__ . '|20|');
        return false;
    }

    /**
     * Get and store the postdata parameters
     */
    private function getPostData()
    {
        $postData = $this->request->getPostValue();

        /** Magento may adds the SID session parameter, depending on the store configuration.
         * We don't need or want to use this parameter, so remove it from the retrieved post data. */
        unset($postData['SID']);

        //Set original postdata before setting it to case lower.
        $this->originalPostData = $postData;

        //Create post data array, change key values to lower case.
        $postDataLowerCase = array_change_key_case($postData, CASE_LOWER);
        $this->postData    = $postDataLowerCase;
    }

    /**
     * Check if it is needed to handle the push message based on postdata
     * @return bool
     */
    private function isPushNeeded()
    {
        $this->logging->addDebug(__METHOD__ . '|1|');
        if ($this->hasPostData('add_initiated_by_magento', 1)
            && $this->hasPostData('add_service_action_from_magento', ['refund'])
        ) {
            return false;
        }

        $types = ['capture', 'cancelauthorize', 'cancelreservation'];
        if ($this->hasPostData('add_initiated_by_magento', 1)
            && $this->hasPostData('add_service_action_from_magento', $types)
            && empty($this->postData['brq_relatedtransaction_refund'])
        ) {
            return false;
        }

        if ($this->hasPostData('add_initiated_by_magento', 1)
            && $this->hasPostData('brq_transaction_method', ['klarnakp'])
            && $this->hasPostData('add_service_action_from_magento', 'pay')
        ) {
            return false;
        }

        return true;
    }

    /**
     * @param $name
     * @param $value
     * @return bool
     */
    private function hasPostData($name, $value)
    {
        if (is_array($value) &&
            isset($this->postData[$name]) &&
            in_array($this->postData[$name], $value)
        ) {
            return true;
        }

        if (isset($this->postData[$name]) &&
            $this->postData[$name] == $value
        ) {
            return true;
        }

        return false;
    }

    /**
     * Try to load the order from the Push Data
     */
    private function loadOrder()
    {
        $brqOrderId = $this->getOrderIncrementId();

        //Check if the order can receive further status updates
        $this->order->loadByIncrementId((string) $brqOrderId);

        if (!$this->order->getId()) {
            $this->logging->addDebug('Order could not be loaded by brq_invoicenumber or brq_ordernumber');
            // try to get order by transaction id on payment.
            $this->order = $this->getOrderByTransactionKey();
        }
    }

    private function saveAndReloadOrder()
    {
        $this->order->save();
        $this->loadOrder();
    }

    /**
     * @return int|string
     */
    private function getStatusCode()
    {
        $transactionType = $this->getTransactionType();
        $statusCode      = 0;
        switch ($transactionType) {
            case self::BUCK_PUSH_TYPE_TRANSACTION:
            case self::BUCK_PUSH_TYPE_DATAREQUEST:
                if (isset($this->postData['brq_statuscode'])) {
                    $statusCode = $this->postData['brq_statuscode'];
                }
                break;
            case self::BUCK_PUSH_TYPE_INVOICE:
            case self::BUCK_PUSH_TYPE_INVOICE_INCOMPLETE:
                if (isset($this->postData['brq_eventparameters_statuscode'])) {
                    $statusCode = $this->postData['brq_eventparameters_statuscode'];
                }

                if (isset($this->postData['brq_eventparameters_transactionstatuscode'])) {
                    $statusCode = $this->postData['brq_eventparameters_transactionstatuscode'];
                }
                break;
        }

        return $statusCode;
    }

    /**
     * @return bool|string
     */
    public function getTransactionType()
    {
        //If an order has an invoice key, then it should only be processed by invoice pushes
        $savedInvoiceKey = $this->order->getPayment()->getAdditionalInformation('buckaroo_cm3_invoice_key');

        if (isset($this->postData['brq_invoicekey'])
            && isset($this->postData['brq_schemekey'])
            && strlen($savedInvoiceKey) > 0
        ) {
            return self::BUCK_PUSH_TYPE_INVOICE;
        }

        if (isset($this->postData['brq_invoicekey'])
            && isset($this->postData['brq_schemekey'])
            && strlen($savedInvoiceKey) == 0
        ) {
            return self::BUCK_PUSH_TYPE_INVOICE_INCOMPLETE;
        }

        if (isset($this->postData['brq_datarequest'])) {
            return self::BUCK_PUSH_TYPE_DATAREQUEST;
        }

        if (!isset($this->postData['brq_invoicekey'])
            && !isset($this->postData['brq_service_creditmanagement3_invoicekey'])
            && !isset($this->postData['brq_datarequest'])
            && strlen($savedInvoiceKey) <= 0
        ) {
            return self::BUCK_PUSH_TYPE_TRANSACTION;
        }

        return false;
    }

    /**
     * Cancel authorize processing.
     *
     * @return bool
     */
    public function processCancelAuthorize()
    {
        try {
            $this->setTransactionKey();
        } catch (\Buckaroo\Magento2\Exception $e) {
            $this->logging->addDebug($e->getLogMessage());
        }

        $this->logging->addDebug('Order autorize has been canceld, trying to update payment transactions');

        return true;
    }

    /**
     * Process the push according the response status
     *
     * @param $response
     *
     * @throws \Buckaroo\Magento2\Exception
     */
    public function processPush($response)
    {
        $this->logging->addDebug(__METHOD__ . '|1|' . var_export($response['status'], true));
        $payment = $this->order->getPayment();

        if (!$payment->getMethodInstance()->canProcessPostData($payment, $this->postData)) {
            return;
        }

        if ($this->giftcardPartialPayment()) {
            return;
        }

        $newStatus = $this->orderStatusFactory->get($this->postData['brq_statuscode'], $this->order);

        $this->logging->addDebug(__METHOD__ . '|5|' . var_export($newStatus, true));

        if ($this->isPayPerEmailB2BModePushInitial($response)) {
            $response['status'] = 'BUCKAROO_MAGENTO2_STATUSCODE_SUCCESS';
            $newStatus          = $this->configAccount->getOrderStatusSuccess();
            $this->logging->addDebug(__METHOD__ . '|15|' . var_export([$response['status'], $newStatus], true));
            $this->isPayPerEmailB2BModePushInitial = true;
        }

        switch ($response['status']) {
            case 'BUCKAROO_MAGENTO2_STATUSCODE_TECHNICAL_ERROR':
            case 'BUCKAROO_MAGENTO2_STATUSCODE_VALIDATION_FAILURE':
            case 'BUCKAROO_MAGENTO2_STATUSCODE_CANCELLED_BY_MERCHANT':
            case 'BUCKAROO_MAGENTO2_STATUSCODE_CANCELLED_BY_USER':
            case 'BUCKAROO_MAGENTO2_STATUSCODE_FAILED':
            case 'BUCKAROO_MAGENTO2_STATUSCODE_REJECTED':
                $this->processFailedPush($newStatus, $response['message']);
                break;
            case 'BUCKAROO_MAGENTO2_STATUSCODE_SUCCESS':
                if ($this->order->getPayment()->getMethod() == Paypal::PAYMENT_METHOD_CODE) {
                    $paypalConfig = $this->configProviderMethodFactory
                        ->get(Paypal::PAYMENT_METHOD_CODE);

                    /**
                     * @var \Buckaroo\Magento2\Model\ConfigProvider\Method\Paypal $paypalConfig
                     */
                    $newSellersProtectionStatus = $paypalConfig->getSellersProtectionIneligible();
                    if ($paypalConfig->getSellersProtection() && !empty($newSellersProtectionStatus)) {
                        $newStatus = $newSellersProtectionStatus;
                    }
                }
                $this->processSucceededPush($newStatus, $response['message']);
                break;
            case 'BUCKAROO_MAGENTO2_STATUSCODE_NEUTRAL':
                $this->setOrderNotificationNote($response['message']);
                break;
            case 'BUCKAROO_MAGENTO2_STATUSCODE_PAYMENT_ON_HOLD':
            case 'BUCKAROO_MAGENTO2_STATUSCODE_WAITING_ON_CONSUMER':
            case 'BUCKAROO_MAGENTO2_STATUSCODE_PENDING_PROCESSING':
            case 'BUCKAROO_MAGENTO2_STATUSCODE_WAITING_ON_USER_INPUT':
                $this->processPendingPaymentPush($newStatus, $response['message']);
                break;
        }
    }

    public function processCm3Push()
    {
        $invoiceKey      = $this->postData['brq_invoicekey'];
        $savedInvoiceKey = $this->order->getPayment()->getAdditionalInformation('buckaroo_cm3_invoice_key');

        if ($invoiceKey != $savedInvoiceKey) {
            return;
        }

        if ($this->updateCm3InvoiceStatus()) {
            $this->sendCm3ConfirmationMail();
        }
    }

    private function updateCm3InvoiceStatus()
    {
        $isPaid     = filter_var(strtolower($this->postData['brq_ispaid']), FILTER_VALIDATE_BOOLEAN);
        $canInvoice = ($this->order->canInvoice() && !$this->order->hasInvoices());
        $store      = $this->order->getStore();

        $amount        = floatval($this->postData['brq_amountdebit']);
        $amount        = $this->order->getBaseCurrency()->formatTxt($amount);
        $statusMessage = 'Payment push status : Creditmanagement invoice with a total amount of '
            . $amount . ' has been paid';

        if (!$isPaid && !$canInvoice) {
            $statusMessage = 'Payment push status : Creditmanagement invoice has been (partially) refunded';
        }

        if (!$isPaid && $canInvoice) {
            $statusMessage = 'Payment push status : Waiting for consumer';
        }

        if ($isPaid && $canInvoice) {
            $originalKey                        = AbstractMethod::BUCKAROO_ORIGINAL_TRANSACTION_KEY_KEY;
            $this->postData['brq_transactions'] = $this->order->getPayment()->getAdditionalInformation($originalKey);
            $this->postData['brq_amount']       = $this->postData['brq_amountdebit'];

            if (!$this->saveInvoice()) {
                return false;
            }
        }

        $this->updateOrderStatus($this->order->getState(), $this->order->getStatus(), $statusMessage);

        return true;
    }

    private function sendCm3ConfirmationMail()
    {
        $store         = $this->order->getStore();
        $cm3StatusCode = 0;

        if (isset($this->postData['brq_invoicestatuscode'])) {
            $cm3StatusCode = $this->postData['brq_invoicestatuscode'];
        }

        /** @var \Magento\Payment\Model\MethodInterface $paymentMethod */
        $paymentMethod   = $this->order->getPayment()->getMethodInstance();
        $configOrderMail = $this->configAccount->getOrderConfirmationEmail($store)
        || $paymentMethod->getConfigData('order_email', $store);

        if (!$this->order->getEmailSent() && $cm3StatusCode == 10 && $configOrderMail) {
            $this->orderSender->send($this->order);
        }
    }

    /**
     * @return bool
     */
    private function giftcardPartialPayment()
    {
        $payment = $this->order->getPayment();

        if ($payment->getMethod() != Giftcards::PAYMENT_METHOD_CODE
            || (isset($this->postData['brq_amount']) && $this->postData['brq_amount'] >= $this->order->getGrandTotal())
            || empty($this->postData['brq_relatedtransaction_partialpayment'])
        ) {
            return false;
        }

        if ($this->groupTransaction->isGroupTransaction($this->postData['brq_invoicenumber'])) {
            return false;
        }

        if (!$this->isGroupTransactionInfoType()) {
            $payment->setAdditionalInformation(
                AbstractMethod::BUCKAROO_ORIGINAL_TRANSACTION_KEY_KEY,
                $this->postData['brq_relatedtransaction_partialpayment']
            );

            $this->addGiftcardPartialPaymentToPaymentInformation();
        }

        return true;
    }

    protected function addGiftcardPartialPaymentToPaymentInformation()
    {
        $payment = $this->order->getPayment();

        $transactionAmount = (isset($this->postData['brq_amount'])) ? $this->postData['brq_amount'] : 0;
        $transactionKey    = (isset($this->postData['brq_transactions'])) ? $this->postData['brq_transactions'] : '';
        $transactionMethod = (isset($this->postData['brq_transaction_method'])) ?
            $this->postData['brq_transaction_method'] : '';

        $transactionData = $payment->getAdditionalInformation(AbstractMethod::BUCKAROO_ALL_TRANSACTIONS);

        $transactionArray = [];
        if (is_array($transactionData) && count($transactionData) > 0) {
            $transactionArray = $transactionData;
        }

        if (!empty($transactionKey) && $transactionAmount > 0) {
            $transactionArray[$transactionKey] = [$transactionMethod, $transactionAmount];

            $payment->setAdditionalInformation(
                AbstractMethod::BUCKAROO_ALL_TRANSACTIONS,
                $transactionArray
            );
        }
    }

    /**
     * Makes sure the order transactionkey has been set.
     */
    protected function setTransactionKey()
    {
        $payment        = $this->order->getPayment();
        $originalKey    = AbstractMethod::BUCKAROO_ORIGINAL_TRANSACTION_KEY_KEY;
        $transactionKey = $this->getTransactionKey();

        if (!$payment->getAdditionalInformation($originalKey) && strlen($transactionKey) > 0) {
            $payment->setAdditionalInformation($originalKey, $transactionKey);
        }
    }

    /**
     * Store additional transaction information to track multiple payments manually
     * Multiple Buckaroo pushes can resolve into incorrect
     */
    protected function setReceivedPaymentFromBuckaroo()
    {
        if (empty($this->postData['brq_transactions'])) {
            return;
        }

        $payment = $this->order->getPayment();

        if (!$payment->getAdditionalInformation(self::BUCKAROO_RECEIVED_TRANSACTIONS)) {
            $payment->setAdditionalInformation(
                self::BUCKAROO_RECEIVED_TRANSACTIONS,
                [$this->postData['brq_transactions'] => floatval($this->postData['brq_amount'])]
            );
        } else {
            $buckarooTransactionKeysArray = $payment->getAdditionalInformation(self::BUCKAROO_RECEIVED_TRANSACTIONS);

            $buckarooTransactionKeysArray[$this->postData['brq_transactions']] =
                floatval($this->postData['brq_amount']);

            $payment->setAdditionalInformation(self::BUCKAROO_RECEIVED_TRANSACTIONS, $buckarooTransactionKeysArray);
        }
    }

    protected function setReceivedTransactionStatuses()
    {
        if (empty($this->postData['brq_transactions']) || empty($this->postData['brq_statuscode'])) {
            return;
        }

        $payment = $this->order->getPayment();

        if (!$payment->getAdditionalInformation(self::BUCKAROO_RECEIVED_TRANSACTIONS_STATUSES)) {
            $payment->setAdditionalInformation(
                self::BUCKAROO_RECEIVED_TRANSACTIONS_STATUSES,
                [$this->postData['brq_transactions'] => $this->postData['brq_statuscode']]
            );
        } else {
            $buckarooTransactionKeysArray = $payment->getAdditionalInformation(self::BUCKAROO_RECEIVED_TRANSACTIONS);
            $buckarooTransactionKeysArray[$this->postData['brq_transactions']] = $this->postData['brq_statuscode'];
            $payment->setAdditionalInformation(
                self::BUCKAROO_RECEIVED_TRANSACTIONS_STATUSES,
                $buckarooTransactionKeysArray
            );
        }
    }

    /**
     * @return string
     */
    private function getTransactionKey()
    {
        $trxId = '';

        if (isset($this->postData['brq_transactions']) && !empty($this->postData['brq_transactions'])) {
            $trxId = $this->postData['brq_transactions'];
        }

        if (isset($this->postData['brq_datarequest']) && !empty($this->postData['brq_datarequest'])) {
            $trxId = $this->postData['brq_datarequest'];
        }

        if (isset($this->postData['brq_service_klarna_autopaytransactionkey'])
            && !empty($this->postData['brq_service_klarna_autopaytransactionkey'])
        ) {
            $trxId = $this->postData['brq_service_klarna_autopaytransactionkey'];
        }

        if (isset($this->postData['brq_service_klarnakp_autopaytransactionkey'])
            && !empty($this->postData['brq_service_klarnakp_autopaytransactionkey'])
        ) {
            $trxId = $this->postData['brq_service_klarnakp_autopaytransactionkey'];
        }

        if (!empty($this->postData['brq_relatedtransaction_refund'])
            && isset($this->postData['brq_relatedtransaction_refund'])
        ) {
            $trxId = $this->postData['brq_relatedtransaction_refund'];
        }

        return $trxId;
    }

    /**
     * Sometimes the push does not contain the order id, when thats the case try to get the order by his payment,
     * by using its own transactionkey.
     *
     * @return Order
     * @throws \Buckaroo\Magento2\Exception
     */
    protected function getOrderByTransactionKey()
    {
        $trxId = $this->getTransactionKey();

        $this->transaction->load($trxId, 'txn_id');
        $order = $this->transaction->getOrder();

        if (!$order) {
            throw new \Buckaroo\Magento2\Exception(__('There was no order found by transaction Id'));
        }

        return $order;
    }

    /**
     * Checks if the order can be updated by checking its state and status.
     *
     * @return bool
     */
    protected function canUpdateOrderStatus($response)
    {
        /**
         * Types of statusses
         */
        $completedStateAndStatus = [Order::STATE_COMPLETE, Order::STATE_COMPLETE];
        $cancelledStateAndStatus = [Order::STATE_CANCELED, Order::STATE_CANCELED];
        $holdedStateAndStatus    = [Order::STATE_HOLDED, Order::STATE_HOLDED];
        $closedStateAndStatus    = [Order::STATE_CLOSED, Order::STATE_CLOSED];
        /**
         * Get current state and status of order
         */
        $currentStateAndStatus = [$this->order->getState(), $this->order->getStatus()];
        $this->logging->addDebug(__METHOD__ . '|1|' . var_export($currentStateAndStatus, true));

        /**
         * If the types are not the same and the order can receive an invoice the order can be udpated by BPE.
         */
        if ($completedStateAndStatus != $currentStateAndStatus
            && $cancelledStateAndStatus != $currentStateAndStatus
            && $holdedStateAndStatus != $currentStateAndStatus
            && $closedStateAndStatus != $currentStateAndStatus
        ) {
            return true;
        }

        if (($this->order->getState() === Order::STATE_CANCELED)
            && ($this->order->getStatus() === Order::STATE_CANCELED)
            && ($response['status'] === 'BUCKAROO_MAGENTO2_STATUSCODE_SUCCESS')
        ) {
            $this->logging->addDebug(__METHOD__ . '|2|');

            $this->order->setState(Order::STATE_NEW);
            $this->order->setStatus('pending');

            foreach ($this->order->getAllItems() as $item) {
                $item->setQtyCanceled(0);
            }

            $this->forceInvoice = true;
            return true;
        }

        return false;
    }

    /**
     * @param $newStatus
     * @param $message
     *
     * @return bool
     */
    public function processFailedPush($newStatus, $message)
    {
        $this->logging->addDebug(__METHOD__ . '|1|' . var_export($newStatus, true));

        if (($this->order->getState() === Order::STATE_PROCESSING)
            && ($this->order->getStatus() === Order::STATE_PROCESSING)
        ) {
            //do not update to failed if we had a success already
            $this->logging->addDebug(__METHOD__ . '|2|');
            return false;
        }

        $description = 'Payment status : ' . $message;

        if (isset($this->postData['brq_service_antifraud_action'])) {
            $description .= $this->postData['brq_service_antifraud_action'] .
                ' ' .
                $this->postData['brq_service_antifraud_check'] .
                ' ' .
                $this->postData['brq_service_antifraud_details']
            ;
        }

        $store = $this->order->getStore();

        $buckarooCancelOnFailed = $this->configAccount->getCancelOnFailed($store);

        $payment = $this->order->getPayment();

        if ($buckarooCancelOnFailed && $this->order->canCancel()) {
            $this->logging->addDebug(__METHOD__ . '|' . 'Buckaroo push failed : ' . $message . ' : Cancel order.');

            // BUCKM2-78: Never automatically cancelauthorize via push for afterpay
            // setting parameter which will cause to stop the cancel process on
            // Buckaroo/Model/Method/AbstractMethod.php:880
            $methods = [
                'buckaroo_magento2_afterpay',
                'buckaroo_magento2_afterpay2',
                'buckaroo_magento2_klarna',
                'buckaroo_magento2_klarnakp'
            ];
            if (in_array($payment->getMethodInstance()->getCode(), $methods)) {
                $payment->setAdditionalInformation('buckaroo_failed_authorize', 1);
                $payment->save();
            }

            $this->updateOrderStatus(Order::STATE_CANCELED, $newStatus, $description);

            try {
                $this->order->cancel()->save();
            } catch (\Throwable $t) {
                $this->logging->addDebug(__METHOD__ . '|3|');
                //  SignifydGateway/Gateway error on line 208"
            }
            return true;
        }

        $this->logging->addDebug(__METHOD__ . '|4|');
        $force = false;
        if (($payment->getMethodInstance()->getCode() == 'buckaroo_magento2_mrcash')
            && ($this->order->getState() === Order::STATE_NEW)
            && ($this->order->getStatus() === 'pending')
        ) {
            $force = true;
        }
        $this->updateOrderStatus(Order::STATE_CANCELED, $newStatus, $description, $force);

        return true;
    }

    /**
     * @param $newStatus
     * @param $message
     *
     * @return bool
     */
    public function processSucceededPush($newStatus, $message)
    {
        $this->logging->addDebug(__METHOD__ . '|1|' . var_export($newStatus, true));

        $amount = $this->order->getTotalDue();

        if (isset($this->postData['brq_amount']) && !empty($this->postData['brq_amount'])) {
            $this->logging->addDebug(__METHOD__ . '|11|');
            $amount = floatval($this->postData['brq_amount']);
        }

        if (isset($this->postData['brq_service_klarna_reservationnumber'])
            && !empty($this->postData['brq_service_klarna_reservationnumber'])
        ) {
            $this->order->setBuckarooReservationNumber($this->postData['brq_service_klarna_reservationnumber']);
            $this->order->save();
        }

        if (isset($this->postData['brq_service_klarnakp_reservationnumber'])
            && !empty($this->postData['brq_service_klarnakp_reservationnumber'])
        ) {
            $this->order->setBuckarooReservationNumber($this->postData['brq_service_klarnakp_reservationnumber']);
            $this->order->save();
        }

        $store = $this->order->getStore();

        $payment = $this->order->getPayment();

        /**
         * @var \Magento\Payment\Model\MethodInterface $paymentMethod
         */
        $paymentMethod = $payment->getMethodInstance();

        if (!$this->order->getEmailSent()
            && ($this->configAccount->getOrderConfirmationEmail($store)
                || $paymentMethod->getConfigData('order_email', $store)
            )
        ) {
            $this->logging->addDebug(__METHOD__ . '|sendemail|' .
                var_export($this->configAccount->getOrderConfirmationEmailSync($store), true));
            $this->orderSender->send($this->order, $this->configAccount->getOrderConfirmationEmailSync($store));
        }

        /** force state eventhough this can lead to a transition of the order
         *  like new -> processing
         */
        $forceState = false;
        $state      = Order::STATE_PROCESSING;

        $this->logging->addDebug(__METHOD__ . '|2|');

        if ($paymentMethod->canPushInvoice($this->postData)) {
            $this->logging->addDebug(__METHOD__ . '|3|');
            $description = 'Payment status : <strong>' . $message . "</strong><br/>";
            if ($this->hasPostData('brq_transaction_method', 'transfer')) {
                //keep amount fetched from brq_amount
                $description .= 'Amount of ' . $this->order->getBaseCurrency()->formatTxt($amount) . ' has been paid';
            } else {
                $amount = $this->order->getBaseTotalDue();
                $description .= 'Total amount of ' .
                    $this->order->getBaseCurrency()->formatTxt($amount) . ' has been paid';
            }
        } else {
            $description = 'Authorization status : <strong>' . $message . "</strong><br/>";
            $description .= 'Total amount of ' . $this->order->getBaseCurrency()->formatTxt($this->order->getTotalDue())
                . ' has been authorized. Please create an invoice to capture the authorized amount.';
            $forceState = true;
        }

        if ($this->isPayPerEmailB2BModePushInitial) {
            $description = '';
        }

        $this->dontSaveOrderUponSuccessPush = false;
        if ($paymentMethod->canPushInvoice($this->postData)) {
            $this->logging->addDebug(__METHOD__ . '|4|');

            if (!$this->isPayPerEmailB2BModePushInitial && $this->isPayPerEmailB2BModePushPaid()) {
                $this->logging->addDebug(__METHOD__ . '|4_1|');
                //Fix for suspected fraud when the order currency does not match with the payment's currency
                $amount = ($payment->isSameCurrency() && $payment->isCaptureFinal($this->order->getGrandTotal())) ?
                    $this->order->getGrandTotal() : $this->order->getBaseTotalDue();
                $payment->registerCaptureNotification($amount);
                $payment->save();
                $this->order->setState('complete');
                $this->order->addStatusHistoryComment($description, 'complete');
                $this->order->save();

                if ($transactionKey = $this->getTransactionKey()) {
                    foreach ($this->order->getInvoiceCollection() as $invoice) {
                        $invoice->setTransactionId($transactionKey)->save();
                    }
                }
                return true;
            }

            if ($this->hasPostData('add_initiated_by_magento', 1) &&
                (
                    $this->hasPostData('brq_transaction_method', 'KlarnaKp') &&
                    $this->hasPostData('add_service_action_from_magento', 'pay') &&
                    empty($this->postData['brq_service_klarnakp_reservationnumber']) &&
                    $this->klarnakpConfig->getCreateInvoiceAfterShipment()
                ) ||
                (
                    $this->hasPostData('brq_transaction_method', 'afterpay') &&
                    $this->hasPostData('add_service_action_from_magento', 'capture') &&
                    $this->afterpayConfig->getCreateInvoiceAfterShipment()
                )
            ) {
                $this->logging->addDebug(__METHOD__ . '|5_1|');
                $this->dontSaveOrderUponSuccessPush = true;
                return true;
            } else {
                $this->logging->addDebug(__METHOD__ . '|6|');

                if ($this->hasPostData('brq_transaction_method', 'transfer')) {
                    //invoice only in case of full or last remained amount
                    $this->logging->addDebug(__METHOD__ . '|61|' . var_export([
                            $this->order->getId(),
                            $amount,
                            $this->order->getTotalDue(),
                            $this->order->getTotalPaid(),
                        ], true));

                    $saveInvoice = true;
                    if (($amount < $this->order->getTotalDue())
                        || (($amount == $this->order->getTotalDue()) && ($this->order->getTotalPaid() > 0))
                    ) {
                        $this->logging->addDebug(__METHOD__ . '|64|');

                        $forceState = true;
                        if ($amount < $this->order->getTotalDue()) {
                            $this->logging->addDebug(__METHOD__ . '|65|');
                            $state       = Order::STATE_NEW;
                            $newStatus   = $this->orderStatusFactory->get(
                                $this->helper->getStatusCode('BUCKAROO_MAGENTO2_STATUSCODE_PENDING_PROCESSING'),
                                $this->order
                            );
                            $saveInvoice = false;
                        }

                        $this->saveAndReloadOrder();

                        $this->order->setTotalDue($this->order->getTotalDue() - $amount);
                        $this->order->setBaseTotalDue($this->order->getTotalDue() - $amount);

                        $totalPaid = $this->order->getTotalPaid() + $amount;
                        $this->order->setTotalPaid(
                            $totalPaid > $this->order->getGrandTotal() ? $this->order->getGrandTotal() : $totalPaid
                        );

                        $baseTotalPaid = $this->order->getBaseTotalPaid() + $amount;
                        $this->order->setBaseTotalPaid(
                            $baseTotalPaid > $this->order->getBaseGrandTotal() ?
                                $this->order->getBaseGrandTotal() : $baseTotalPaid
                        );
                        
                        $this->saveAndReloadOrder();

                        $connection = $this->resourceConnection->getConnection();
                        $connection->update(
                            $connection->getTableName('sales_order'),
                            [
                                'total_due'       => $this->order->getTotalDue(),
                                'base_total_due'  => $this->order->getTotalDue(),
                                'total_paid'      => $this->order->getTotalPaid(),
                                'base_total_paid' => $this->order->getBaseTotalPaid(),
                            ],
                            $connection->quoteInto('entity_id = ?', $this->order->getId())
                        );

                    }

                    if ($saveInvoice) {
                        if (!$this->saveInvoice()) {
                            return false;
                        }
                    }

                } else {

                    if (!$this->saveInvoice()) {
                        return false;
                    }

                }

            }
        }

        if (!empty($this->postData['brq_service_klarna_autopaytransactionkey'])
            && ($this->postData['brq_statuscode'] == 190)
        ) {
            $this->saveInvoice();
        }

        if (!empty($this->postData['brq_service_klarnakp_autopaytransactionkey'])
            && ($this->postData['brq_statuscode'] == 190)
        ) {
            $this->saveInvoice();
        }

        if ($this->groupTransaction->isGroupTransaction($this->postData['brq_invoicenumber'])) {
            $forceState = true;
        }

        $this->logging->addDebug(__METHOD__ . '|8|');

        $this->processSucceededPushAuth($payment);

        $this->updateOrderStatus($state, $newStatus, $description, $forceState);

        $this->logging->addDebug(__METHOD__ . '|9|');

        return true;
    }

    /**
     * @param $newStatus
     * @param $message
     *
     * @return bool
     */
    public function processPendingPaymentPush($newStatus, $message)
    {
        $this->logging->addDebug(__METHOD__ . '|1|');

        $store   = $this->order->getStore();
        $payment = $this->order->getPayment();

        /** @var \Magento\Payment\Model\MethodInterface $paymentMethod */
        $paymentMethod = $payment->getMethodInstance();

        // Transfer has a slightly different flow where a successful order has a 792 status code instead of an 190 one
        if (!$this->order->getEmailSent()
            && in_array($payment->getMethod(), [Transfer::PAYMENT_METHOD_CODE,
                SepaDirectDebit::PAYMENT_METHOD_CODE,
                Sofortbanking::PAYMENT_METHOD_CODE,
                PayPerEmail::PAYMENT_METHOD_CODE,
            ])
            && ($this->configAccount->getOrderConfirmationEmail($store)
                || $paymentMethod->getConfigData('order_email', $store)
            )
        ) {
            $this->logging->addDebug(__METHOD__ . '|sendemail|');
            $this->orderSender->send($this->order);
        }

        $description = 'Payment push status : ' . $message;

        // $this->updateOrderStatus(Order::STATE_PROCESSING, $newStatus, $description);

        return true;
    }

    /**
     * Try to add an notification note to the order comments.
     *
     * @param $message
     */
    protected function setOrderNotificationNote($message)
    {
        $note = 'Buckaroo attempted to update this order, but failed: ' . $message;
        try {
            $this->order->addStatusHistoryComment($note);
            $this->order->save();
        } catch (\Buckaroo\Magento2\Exception $e) {
            $this->logging->addDebug($e->getLogMessage());
        }
    }

    /**
     * Updates the orderstate and add a comment.
     *
     * @param $orderState
     * @param $description
     * @param $newStatus
     * @param $force
     */
    protected function updateOrderStatus($orderState, $newStatus, $description, $force = false)
    {
        $this->logging->addDebug(__METHOD__ . '|0|' . var_export([$orderState, $newStatus, $description], true));
        if ($this->order->getState() == $orderState || $force == true) {
            $this->logging->addDebug(__METHOD__ . '|1|');
            $this->logging->addDebug('||| $orderState: ' . '|1|' . $orderState);
            if ($this->dontSaveOrderUponSuccessPush) {
                $this->order->addStatusHistoryComment($description)
                    ->setIsCustomerNotified(false)
                    ->setEntityName('invoice')
                    ->setStatus($newStatus)
                    ->save();
            } else {
                $this->order->addStatusHistoryComment($description, $newStatus);
            }
        } else {
            $this->logging->addDebug(__METHOD__ . '|2|');
            $this->logging->addDebug('||| $orderState: ' . '|2|' . $orderState);
            if ($this->dontSaveOrderUponSuccessPush) {
                $this->order->addStatusHistoryComment($description)
                    ->setIsCustomerNotified(false)
                    ->setEntityName('invoice')
                    ->save();
            } else {
                $this->order->addStatusHistoryComment($description);
            }
        }
    }

    /**
     * Creates and saves the invoice and adds for each invoice the buckaroo transaction keys
     * Only when the order can be invoiced and has not been invoiced before.
     *
     * @return bool
     * @throws \Buckaroo\Magento2\Exception
     */
    protected function saveInvoice()
    {
        $this->logging->addDebug(__METHOD__ . '|1|');
        if (!$this->forceInvoice) {
            if (!$this->order->canInvoice() || $this->order->hasInvoices()) {
                $this->logging->addDebug('Order can not be invoiced');
                //throw new \Buckaroo\Magento2\Exception(__('Order can not be invoiced'));
                return false;
            }
        }

        $this->logging->addDebug(__METHOD__ . '|5|');

        /**
         * Only when the order can be invoiced and has not been invoiced before.
         */

        if (!$this->isGroupTransactionInfoType()) {
            $this->addTransactionData();
        }

        /**
         * @var \Magento\Sales\Model\Order\Payment $payment
         */
        $payment = $this->order->getPayment();

        $invoiceAmount = floatval($this->postData['brq_amount']);
        if (($payment->getMethod() == Giftcards::PAYMENT_METHOD_CODE)
            && $invoiceAmount != $this->order->getGrandTotal()
        ) {
            $this->setReceivedPaymentFromBuckaroo();

            $payment->registerCaptureNotification($invoiceAmount, true);
            $payment->save();

            $receivedPaymentsArray = $payment->getAdditionalInformation(self::BUCKAROO_RECEIVED_TRANSACTIONS);

            if (!is_array($receivedPaymentsArray)) {
                return;
            }

            $payment->capture(); //creates invoice
            $payment->save();
        } elseif ($this->isPayPerEmailB2BModePushInitial) {
            $this->logging->addDebug(__METHOD__ . '|10|');
            $invoice = $this->order->prepareInvoice()->register();
            $invoice->setOrder($this->order);
            $this->order->addRelatedObject($invoice);
            $payment->setCreatedInvoice($invoice);
            $payment->setShouldCloseParentTransaction(true);
        } else {
            $this->logging->addDebug(__METHOD__ . '|15|');
            //Fix for suspected fraud when the order currency does not match with the payment's currency
            $amount = ($payment->isSameCurrency()
                && $payment->isCaptureFinal($this->order->getGrandTotal())) ?
                $this->order->getGrandTotal() : $this->order->getBaseTotalDue();
            $payment->registerCaptureNotification($amount);
            $payment->save();
        }

        $this->logging->addDebug(__METHOD__ . '|20|');

        $transactionKey = $this->getTransactionKey();

        if (strlen($transactionKey) <= 0) {
            return true;
        }

        $this->logging->addDebug(__METHOD__ . '|25|');

        /** @var \Magento\Sales\Model\Order\Invoice $invoice */
        foreach ($this->order->getInvoiceCollection() as $invoice) {
            $invoice->setTransactionId($transactionKey)->save();

            if (!empty($this->postData['brq_invoicenumber'])) {
                if ($this->groupTransaction->isGroupTransaction($this->postData['brq_invoicenumber'])) {

                    $this->logging->addDebug(__METHOD__ . '|27|');

                    $invoice->setGrandTotal($invoice->getGrandTotal() + $this->order->getBuckarooAlreadyPaid());
                    $invoice->setBaseGrandTotal(
                        $invoice->getBaseGrandTotal() + $this->order->getBaseBuckarooAlreadyPaid()
                    );
                    $invoice->setState(2);
                    $payment->setAmountPaid($payment->getAmountPaid() + $this->order->getBuckarooAlreadyPaid());
                    $payment->save();
                }
            }

            if (!$invoice->getEmailSent() && $this->configAccount->getInvoiceEmail($this->order->getStore())) {
                $this->logging->addDebug(__METHOD__ . '|30|sendinvoiceemail');
                $this->invoiceSender->send($invoice, true);
            }
        }

        $this->logging->addDebug(__METHOD__ . '|35|');

        $this->order->setIsInProcess(true);
        $this->order->save();

        $this->dontSaveOrderUponSuccessPush = true;

        return true;
    }

    /**
     * @return Order\Payment
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function addTransactionData($transactionKey = false, $datas = false)
    {
        /**
         * @var \Magento\Sales\Model\Order\Payment $payment
         */
        $payment = $this->order->getPayment();

        $transactionKey = $transactionKey ? $transactionKey : $this->getTransactionKey();

        if (strlen($transactionKey) <= 0) {
            throw new \Buckaroo\Magento2\Exception(__('There was no transaction ID found'));
        }

        /**
         * Save the transaction's response as additional info for the transaction.
         */
        $postData = $datas ? $datas : $this->postData;
        $rawInfo  = $this->helper->getTransactionAdditionalInfo($postData);

        /**
         * @noinspection PhpUndefinedMethodInspection
         */
        $payment->setTransactionAdditionalInfo(
            Transaction::RAW_DETAILS,
            $rawInfo
        );

        /**
         * Save the payment's transaction key.
         */
        /**
         * @noinspection PhpUndefinedMethodInspection
         */
        if ($this->hasPostData('brq_transaction_method', 'KlarnaKp')) {
            $payment->setTransactionId($transactionKey);
        } else {
            $payment->setTransactionId($transactionKey . '-capture');
        }
        /**
         * @noinspection PhpUndefinedMethodInspection
         */
        $payment->setParentTransactionId($transactionKey);
        $payment->setAdditionalInformation(
            \Buckaroo\Magento2\Model\Method\AbstractMethod::BUCKAROO_ORIGINAL_TRANSACTION_KEY_KEY,
            $transactionKey
        );

        return $payment;
    }

    private function isGroupTransactionInfoType()
    {
        if (!empty($this->postData['brq_transaction_type'])
            && ($this->postData['brq_transaction_type'] == self::BUCK_PUSH_GROUPTRANSACTION_TYPE)
        ) {
            return true;
        }
        return false;
    }

    private function isGroupTransactionInfo()
    {
        $this->logging->addDebug(__METHOD__ . '|1|');
        if ($this->isGroupTransactionInfoType()) {
            if ($this->postData['brq_statuscode'] !=
                $this->helper->getStatusCode('BUCKAROO_MAGENTO2_STATUSCODE_SUCCESS')
            ) {
                return true;
            }
        }
        return false;
    }

    private function isGroupTransactionPart()
    {
        if (isset($this->postData['brq_transactions'])) {
            return $this->groupTransaction->getGroupTransactionByTrxId($this->postData['brq_transactions']);
        }
        return false;
    }

    private function isGroupTransactionFailed()
    {
        if ($this->isGroupTransactionInfoType()) {
            if ($this->postData['brq_statuscode'] ==
                $this->helper->getStatusCode('BUCKAROO_MAGENTO2_STATUSCODE_FAILED')
            ) {
                return true;
            }
        }
        return false;
    }

    private function savePartGroupTransaction()
    {
        $items = $this->groupTransaction->getGroupTransactionByTrxId($this->postData['brq_transactions']);
        if (is_array($items) && count($items) > 0) {
            foreach ($items as $key => $item) {
                $item2['status']    = $this->postData['brq_statuscode'];
                $item2['entity_id'] = $item['entity_id'];
                $this->groupTransaction->updateGroupTransaction($item2);
            }
        }
    }

    private function receivePushCheckPayLink($response, $validSignature)
    {
        if (isset($this->postData['add_frompaylink'])
            && $response['status'] == 'BUCKAROO_MAGENTO2_STATUSCODE_SUCCESS'
            && $validSignature
        ) {
            $payment = $this->order->getPayment();
            $payment->setMethod('buckaroo_magento2_payperemail');
            $payment->save();
            $this->order->save();
            return true;
        }
        return false;
    }

    private function pushCheckPayPerEmailCancel($response, $validSignature, $payment)
    {
        $failedStatuses = [
            'BUCKAROO_MAGENTO2_STATUSCODE_TECHNICAL_ERROR',
            'BUCKAROO_MAGENTO2_STATUSCODE_VALIDATION_FAILURE',
            'BUCKAROO_MAGENTO2_STATUSCODE_CANCELLED_BY_MERCHANT',
            'BUCKAROO_MAGENTO2_STATUSCODE_CANCELLED_BY_USER',
            'BUCKAROO_MAGENTO2_STATUSCODE_FAILED',
            'BUCKAROO_MAGENTO2_STATUSCODE_REJECTED'
        ];
        $status = $this->helper->getStatusByValue($this->postData['brq_statuscode'] ?? '');
        if ((isset($this->originalPostData['ADD_fromPayPerEmail'])
                || ($payment->getMethod() == 'buckaroo_magento2_payperemail'))
            && isset($this->originalPostData['brq_transaction_method'])
            && ((in_array($response['status'], $failedStatuses))
                || (in_array($status, $failedStatuses))
            ) && $validSignature
        ) {
            $config = $this->configProviderMethodFactory->get(PayPerEmail::PAYMENT_METHOD_CODE);
            if ($config->getEnabledCronCancelPPE()) {
                return true;
            }
        }
        return false;
    }

    private function receivePushCheckPayPerEmail($response, $validSignature, $payment)
    {
        $status = $this->helper->getStatusByValue($this->postData['brq_statuscode'] ?? '');
        if ((isset($this->postData['add_frompayperemail'])
                || ($payment->getMethod() == 'buckaroo_magento2_payperemail'))
            && isset($this->postData['brq_transaction_method'])
            && (($response['status'] == 'BUCKAROO_MAGENTO2_STATUSCODE_SUCCESS')
                || ($status == 'BUCKAROO_MAGENTO2_STATUSCODE_SUCCESS')
            ) && $validSignature
        ) {
            if ($this->postData['brq_transaction_method'] != 'payperemail') {
                $brq_transaction_method = strtolower($this->postData['brq_transaction_method']);
                $payment                = $this->order->getPayment();
                $payment->setAdditionalInformation('isPayPerEmail', $brq_transaction_method);

                $options = new \Buckaroo\Magento2\Model\Config\Source\PaymentMethods\PayPerEmail();
                foreach ($options->toOptionArray() as $item) {
                    if (($item['value'] == $brq_transaction_method) && isset($item['code'])) {
                        $payment->setMethod($item['code']);
                        $payment->setAdditionalInformation(
                            AbstractMethod::BUCKAROO_ORIGINAL_TRANSACTION_KEY_KEY,
                            $this->getTransactionKey()
                        );
                        if ($item['code'] == 'buckaroo_magento2_creditcards') {
                            $payment->setAdditionalInformation('customer_creditcardcompany', $brq_transaction_method);
                        }
                    }
                }
                $payment->save();
                $this->order->save();
                return true;
            }
        }
        return false;
    }

    public function isPayPerEmailB2BModePush()
    {
        if (isset($this->postData['add_frompayperemail'])
            && isset($this->postData['brq_transaction_method'])
            && ($this->postData['brq_transaction_method'] == 'payperemail')
        ) {
            $this->logging->addDebug(__METHOD__ . '|1|');
            $config = $this->configProviderMethodFactory->get(PayPerEmail::PAYMENT_METHOD_CODE);
            if ($config->getEnabledB2B()) {
                $this->logging->addDebug(__METHOD__ . '|5|');
                return true;
            }
        }
        return false;
    }

    public function isPayPerEmailB2CModePush()
    {
        if (isset($this->postData['add_frompayperemail'])
            && isset($this->postData['brq_transaction_method'])
            && ($this->postData['brq_transaction_method'] == 'payperemail')
        ) {
            $this->logging->addDebug(__METHOD__ . '|1|');
            $config = $this->configProviderMethodFactory->get(PayPerEmail::PAYMENT_METHOD_CODE);
            if (!$config->getEnabledB2B()) {
                $this->logging->addDebug(__METHOD__ . '|5|');
                return true;
            }
        }
        return false;
    }

    public function isPayPerEmailB2BModePushInitial($response)
    {
        $this->logging->addDebug(__METHOD__ . '|1|');
        return $this->isPayPerEmailB2BModePush()
            && ($response['status'] == 'BUCKAROO_MAGENTO2_STATUSCODE_WAITING_ON_CONSUMER');
    }

    public function isPayPerEmailB2CModePushInitial($response)
    {
        $this->logging->addDebug(__METHOD__ . '|1|');
        return $this->isPayPerEmailB2CModePush()
            && ($response['status'] == 'BUCKAROO_MAGENTO2_STATUSCODE_WAITING_ON_CONSUMER');
    }

    public function isPayPerEmailB2BModePushPaid()
    {
        $this->logging->addDebug(__METHOD__ . '|1|');
        return $this->isPayPerEmailB2BModePush();
    }

    private function getOrderIncrementId()
    {
        $brqOrderId = false;

        if (isset($this->postData['brq_invoicenumber']) && strlen($this->postData['brq_invoicenumber']) > 0) {
            $brqOrderId = $this->postData['brq_invoicenumber'];
        }

        if (isset($this->postData['brq_ordernumber']) && strlen($this->postData['brq_ordernumber']) > 0) {
            $brqOrderId = $this->postData['brq_ordernumber'];
        }

        return $brqOrderId;
    }

    private function getLockPushProcessingPpeFilePath()
    {
        if ($brqOrderId = $this->getOrderIncrementId()) {
            return $this->dirList->getPath('tmp') . DIRECTORY_SEPARATOR . 'bk_push_ppe_' . sha1($brqOrderId);
        } else {
            return false;
        }
    }

    private function lockPushProcessingPpe()
    {
        if (isset($this->postData['add_frompayperemail'])) {
            $this->logging->addDebug(__METHOD__ . '|1|');
            if ($path = $this->getLockPushProcessingPpeFilePath()) {
                if ($fp = $this->fileSystemDriver->fileOpen($path, "w+")) {
                    $this->fileSystemDriver->fileLock($fp, LOCK_EX);
                    $this->logging->addDebug(__METHOD__ . '|5|');
                    return $fp;
                }
            }
        }
    }

    private function unlockPushProcessingPpe($lockHandler)
    {
        if (isset($this->postData['add_frompayperemail'])) {
            $this->logging->addDebug(__METHOD__ . '|1|');
            $this->fileSystemDriver->fileClose($lockHandler);
            if (($path = $this->getLockPushProcessingPpeFilePath()) && $this->fileSystemDriver->isExists($path)) {
                $this->fileSystemDriver->deleteFile($path);
                $this->logging->addDebug(__METHOD__ . '|5|');
            }
        }
    }

    private function processSucceededPushAuth($payment)
    {
        $authPpaymentMethods = [
            Afterpay::PAYMENT_METHOD_CODE,
            Afterpay2::PAYMENT_METHOD_CODE,
            Afterpay20::PAYMENT_METHOD_CODE,
            Creditcard::PAYMENT_METHOD_CODE,
            Klarnakp::PAYMENT_METHOD_CODE
        ];

        if (in_array($payment->getMethod(), $authPpaymentMethods)) {
            if ((($payment->getMethod() == Klarnakp::PAYMENT_METHOD_CODE)
                    || (
                        !empty($this->postData['brq_transaction_type'])
                        && in_array($this->postData['brq_transaction_type'], ['I038', 'I880'])
                    )
                ) && !empty($this->postData['brq_statuscode'])
                && ($this->postData['brq_statuscode'] == 190)
            ) {
                $this->logging->addDebug(__METHOD__ . '|88|' . var_export($payment->getMethod(), true));
                $this->order->setState(Order::STATE_PROCESSING);
                $this->order->save();
            }
        }
    }
}
