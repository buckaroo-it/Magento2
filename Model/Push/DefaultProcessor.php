<?php

namespace Buckaroo\Magento2\Model\Push;

use Buckaroo\Magento2\Api\PushProcessorInterface;
use Buckaroo\Magento2\Api\PushRequestInterface;
use Buckaroo\Magento2\Exception as BuckarooException;
use Buckaroo\Magento2\Helper\Data;
use Buckaroo\Magento2\Logging\Log;
use Buckaroo\Magento2\Model\BuckarooStatusCode;
use Buckaroo\Magento2\Model\ConfigProvider\Method\Giftcards;
use Buckaroo\Magento2\Model\ConfigProvider\Method\PayPerEmail;
use Buckaroo\Magento2\Model\ConfigProvider\Method\Transfer;
use Buckaroo\Magento2\Model\Method\BuckarooAdapter;
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
    public const BUCKAROO_RECEIVED_TRANSACTIONS = 'buckaroo_received_transactions';
    public const BUCKAROO_RECEIVED_TRANSACTIONS_STATUSES = 'buckaroo_received_transactions_statuses';

    /**
     * @var OrderRequestService
     */
    protected OrderRequestService $orderRequestService;

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
     * @var Payment|null
     */
    protected ?Payment $payment;

    /**
     * @var PushTransactionType
     */
    protected PushTransactionType $pushTransactionType;

    /**
     * @var bool
     */
    protected bool $forceInvoice = false;

    public function __construct(
        OrderRequestService $orderRequestService,
        PushTransactionType $pushTransactionType,
        Log $logging,
        TransactionInterface $transaction,
        Data $helper
    ) {
        $this->orderRequestService = $orderRequestService;
        $this->logging = $logging;
        $this->transaction = $transaction;
        $this->helper = $helper;
        $this->pushTransactionType = $pushTransactionType;
    }

    /**
     * @throws BuckarooException
     * @throws FileSystemException
     * @throws \Exception
     */
    public function processPush(PushRequestInterface $pushRequest): bool
    {
        $this->pushRequest = $pushRequest;
        $this->order = $this->orderRequestService->getOrderByRequest();
        $this->payment = $this->order->getPayment();

        // Skip Push
        if ($this->skipPush()) {
            return true;
        }

        // Check Push Dublicates
        if ($this->receivePushCheckDuplicates()) {
            throw new BuckarooException(__('Skipped handling this push, duplicate'));
        }

        // Check if the order can be updated
        if (!$this->canUpdateOrderStatus()) {
            $this->logging->addDebug('Order can not receive updates');
            $this->orderRequestService->setOrderNotificationNote(__('The order has already been processed.'));
            throw new BuckarooException(
                __('Signature from push is correct but the order can not receive updates')
            );
        }

        $this->setTransactionKey();

        $this->setOrderStatusMessage();
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
     * Sets the transaction key in the payment's additional information if it's not already set.
     *
     * @return void
     */
    protected function setTransactionKey()
    {
        $payment = $this->order->getPayment();
        $originalKey = BuckarooAdapter::BUCKAROO_ORIGINAL_TRANSACTION_KEY_KEY;
        $transactionKey = $this->getTransactionKey();

        if (!$payment->getAdditionalInformation($originalKey) && strlen($transactionKey) > 0) {
            $payment->setAdditionalInformation($originalKey, $transactionKey);
        }
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
        if ($this->skipKlarnaCapture()) {
            return true;
        }

        // Skip Push based on specific condition
        if (!$this->skipSpecificTypesOfRequsts()) {
            return true;
        }

        if ($this->skipFirstPush()) {
            throw new BuckarooException(
                __('Skipped handling this push, first handle response, action will be taken on the next push.')
            );
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
     * @return bool
     */
    protected function skipKlarnaCapture(): bool
    {
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
     * Buckaroo Push is send before Response, for correct flow we skip the first push
     * for some payment methods
     *
     * @return bool
     * @throws LocalizedException
     */
    protected function skipFirstPush(): bool
    {
        $skipFirstPush = $this->payment->getAdditionalInformation('skip_push');
        $this->logging->addDebug(__METHOD__ . '|1_20|' . var_export($skipFirstPush, true));


        if ($skipFirstPush > 0) {
            $this->payment->setAdditionalInformation('skip_push', (int)$skipFirstPush - 1);
            $this->payment->save();
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
    protected function receivePushCheckDuplicates(int $receivedStatusCode = null, string $trxId = null): bool
    {
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

        $ignoredPaymentMethods = [
            Giftcards::CODE,
            Transfer::CODE
        ];
        if ($this->payment
            && $this->payment->getMethod()
            && $receivedStatusCode
            && ($this->pushTransactionType->getPushType() == PushTransactionType::BUCK_PUSH_TYPE_TRANSACTION)
            && (!in_array($this->payment->getMethod(), $ignoredPaymentMethods))
        ) {
            $this->logging->addDebug(__METHOD__ . '|5|');

            $receivedTrxStatuses = $this->payment->getAdditionalInformation(
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
                if (($this->order->getState() == Order::STATE_NEW)
                    && ($this->order->getStatus() == $orderStatus)
                    && ($receivedStatusCode == BuckarooStatusCode::SUCCESS)
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
                $this->payment->save();
            }
        }
        $this->logging->addDebug(__METHOD__ . '|20|');

        return false;
    }

    /**
     * It updates the BUCKAROO_RECEIVED_TRANSACTIONS_STATUSES payment additional information
     * with the current received tx status.
     *
     * @return void
     * @throws LocalizedException
     */
    protected function setReceivedTransactionStatuses(): void
    {
        $txId = $this->pushRequest->getTransactions();
        $statusCode = $this->pushRequest->getStatusCode();

        if (empty($txId) || empty($statusCode)) {
            return;
        }

        $receivedTxStatuses = $this->payment->getAdditionalInformation(
            self::BUCKAROO_RECEIVED_TRANSACTIONS_STATUSES) ?? [];
        $receivedTxStatuses[$txId] = $statusCode;

        $this->payment->setAdditionalInformation(self::BUCKAROO_RECEIVED_TRANSACTIONS_STATUSES, $receivedTxStatuses);
    }

    /**
     * Checks if the order can be updated by checking its state and status.
     *
     * @return bool
     *
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     */
    protected function canUpdateOrderStatus(): bool
    {
        /**
         * Types of statusses
         */
        $completedStateAndStatus = [Order::STATE_COMPLETE, Order::STATE_COMPLETE];
        $cancelledStateAndStatus = [Order::STATE_CANCELED, Order::STATE_CANCELED];
        $holdedStateAndStatus = [Order::STATE_HOLDED, Order::STATE_HOLDED];
        $closedStateAndStatus = [Order::STATE_CLOSED, Order::STATE_CLOSED];
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
            && ($this->pushTransactionType->getStatusKey() === 'BUCKAROO_MAGENTO2_STATUSCODE_SUCCESS')
            && $this->pushRequest->getRelatedtransactionPartialpayment() == null
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
     * @return void
     */
    protected function setOrderStatusMessage(): void
    {
        if (!empty($this->pushRequest->getStatusmessage())) {
            if ($this->order->getState() === Order::STATE_NEW
                && empty($this->pushRequest->getRelatedtransactionPartialpayment())
                && $this->pushRequest->hasPostData('statuscode', BuckarooStatusCode::SUCCESS)
            ) {
                $this->order->setState(Order::STATE_PROCESSING);
                $this->order->addStatusHistoryComment(
                    $this->pushRequest->getStatusmessage(),
                    $this->helper->getOrderStatusByState($this->order, Order::STATE_PROCESSING)
                );
            } else {
                $this->order->addStatusHistoryComment($this->pushRequest->getStatusmessage());
            }
        }
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