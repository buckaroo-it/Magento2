<?php

namespace Buckaroo\Magento2\Model\Push;

use Buckaroo\Magento2\Api\PushProcessorInterface;
use Buckaroo\Magento2\Api\PushRequestInterface;
use Buckaroo\Magento2\Exception as BuckarooException;
use Buckaroo\Magento2\Helper\Data;
use Buckaroo\Magento2\Logging\Log;
use Buckaroo\Magento2\Model\BuckarooStatusCode;
use Buckaroo\Magento2\Model\ConfigProvider\Method\Giftcards;
use Buckaroo\Magento2\Model\ConfigProvider\Method\Transfer;
use Buckaroo\Magento2\Model\Validator\Push as ValidatorPush;
use Buckaroo\Magento2\Service\LockerProcess;
use Buckaroo\Magento2\Service\Push\OrderRequestService;
use Magento\Framework\Exception\FileSystemException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Sales\Api\Data\TransactionInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Payment;
use Magento\Sales\Model\Order\Payment as OrderPayment;
use Magento\Sales\Model\Order\Payment\Transaction;

class DefaultProcessor implements PushProcessorInterface
{
    /**
     * @var OrderRequestService
     */
    private OrderRequestService $orderService;

    /**
     * @var PushRequestInterface
     */
    protected PushRequestInterface $pushRequest;

    /**
     * @var Log $logging
     */
    protected Log $logging;

    /**
     * @var Order|OrderPayment $order
     */
    protected $order;

    /**
     * @var Transaction
     */
    protected $transaction;

    /**
     * @var Data
     */
    protected Data $helper;

    /**
     * @var LockerProcess
     */
    protected LockerProcess $lockerProcess;

    /**
     * @var ValidatorPush $validator
     */
    protected ValidatorPush $validator;

    public function __construct(
        OrderRequestService $orderRequestService,
        Log $logging,
        TransactionInterface $transaction,
        LockerProcess $lockerProcess,
        Data $helper,
        ValidatorPush $validator
    ) {
        $this->order = $orderRequestService->getOrderByRequest();
        $this->logging = $logging;
        $this->transaction = $transaction;
        $this->lockerProcess = $lockerProcess;
        $this->helper = $helper;
        $this->validator = $validator;
    }

    /**
     * @throws BuckarooException
     * @throws FileSystemException
     */
    public function processPush(PushRequestInterface $pushRequest): bool
    {
        $this->pushRequest = $pushRequest;

        // Skip Push
        if ($this->skipPush()) {
            return true;
        }


        // Handle Group Transaction



        $transactionType = $this->getTransactionType();
        $postDataStatusCode = $this->getStatusCode();
        $this->logging->addDebug(__METHOD__ . '|1_5|' . var_export($postDataStatusCode, true));
        $this->logging->addDebug(__METHOD__ . '|1_10|' . var_export($transactionType, true));

        $response = $this->validator->validateStatusCode($postDataStatusCode);


        $this->lockerProcess->unlockProcess();
    }

    /**
     * Creates and saves the invoice and adds for each invoice the buckaroo transaction keys
     * Only when the order can be invoiced and has not been invoiced before.
     *
     * @return bool
     * @throws BuckarooException
     * @throws LocalizedException
     *
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     * @SuppressWarnings(PHPMD.NPathComplexity)
     */
    protected function saveInvoice(): bool
    {
        $this->logging->addDebug(__METHOD__ . '|1|');
        if (!$this->forceInvoice
            && (!$this->order->canInvoice() || $this->order->hasInvoices())) {
            $this->logging->addDebug('Order can not be invoiced');
            //throw new BuckarooException(__('Order can not be invoiced'));
            return false;

        }

        $this->logging->addDebug(__METHOD__ . '|5|');

        /**
         * Only when the order can be invoiced and has not been invoiced before.
         */
        if (!$this->isGroupTransactionInfoType()) {
            $this->addTransactionData();
        }

        /**
         * @var Payment $payment
         */
        $payment = $this->order->getPayment();

        $invoiceAmount = 0;
        if (!empty($this->pushRequst->getAmount())) {
            $invoiceAmount = floatval($this->pushRequst->getAmount());
        }
        if (($payment->getMethod() == Giftcards::CODE)
            && $invoiceAmount != $this->order->getGrandTotal()
        ) {
            $this->setReceivedPaymentFromBuckaroo();

            $payment->registerCaptureNotification($invoiceAmount, true);
            $payment->save();

            $receivedPaymentsArray = $payment->getAdditionalInformation(self::BUCKAROO_RECEIVED_TRANSACTIONS);

            if (!is_array($receivedPaymentsArray)) {
                return false;
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

            if (!empty($this->pushRequst->getInvoiceNumber())
                && $this->groupTransaction->isGroupTransaction($this->pushRequst->getInvoiceNumber())) {
                $this->logging->addDebug(__METHOD__ . '|27|');
                $invoice->setState(2);
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
     * Load the order from the Push Data based on the Order Increment ID or transaction key.
     *
     * @return void
     * @throws \Exception
     */
    protected function loadOrder(): void
    {
        $brqOrderId = $this->getOrderIncrementId();

        $this->order->loadByIncrementId((string)$brqOrderId);

        if (!$this->order->getId()) {
            $this->logging->addDebug('Order could not be loaded by Invoice Number or Order Number');
            // Try to get order by transaction id on payment.
            $this->order = $this->getOrderByTransactionKey();
        }
    }

    /**
     * Get the order increment ID based on the invoice number or order number.
     *
     * @return string|null
     */
    protected function getOrderIncrementId(): ?string
    {
        $brqOrderId = null;

        if (!empty($this->pushRequest->getInvoiceNumber()) && strlen($this->pushRequest->getInvoiceNumber()) > 0) {
            $brqOrderId = $this->pushRequest->getInvoiceNumber();
        }

        if (!empty($this->pushRequest->getOrderNumber()) && strlen($this->pushRequest->getOrderNumber()) > 0) {
            $brqOrderId = $this->pushRequest->getOrderNumber();
        }

        return $brqOrderId;
    }

    /**
     * Sometimes the push does not contain the order id, when that's the case try to get the order by his payment,
     * by using its own transaction key.
     *
     * @return OrderPayment
     * @throws \Exception
     */
    protected function getOrderByTransactionKey(): OrderPayment
    {
        $trxId = $this->getTransactionKey();

        $this->transaction->load($trxId, 'txn_id');
        $order = $this->transaction->getOrder();

        if (!$order) {
            throw new \Exception(__('There was no order found by transaction Id'));
        }

        return $order;
    }

    /**
     * Retrieves the transaction key from the push request.
     *
     * @return string
     */
    protected function getTransactionKey(): string
    {
        $trxId = '';

        if (!empty($this->pushRequest->getTransactions())) {
            $trxId = $this->pushRequest->getTransactions();
        }

        if (!empty($this->pushRequest->getDatarequest())) {
            $trxId = $this->pushRequest->getDatarequest();
        }

        if (!empty($this->pushRequest->getRelatedtransactionRefund())) {
            $trxId = $this->pushRequest->getRelatedtransactionRefund();
        }

        return $trxId;
    }

    /**
     * Determine if the lock push processing criteria are met.
     *
     * @return bool
     */
    protected function lockPushProcessingCriteria(): bool
    {
        return false;
    }


    /**
     * Skip the push if the conditions are met.
     *
     * @return bool
     * @throws \Exception
     */
    protected function skipPush()
    {
        if ($this->skipPendingRefundPush()) {
            return true;
        }

        if ($this->skipKlarnaCapture()) {
            return true;
        }

        // Skip Push based on specific condition
        if (!$this->skipSpecificTypesOfRequsts()) {
            return true;
        }

        return false;
    }

    /**
     * Check if it is needed to handle the push message based on postdata
     *
     * @return bool
     * @throws \Exception
     *
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     */
    private function skipSpecificTypesOfRequsts(): bool
    {
        $this->logging->addDebug(__METHOD__ . '|1|');

        $types = ['capture', 'cancelauthorize', 'cancelreservation'];
        if ($this->pushRequest->hasAdditionalInformation('initiated_by_magento', 1)
            && $this->pushRequest->hasAdditionalInformation('service_action_from_magento', $types)
            && empty($this->pushRequest->getRelatedtransactionRefund())
        ) {
            return true;
        }

        return false;
    }

    /**
     * Skip Pending Refund Push
     *
     * @return bool
     * @throws \Exception
     */
    protected function skipPendingRefundPush(): bool
    {
        if ($this->pushRequest->hasAdditionalInformation('initiated_by_magento', 1)
            && $this->pushRequest->hasAdditionalInformation('service_action_from_magento', ['refund'])
        ) {
            if ($this->pushRequest->hasPostData('statuscode', BuckarooStatusCode::SUCCESS)
                && !empty($this->pushRequest->getRelatedtransactionRefund())
                && $this->receivePushCheckDuplicates(
                    BuckarooStatusCode::PENDING_APPROVAL,
                    $this->pushRequest->getRelatedtransactionRefund()
                )) {
                $this->logging->addDebug(__METHOD__ . '|4|');
                return false;
            }
            $this->logging->addDebug(__METHOD__ . '|5|');
            return true;
        }

        return false;
    }

    protected function skipKlarnaCapture() {
        if ($this->pushRequest->hasAdditionalInformation('initiated_by_magento', 1)
            && $this->pushRequest->hasPostData('transaction_method', ['klarnakp', 'KlarnaKp'])
            && $this->pushRequest->hasAdditionalInformation('service_action_from_magento', 'pay')
            && !empty($this->pushRequest->getServiceKlarnakpCaptureid())
        ) {
            return true;
        }

        return false;
    }

    /**
     * Check for duplicate transaction pushes from Buckaroo and update the payment transaction statuses accordingly.
     *
     * @param int|null $receivedStatusCode
     * @param string|null $trxId
     * @return bool
     * @throws \Exception
     *
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     * @SuppressWarnings(PHPMD.NPathComplexity)
     */
    private function receivePushCheckDuplicates(int $receivedStatusCode = null, string $trxId = null): bool
    {
        $this->logging->addDebug(__METHOD__ . '|1|' . var_export($this->order->getPayment()->getMethod(), true));

        $save = false;
        if (!$receivedStatusCode) {
            $save = true;
            if (empty($this->pushRequest->getStatusCode())) {
                return false;
            }
            $receivedStatusCode = $this->pushRequest->getStatusCode();
        }
        if (!$trxId) {
            if (empty($this->pushRequest->getTransactions())) {
                return false;
            }
            $trxId = $this->pushRequest->getTransactions();
        }
        $payment               = $this->order->getPayment();
        $ignoredPaymentMethods = [
            Giftcards::CODE,
            Transfer::CODE
        ];
        if ($payment
            && $payment->getMethod()
            && $receivedStatusCode
            && ($this->getTransactionType() == self::BUCK_PUSH_TYPE_TRANSACTION)
            && (!in_array($payment->getMethod(), $ignoredPaymentMethods))
        ) {
            $this->logging->addDebug(__METHOD__ . '|5|');

            $receivedTrxStatuses = $payment->getAdditionalInformation(
                self::BUCKAROO_RECEIVED_TRANSACTIONS_STATUSES
            );
            $this->logging->addDebug(__METHOD__ . '|10|' .
                var_export([$receivedTrxStatuses, $receivedStatusCode], true));
            if ($receivedTrxStatuses
                && is_array($receivedTrxStatuses)
                && !empty($trxId)
                && isset($receivedTrxStatuses[$trxId])
                && ($receivedTrxStatuses[$trxId] == $receivedStatusCode)
            ) {
                $orderStatus = $this->helper->getOrderStatusByState($this->order, Order::STATE_NEW);
                $statusCode = $this->helper->getStatusCode('BUCKAROO_MAGENTO2_STATUSCODE_SUCCESS');
                if (($this->order->getState() == Order::STATE_NEW)
                    && ($this->order->getStatus() == $orderStatus)
                    && ($receivedStatusCode == $statusCode)
                ) {
                    //allow duplicated pushes for 190 statuses in case if order stills to be new/pending
                    $this->logging->addDebug(__METHOD__ . '|13|');
                    return false;
                }

                $this->logging->addDebug(__METHOD__ . '|15|');
                return true;
            }
            if ($save) {
                $this->logging->addDebug(__METHOD__ . '|17|');
                $this->setReceivedTransactionStatuses();
                $payment->save();
            }
        }
        $this->logging->addDebug(__METHOD__ . '|20|');

        return false;
    }

    /**
     * Determine the transaction type based on push request data and the saved invoice key.
     *
     * @return bool|string
     *
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     */
    public function getTransactionType()
    {
        //If an order has an invoice key, then it should only be processed by invoice pushes
        $savedInvoiceKey = (string)$this->order->getPayment()->getAdditionalInformation('buckaroo_cm3_invoice_key');

        if (!empty($this->pushRequest->getInvoicekey())
            && !empty($this->pushRequest->getSchemekey())
            && strlen($savedInvoiceKey) > 0
        ) {
            return self::BUCK_PUSH_TYPE_INVOICE;
        }

        if (!empty($this->pushRequest->getInvoicekey())
            && !empty($this->pushRequest->getSchemekey())
            && strlen($savedInvoiceKey) == 0
        ) {
            return self::BUCK_PUSH_TYPE_INVOICE_INCOMPLETE;
        }

        if (!empty($this->pushRequest->getDatarequest())) {
            return self::BUCK_PUSH_TYPE_DATAREQUEST;
        }

        if (empty($this->pushRequest->getInvoicekey())
            && empty($this->pushRequest->getServiceCreditmanagement3Invoicekey())
            && empty($this->pushRequest->getDatarequest())
            && strlen($savedInvoiceKey) <= 0
        ) {
            return self::BUCK_PUSH_TYPE_TRANSACTION;
        }

        return false;
    }

    /**
     * Retrieve the status code from the push request based on the transaction type.
     *
     * @return int|string
     *
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     */
    private function getStatusCode()
    {
        $transactionType = $this->getTransactionType();
        $statusCode = 0;
        switch ($transactionType) {
            case self::BUCK_PUSH_TYPE_TRANSACTION:
            case self::BUCK_PUSH_TYPE_DATAREQUEST:
                if ($this->pushRequest->getStatusCode() !== null) {
                    $statusCode = $this->pushRequest->getStatusCode();
                }
                break;
            case self::BUCK_PUSH_TYPE_INVOICE:
            case self::BUCK_PUSH_TYPE_INVOICE_INCOMPLETE:
                if (!empty($this->pushRequest->getEventparametersStatuscode())) {
                    $statusCode = $this->pushRequest->getEventparametersStatuscode();
                }

                if (!empty($this->pushRequest->getEventparametersTransactionstatuscode())) {
                    $statusCode = $this->pushRequest->getEventparametersTransactionstatuscode();
                }
                break;
        }

        $statusCodeSuccess = $this->helper->getStatusCode('BUCKAROO_MAGENTO2_STATUSCODE_SUCCESS');
        if ($this->pushRequest->getStatusCode() !== null
            && ($this->pushRequest->getStatusCode() == $statusCodeSuccess)
            && !$statusCode
        ) {
            $statusCode = $statusCodeSuccess;
        }

        return $statusCode;
    }

    public function processSucceededPush(PushRequestInterface $pushRequest): bool
    {
        // TODO: Implement processSucceededPush() method.
    }

    public function processFailedPush(PushRequestInterface $pushRequest): bool
    {
        // TODO: Implement processFailedPush() method.
    }

    public function processPendingPaymentPush(PushRequestInterface $pushRequest): bool
    {
        // TODO: Implement processPendingPaymentPush() method.
    }
}