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

namespace TIG\Buckaroo\Model;

use Magento\Framework\Webapi\Rest\Request;
use Magento\Sales\Api\Data\TransactionInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Email\Sender\InvoiceSender;
use Magento\Sales\Model\Order\Email\Sender\OrderSender;
use Magento\Sales\Model\Order\Payment\Transaction;
use TIG\Buckaroo\Api\PushInterface;
use TIG\Buckaroo\Helper\Data;
use TIG\Buckaroo\Logging\Log;
use TIG\Buckaroo\Model\ConfigProvider\Account;
use TIG\Buckaroo\Model\ConfigProvider\Method\Factory;
use TIG\Buckaroo\Model\Method\AbstractMethod;
use TIG\Buckaroo\Model\Method\Giftcards;
use TIG\Buckaroo\Model\Method\Transfer;
use TIG\Buckaroo\Model\Method\Paypal;
use TIG\Buckaroo\Model\Method\SepaDirectDebit;
use TIG\Buckaroo\Model\Method\Sofortbanking;
use TIG\Buckaroo\Model\Method\Alipay;
use TIG\Buckaroo\Model\Method\Wechatpay;
use TIG\Buckaroo\Model\Refund\Push as RefundPush;
use TIG\Buckaroo\Model\Validator\Push as ValidatorPush;

/**
 * Class Push
 *
 * @package TIG\Buckaroo\Model
 */
class Push implements PushInterface
{
    const BUCK_PUSH_CANCEL_AUTHORIZE_TYPE  = 'I014';
    const BUCK_PUSH_ACCEPT_AUTHORIZE_TYPE  = 'I013';

    const BUCK_PUSH_TYPE_TRANSACTION            = 'transaction_push';
    const BUCK_PUSH_TYPE_INVOICE                = 'invoice_push';
    const BUCK_PUSH_TYPE_INVOICE_INCOMPLETE     = 'incomplete_invoice_push';
    const BUCK_PUSH_TYPE_DATAREQUEST            = 'datarequest_push';

    const BUCKAROO_RECEIVED_TRANSACTIONS = 'buckaroo_received_transactions';

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

    /**
     * @param Order                $order
     * @param TransactionInterface $transaction
     * @param Request              $request
     * @param ValidatorPush        $validator
     * @param OrderSender          $orderSender
     * @param InvoiceSender        $invoiceSender
     * @param Data                 $helper
     * @param Account              $configAccount
     * @param RefundPush           $refundPush
     * @param Log                  $logging
     * @param Factory              $configProviderMethodFactory
     * @param OrderStatusFactory   $orderStatusFactory
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
        OrderStatusFactory $orderStatusFactory
    ) {
        $this->order                        = $order;
        $this->transaction                  = $transaction;
        $this->request                      = $request;
        $this->validator                    = $validator;
        $this->orderSender                  = $orderSender;
        $this->invoiceSender                = $invoiceSender;
        $this->helper                       = $helper;
        $this->configAccount                = $configAccount;
        $this->refundPush                   = $refundPush;
        $this->logging                      = $logging;
        $this->configProviderMethodFactory  = $configProviderMethodFactory;
        $this->orderStatusFactory           = $orderStatusFactory;
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
        $this->logging->addDebug(print_r($this->originalPostData, true));

        //Check if the push can be processed and if the order can be updated IMPORTANT => use the original post data.
        $validSignature = $this->validator->validateSignature($this->originalPostData);

        if (!$this->isPushNeeded()) {
            return true;
        }

        $this->loadOrder();

        $transactionType = $this->getTransactionType();

        //Validate status code and return response
        $postDataStatusCode = $this->getStatusCode();
        $response = $this->validator->validateStatusCode($postDataStatusCode);

        $canUpdateOrder = $this->canUpdateOrderStatus();

        //Check if the push is a refund request or cancel authorize
        if (isset($this->postData['brq_amount_credit'])) {
            if ($response['status'] !== 'TIG_BUCKAROO_STATUSCODE_SUCCESS'
                && $this->order->isCanceled()
                && $this->postData['brq_transaction_type'] == self::BUCK_PUSH_CANCEL_AUTHORIZE_TYPE
                && $validSignature
            ) {
                return $this->processCancelAuthorize();
            } elseif ($response['status'] !== 'TIG_BUCKAROO_STATUSCODE_SUCCESS'
                && !$this->order->hasInvoices()
            ) {
                throw new \TIG\Buckaroo\Exception(
                    __('Refund failed ! Status : %1 and the order does not contain an invoice', $response['status'])
                );
            }
            return $this->refundPush->receiveRefundPush($this->postData, $validSignature, $this->order);
        }

        //Last validation before push can be completed
        if (!$validSignature) {
            $this->logging->addDebug('Invalid push signature');
            throw new \TIG\Buckaroo\Exception(__('Signature from push is incorrect'));
            //If the signature is valid but the order cant be updated, try to add a notification to the order comments.
        } elseif ($validSignature && !$canUpdateOrder) {
            $this->setOrderNotificationNote(__('The order has already been processed.'));
            $this->logging->addDebug('Order can not receive updates');
            throw new \TIG\Buckaroo\Exception(
                __('Signature from push is correct but the order can not receive updates')
            );
        }

        $payment = $this->order->getPayment();
        $skipFirstPush = $payment->getAdditionalInformation('skip_push');

        /**
         * Buckaroo Push is send before Response, for correct flow we skip the first push
         * for some payment methods
         * @todo when buckaroo changes the push / response order this can be removed
         */
        if ($skipFirstPush > 0) {
            $payment->setAdditionalInformation('skip_push', $skipFirstPush - 1);
            $payment->save();
            throw new \TIG\Buckaroo\Exception(__('Skipped handling this push, first handle response, action will be taken on the next push.'));
        }

        $this->setTransactionKey();

        switch ($transactionType) {
            case self::BUCK_PUSH_TYPE_TRANSACTION:
            case self::BUCK_PUSH_TYPE_DATAREQUEST:
                $this->processPush($response);
                break;
            case self::BUCK_PUSH_TYPE_INVOICE:
                $this->processCm3Push();
                break;
            case self::BUCK_PUSH_TYPE_INVOICE_INCOMPLETE:
                throw new \TIG\Buckaroo\Exception(
                    __('Skipped handling this invoice push because it is too soon.')
                );
                break;
        }

        $this->order->save();

        return true;
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
        $this->postData = $postDataLowerCase;
    }

    /**
     * Check if it is needed to handle the push message based on postdata
     * @return bool
     */
    private function isPushNeeded()
    {
        if ($this->hasPostData('add_initiated_by_magento', 1) &&
            $this->hasPostData('add_service_action_from_magento',
                ['capture','cancelauthorize','cancelreserve','refund'])
        ) {
            return false;
        }

        if ($this->hasPostData('add_initiated_by_magento', 1) &&
            $this->hasPostData('brq_transaction_method', 'klarna') &&
            $this->hasPostData('add_service_action_from_magento', 'pay')
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
        $brqOrderId = false;

        if (isset($this->postData['brq_invoicenumber']) && strlen($this->postData['brq_invoicenumber']) > 0) {
            $brqOrderId = $this->postData['brq_invoicenumber'];
        }

        if (isset($this->postData['brq_ordernumber']) && strlen($this->postData['brq_ordernumber']) > 0) {
            $brqOrderId = $this->postData['brq_ordernumber'];
        }

        //Check if the order can receive further status updates
        $this->order->loadByIncrementId($brqOrderId);

        if (!$this->order->getId()) {
            $this->logging->addDebug('Order could not be loaded by brq_invoicenumber or brq_ordernumber');
            // try to get order by transaction id on payment.
                $this->order = $this->getOrderByTransactionKey();
        }
    }

    /**
     * @return int|string
     */
    private function getStatusCode()
    {
        $transactionType = $this->getTransactionType();
        $statusCode = 0;

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
        } catch (\TIG\Buckaroo\Exception $e) {
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
     * @throws \TIG\Buckaroo\Exception
     */
    public function processPush($response)
    {
        $this->logging->addDebug('RESPONSE STATUS: '.$response['status']);
        $payment = $this->order->getPayment();

        if (!$payment->getMethodInstance()->canProcessPostData($payment, $this->postData)) {
            return;
        }

        if ($this->giftcardPartialPayment()) {
            return;
        }

        $newStatus = $this->orderStatusFactory->get($this->postData['brq_statuscode'], $this->order);

        switch ($response['status']) {
            case 'TIG_BUCKAROO_STATUSCODE_TECHNICAL_ERROR':
            case 'TIG_BUCKAROO_STATUSCODE_VALIDATION_FAILURE':
            case 'TIG_BUCKAROO_STATUSCODE_CANCELLED_BY_MERCHANT':
            case 'TIG_BUCKAROO_STATUSCODE_CANCELLED_BY_USER':
            case 'TIG_BUCKAROO_STATUSCODE_FAILED':
            case 'TIG_BUCKAROO_STATUSCODE_REJECTED':
                $this->processFailedPush($newStatus, $response['message']);
                break;
            case 'TIG_BUCKAROO_STATUSCODE_SUCCESS':
                if ($this->order->getPayment()->getMethod() == \TIG\Buckaroo\Model\Method\Paypal::PAYMENT_METHOD_CODE) {
                    $paypalConfig = $this->configProviderMethodFactory
                        ->get(\TIG\Buckaroo\Model\Method\Paypal::PAYMENT_METHOD_CODE);

                    /**
                     * @var \TIG\Buckaroo\Model\ConfigProvider\Method\Paypal $paypalConfig
                     */
                    $newSellersProtectionStatus = $paypalConfig->getSellersProtectionIneligible();
                    if ($paypalConfig->getSellersProtection() && !empty($newSellersProtectionStatus)) {
                        $newStatus = $newSellersProtectionStatus;
                    }
                }
                $this->processSucceededPush($newStatus, $response['message']);
                break;
            case 'TIG_BUCKAROO_STATUSCODE_NEUTRAL':
                $this->setOrderNotificationNote($response['message']);
                break;
            case 'TIG_BUCKAROO_STATUSCODE_PAYMENT_ON_HOLD':
            case 'TIG_BUCKAROO_STATUSCODE_WAITING_ON_CONSUMER':
            case 'TIG_BUCKAROO_STATUSCODE_PENDING_PROCESSING':
            case 'TIG_BUCKAROO_STATUSCODE_WAITING_ON_USER_INPUT':
                $this->processPendingPaymentPush($newStatus, $response['message']);
                break;
        }
    }

    public function processCm3Push()
    {
        $invoiceKey = $this->postData['brq_invoicekey'];
        $savedInvoiceKey = $this->order->getPayment()->getAdditionalInformation('buckaroo_cm3_invoice_key');

        if ($invoiceKey != $savedInvoiceKey) {
            return;
        }

        $this->updateCm3InvoiceStatus();
        $this->sendCm3ConfirmationMail();
    }

    private function updateCm3InvoiceStatus()
    {
        $isPaid = filter_var(strtolower($this->postData['brq_ispaid']), FILTER_VALIDATE_BOOLEAN);
        $canInvoice = ($this->order->canInvoice() && !$this->order->hasInvoices());
        $store = $this->order->getStore();

        $amount = floatval($this->postData['brq_amountdebit']);
        $amount = $this->order->getBaseCurrency()->formatTxt($amount);
        $statusMessage = 'Payment push status : Creditmanagement invoice with a total amount of '
            . $amount . ' has been paid';

        if (!$isPaid && !$canInvoice) {
            $statusMessage = 'Payment push status : Creditmanagement invoice has been (partially) refunded';
        }

        if (!$isPaid && $canInvoice) {
            $statusMessage = 'Payment push status : Waiting for consumer';
        }

        if ($isPaid && $canInvoice) {
            $originalKey = AbstractMethod::BUCKAROO_ORIGINAL_TRANSACTION_KEY_KEY;
            $this->postData['brq_transactions'] = $this->order->getPayment()->getAdditionalInformation($originalKey);
            $this->postData['brq_amount'] = $this->postData['brq_amountdebit'];

            $this->saveInvoice();
        }

        $this->updateOrderStatus($this->order->getState(), $this->order->getStatus(), $statusMessage);
    }

    private function sendCm3ConfirmationMail()
    {
        $store = $this->order->getStore();
        $cm3StatusCode = 0;

        if (isset($this->postData['brq_invoicestatuscode'])) {
            $cm3StatusCode = $this->postData['brq_invoicestatuscode'];
        }

        /** @var \Magento\Payment\Model\MethodInterface $paymentMethod */
        $paymentMethod = $this->order->getPayment()->getMethodInstance();
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
            || $this->postData['brq_amount'] >= $this->order->getGrandTotal()
            || empty($this->postData['brq_relatedtransaction_partialpayment'])
        ) {
            return false;
        }

        $payment->setAdditionalInformation(
            AbstractMethod::BUCKAROO_ORIGINAL_TRANSACTION_KEY_KEY,
            $this->postData['brq_relatedtransaction_partialpayment']
        );

        $this->addGiftcardPartialPaymentToPaymentInformation();

        return true;
    }

    protected function addGiftcardPartialPaymentToPaymentInformation()
    {
        $payment = $this->order->getPayment();

        $transactionAmount =  (isset($this->postData['brq_amount'])) ? $this->postData['brq_amount'] : 0;
        $transactionKey =  (isset($this->postData['brq_transactions'])) ? $this->postData['brq_transactions'] : '';
        $transactionMethod = (isset($this->postData['brq_transaction_method'])) ? $this->postData['brq_transaction_method'] : '';

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
        $payment     = $this->order->getPayment();
        $originalKey = AbstractMethod::BUCKAROO_ORIGINAL_TRANSACTION_KEY_KEY;
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

        $payment     = $this->order->getPayment();

        if (!$payment->getAdditionalInformation(self::BUCKAROO_RECEIVED_TRANSACTIONS)) {
            $payment->setAdditionalInformation(
                self::BUCKAROO_RECEIVED_TRANSACTIONS,
                array($this->postData['brq_transactions'] => floatval($this->postData['brq_amount']))
            );
        } else {
            $buckarooTransactionKeysArray = $payment->getAdditionalInformation(self::BUCKAROO_RECEIVED_TRANSACTIONS);

            $buckarooTransactionKeysArray[$this->postData['brq_transactions']] = floatval($this->postData['brq_amount']);

            $payment->setAdditionalInformation(self::BUCKAROO_RECEIVED_TRANSACTIONS, $buckarooTransactionKeysArray);
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

        return $trxId;
    }

    /**
     * Sometimes the push does not contain the order id, when thats the case try to get the order by his payment,
     * by using its own transactionkey.
     *
     * @return Order
     * @throws \TIG\Buckaroo\Exception
     */
    protected function getOrderByTransactionKey()
    {
        $trxId = $this->getTransactionKey();

        $this->transaction->load($trxId, 'txn_id');
        $order = $this->transaction->getOrder();

        if (!$order) {
            throw new \TIG\Buckaroo\Exception(__('There was no order found by transaction Id'));
        }

        return $order;
    }

    /**
     * Checks if the order can be updated by checking its state and status.
     *
     * @return bool
     */
    protected function canUpdateOrderStatus()
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

        /**
         * If the types are not the same and the order can receive an invoice the order can be udpated by BPE.
         */
        if ($completedStateAndStatus   != $currentStateAndStatus
            && $cancelledStateAndStatus != $currentStateAndStatus
            && $holdedStateAndStatus    != $currentStateAndStatus
            && $closedStateAndStatus    != $currentStateAndStatus
        ) {
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
        $description = 'Payment status : '.$message;

        $store = $this->order->getStore();

        $buckarooCancelOnFailed = $this->configAccount->getCancelOnFailed($store);

        if ($buckarooCancelOnFailed && $this->order->canCancel()) {
            $this->logging->addDebug('Buckaroo push failed : '.$message.' : Cancel order.');

            // BUCKM2-78: Never automatically cancelauthorize via push for afterpay
            // setting parameter which will cause to stop the cancel process on
            // Buckaroo/Model/Method/AbstractMethod.php:880
            $payment = $this->order->getPayment();
            if ($payment->getMethodInstance()->getCode() == 'tig_buckaroo_afterpay'
                || $payment->getMethodInstance()->getCode() == 'tig_buckaroo_afterpay2'
            ) {
                $payment->setAdditionalInformation('buckaroo_failed_authorize', 1);
                $payment->save();
            }

            $this->order->cancel()->save();
        }

        $this->updateOrderStatus(Order::STATE_CANCELED, $newStatus, $description);

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
        $amount = $this->order->getTotalDue();

        if (isset($this->originalPostData['brq_amount']) && !empty($this->originalPostData['brq_amount'])) {
            $amount = floatval($this->originalPostData['brq_amount']);
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
            $this->orderSender->send($this->order);
        }

        /** force state eventhough this can lead to a transition of the order
         *  like new -> processing
         */
        $forceState = false;

        if ($paymentMethod->canPushInvoice($this->postData)) {
            $description = 'Payment status : <strong>' . $message . "</strong><br/>";
            $description .= 'Total amount of ' . $this->order->getBaseCurrency()->formatTxt($amount) . ' has been paid';
        } else {
            $description = 'Authorization status : <strong>' . $message . "</strong><br/>";
            $description .= 'Total amount of ' . $this->order->getBaseCurrency()->formatTxt($this->order->getTotalDue())
                . ' has been authorized. Please create an invoice to capture the authorized amount.';
            $forceState = true;
        }

        if ($paymentMethod->canPushInvoice($this->postData)) {
            $this->saveInvoice();
        }

        $this->updateOrderStatus(Order::STATE_PROCESSING, $newStatus, $description, $forceState);

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
        $store = $this->order->getStore();
        $payment = $this->order->getPayment();

        /** @var \Magento\Payment\Model\MethodInterface $paymentMethod */
        $paymentMethod = $payment->getMethodInstance();

        // Transfer has a slightly different flow where a successful order has a 792 status code instead of an 190 one
        if (!$this->order->getEmailSent()
            && in_array($payment->getMethod(), array(   Transfer::PAYMENT_METHOD_CODE,
                                                        Paypal::PAYMENT_METHOD_CODE,
                                                        SepaDirectDebit::PAYMENT_METHOD_CODE,
                                                        Sofortbanking::PAYMENT_METHOD_CODE,
                                                        Alipay::PAYMENT_METHOD_CODE,
                                                        Wechatpay::PAYMENT_METHOD_CODE
                    ))
            && ($this->configAccount->getOrderConfirmationEmail($store)
                || $paymentMethod->getConfigData('order_email', $store)
            )
        ) {
            $this->orderSender->send($this->order);
        }

        $description = 'Payment push status : '.$message;

        $this->updateOrderStatus(Order::STATE_PROCESSING, $newStatus, $description);

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
        } catch (\TIG\Buckaroo\Exception $e) {
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
        if ($this->order->getState() == $orderState || $force == true) {
            $this->order->addStatusHistoryComment($description, $newStatus);
        } else {
            $this->order->addStatusHistoryComment($description);
        }
    }

    /**
     * Creates and saves the invoice and adds for each invoice the buckaroo transaction keys
     * Only when the order can be invoiced and has not been invoiced before.
     *
     * @return bool
     * @throws \TIG\Buckaroo\Exception
     */
    protected function saveInvoice()
    {
        if (!$this->order->canInvoice() || $this->order->hasInvoices()) {
            $this->logging->addDebug('Order can not be invoiced');
            throw new \TIG\Buckaroo\Exception(__('Order can not be invoiced'));
        }

        /**
         * Only when the order can be invoiced and has not been invoiced before.
         */

        $this->addTransactionData();

        /**
         * @var \Magento\Sales\Model\Order\Payment $payment
         */
        $payment = $this->order->getPayment();

        if ($payment->getMethod() == Giftcards::PAYMENT_METHOD_CODE) {
            $this->setReceivedPaymentFromBuckaroo();

            $invoiceAmount = floatval($this->postData['brq_amount']);
            $payment->registerCaptureNotification($invoiceAmount, true);
            $payment->save();

            $receivedPaymentsArray = $payment->getAdditionalInformation(self::BUCKAROO_RECEIVED_TRANSACTIONS);

            if (!is_array($receivedPaymentsArray)) {
                return;
            }

            /* partial payment, do not create invoice yet */
            if ($this->order->getGrandTotal() != array_sum($receivedPaymentsArray)) {
                return;
            }

            /* partially paid giftcard, create invoice */
            if (count($receivedPaymentsArray) > 1) {
                $payment->capture(); //creates invoice
                $payment->save();
            }
        } else {
            $payment->registerCaptureNotification($this->order->getGrandTotal());
            $payment->save();
        }

        $this->order->setIsInProcess(true);
        $this->order->save();

        $transactionKey = $this->getTransactionKey();

        if (strlen($transactionKey) <= 0) {
            return true;
        }

        /** @var \Magento\Sales\Model\Order\Invoice $invoice */
        foreach ($this->order->getInvoiceCollection() as $invoice) {
            $invoice->setTransactionId($transactionKey)->save();

            if (!$invoice->getEmailSent() && $this->configAccount->getInvoiceEmail($this->order->getStore())) {
                $this->invoiceSender->send($invoice, true);
            }
        }

        return true;
    }

    /**
     * Get Transactions
     */
    public function getTransactionsByOrder()
    {
        $this->order->getPayment();
    }

    /**
     * @return Order\Payment
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function addTransactionData()
    {
        /**
         * @var \Magento\Sales\Model\Order\Payment $payment
         */
        $payment = $this->order->getPayment();

        $transactionKey = $this->getTransactionKey();

        if (strlen($transactionKey) <= 0) {
            throw new \TIG\Buckaroo\Exception(__('There was no transaction ID found'));
        }

        /**
         * Save the transaction's response as additional info for the transaction.
         */
        $rawInfo = $this->helper->getTransactionAdditionalInfo($this->postData);

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
        $payment->setTransactionId($transactionKey . '-capture');
        /**
         * @noinspection PhpUndefinedMethodInspection
         */
        $payment->setParentTransactionId($transactionKey);
        $payment->setAdditionalInformation(
            \TIG\Buckaroo\Model\Method\AbstractMethod::BUCKAROO_ORIGINAL_TRANSACTION_KEY_KEY,
            $transactionKey
        );

        return $payment;
    }

    /**
     * Get Correct order amount
     *
     * @return int $orderAmount
     */
    protected function getCorrectOrderAmount()
    {
        if ($this->postData['brq_currency'] == $this->order->getBaseCurrencyCode()) {
            $orderAmount = $this->order->getBaseGrandTotal();
        } else {
            $orderAmount = $this->order->getGrandTotal();
        }

        return $orderAmount;
    }
}
