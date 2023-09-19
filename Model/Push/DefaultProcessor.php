<?php
/**
 * NOTICE OF LICENSE
 *
 * This source file is subject to the MIT License
 * It is available through the world-wide-web at this URL:
 * https://tldrlegal.com/license/mit-license
 * If you are unable to obtain it through the world-wide-web, please email
 * to support@buckaroo.nl, so we can send you a copy immediately.
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
declare(strict_types=1);

namespace Buckaroo\Magento2\Model\Push;

use Buckaroo\Magento2\Api\PushProcessorInterface;
use Buckaroo\Magento2\Api\PushRequestInterface;
use Buckaroo\Magento2\Exception as BuckarooException;
use Buckaroo\Magento2\Helper\Data;
use Buckaroo\Magento2\Helper\PaymentGroupTransaction;
use Buckaroo\Magento2\Logging\BuckarooLoggerInterface;
use Buckaroo\Magento2\Model\BuckarooStatusCode;
use Buckaroo\Magento2\Model\ConfigProvider\Account;
use Buckaroo\Magento2\Model\ConfigProvider\Method\Afterpay;
use Buckaroo\Magento2\Model\ConfigProvider\Method\Afterpay2;
use Buckaroo\Magento2\Model\ConfigProvider\Method\Afterpay20;
use Buckaroo\Magento2\Model\ConfigProvider\Method\Creditcard;
use Buckaroo\Magento2\Model\ConfigProvider\Method\Giftcards;
use Buckaroo\Magento2\Model\ConfigProvider\Method\Klarnakp;
use Buckaroo\Magento2\Model\ConfigProvider\Method\Transfer;
use Buckaroo\Magento2\Model\ConfigProvider\Method\Voucher;
use Buckaroo\Magento2\Model\Method\BuckarooAdapter;
use Buckaroo\Magento2\Model\OrderStatusFactory;
use Buckaroo\Magento2\Service\Push\OrderRequestService;
use Magento\Framework\Exception\FileSystemException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Sales\Api\Data\TransactionInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Invoice;
use Magento\Sales\Model\Order\Payment;
use Magento\Sales\Model\Order\Payment as OrderPayment;
use Magento\Sales\Model\Order\Payment\Transaction;

/**
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 * @SuppressWarnings(PHPMD.ExcessiveClassComplexity)
 */
class DefaultProcessor implements PushProcessorInterface
{
    public const BUCKAROO_RECEIVED_TRANSACTIONS          = 'buckaroo_received_transactions';
    public const BUCKAROO_RECEIVED_TRANSACTIONS_STATUSES = 'buckaroo_received_transactions_statuses';
    /**
     * @var Account
     */
    public Account $configAccount;
    /**
     * @var PushRequestInterface
     */
    protected PushRequestInterface $pushRequest;
    /**
     * @var PushTransactionType
     */
    protected PushTransactionType $pushTransactionType;
    /**
     * @var OrderRequestService
     */
    protected OrderRequestService $orderRequestService;
    /**
     * @var Order|OrderPayment $order
     */
    protected $order;
    /**
     * @var OrderPayment|null
     */
    protected ?OrderPayment $payment;
    /**
     * @var BuckarooLoggerInterface $logger
     */
    protected BuckarooLoggerInterface $logger;
    /**
     * @var Data
     */
    protected Data $helper;
    /**
     * @var Transaction
     */
    protected Transaction $transaction;
    /**
     * @var PaymentGroupTransaction
     */
    protected PaymentGroupTransaction $groupTransaction;
    /**
     * @var bool
     */
    protected bool $forceInvoice = false;
    /**
     * @var bool
     */
    protected bool $dontSaveOrderUponSuccessPush = false;
    /**
     * @var BuckarooStatusCode
     */
    protected BuckarooStatusCode $buckarooStatusCode;
    /**
     * @var OrderStatusFactory
     */
    protected OrderStatusFactory $orderStatusFactory;

    /**
     * @param OrderRequestService $orderRequestService
     * @param PushTransactionType $pushTransactionType
     * @param BuckarooLoggerInterface $logger
     * @param Data $helper
     * @param TransactionInterface $transaction
     * @param PaymentGroupTransaction $groupTransaction
     * @param BuckarooStatusCode $buckarooStatusCode
     * @param OrderStatusFactory $orderStatusFactory
     * @param Account $configAccount
     */
    public function __construct(
        OrderRequestService $orderRequestService,
        PushTransactionType $pushTransactionType,
        BuckarooLoggerInterface $logger,
        Data $helper,
        TransactionInterface $transaction,
        PaymentGroupTransaction $groupTransaction,
        BuckarooStatusCode $buckarooStatusCode,
        OrderStatusFactory $orderStatusFactory,
        Account $configAccount
    ) {
        $this->pushTransactionType = $pushTransactionType;
        $this->orderRequestService = $orderRequestService;
        $this->logger = $logger;
        $this->helper = $helper;
        $this->transaction = $transaction;
        $this->groupTransaction = $groupTransaction;
        $this->buckarooStatusCode = $buckarooStatusCode;
        $this->orderStatusFactory = $orderStatusFactory;
        $this->configAccount = $configAccount;
    }

    /**
     * @throws BuckarooException
     * @throws FileSystemException
     * @throws \Exception
     */
    public function processPush(PushRequestInterface $pushRequest): bool
    {
        $this->initializeFields($pushRequest);

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
            $this->logger->addDebug(
                '[PUSH] | [Webapi] | ['. __METHOD__ .':'. __LINE__ . '] - Order can not receive updates'
            );

            $this->orderRequestService->setOrderNotificationNote(__('The order has already been processed.'));
            throw new BuckarooException(
                __('Signature from push is correct but the order can not receive updates')
            );
        }

        $this->setTransactionKey();

        $this->setOrderStatusMessage();

        if ((!in_array($this->payment->getMethod(), [Giftcards::CODE, Voucher::CODE]))
            && $this->isGroupTransactionPart()) {
            $this->savePartGroupTransaction();
            return true;
        }

        if (!$this->canProcessPostData()) {
            return true;
        }

        if ($this->giftcardPartialPayment()) {
            return true;
        }

        $this->processPushByStatus();

        if (!$this->dontSaveOrderUponSuccessPush) {
            $this->order->save();
        }

        return true;
    }

    /**
     * @param PushRequestInterface $pushRequest
     * @return void
     * @throws \Exception
     */
    protected function initializeFields(PushRequestInterface $pushRequest): void
    {
        $this->pushRequest = $pushRequest;
        $this->order = $this->orderRequestService->getOrderByRequest();
        $this->payment = $this->order->getPayment();
    }

    /**
     * Skip the push if the conditions are met.
     *
     * @return bool
     * @throws \Exception
     */
    protected function skipPush(): bool
    {
        // Skip Push based on specific condition
        if ($this->skipSpecificTypesOfRequsts()) {
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
     * Check if it is needed to handle the push message based on postdata
     *
     * @return bool
     * @throws \Exception
     *
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     */
    protected function skipSpecificTypesOfRequsts(): bool
    {
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
     * Buckaroo Push is send before Response, for correct flow we skip the first push
     * for some payment methods
     *
     * @return bool
     * @throws LocalizedException
     */
    protected function skipFirstPush(): bool
    {
        $skipFirstPush = $this->payment->getAdditionalInformation('skip_push');
        $this->logger->addDebug(
            '[PUSH] | [Webapi] | [' . __METHOD__ . ':' . __LINE__ . '] - Skip First Push: ' . $skipFirstPush,
        );

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
            $receivedTrxStatuses = $this->payment->getAdditionalInformation(
                self::BUCKAROO_RECEIVED_TRANSACTIONS_STATUSES
            );

            $this->logger->addDebug(sprintf(
                '[PUSH] | [Webapi] | [%s:%s] - Check for duplicate transaction pushes | order: %s',
                __METHOD__,
                __LINE__,
                var_export([
                    'receivedTrxStatuses' => $receivedTrxStatuses,
                    'receivedStatusCode'  => $receivedStatusCode
                ], true)
            ));

            if ($receivedTrxStatuses
                && is_array($receivedTrxStatuses)
                && isset($receivedTrxStatuses[$trxId])
                && ($receivedTrxStatuses[$trxId] == $receivedStatusCode)
            ) {
                $orderStatus = $this->helper->getOrderStatusByState($this->order, Order::STATE_NEW);
                if (($this->order->getState() == Order::STATE_NEW)
                    && ($this->order->getStatus() == $orderStatus)
                    && ($receivedStatusCode == BuckarooStatusCode::SUCCESS)
                ) {
                    $this->logger->addDebug(
                        '[PUSH] | [Webapi] | [' . __METHOD__ . ':' . __LINE__ . '] - allow duplicated pushes '
                        . 'for 190 statuses in case if order stills to be new/pending',
                    );
                    return false;
                }

                $this->logger->addDebug(
                    '[PUSH] | [Webapi] | [' . __METHOD__ . ':' . __LINE__ . '] - Skip Push the request is duplicate '
                );
                return true;
            }
            if ($save) {
                $this->setReceivedTransactionStatuses();
                $this->payment->save();
            }
        }

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
            self::BUCKAROO_RECEIVED_TRANSACTIONS_STATUSES
        ) ?? [];
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
        $this->logger->addDebug(sprintf(
            '[PUSH] | [Webapi] | [%s:%s] - Checks if the order can be updated | currentStateAndStatus: %s',
            __METHOD__,
            __LINE__,
            var_export($currentStateAndStatus, true)
        ));

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
            $this->logger->addDebug(sprintf(
                '[PUSH] | [Webapi] | [%s:%s] - Resetting from CANCELED to STATE_NEW/PENDING',
                __METHOD__,
                __LINE__
            ));

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

    /**
     * Checks if the push request is a group transaction with a non-success status code.
     *
     * @return false|mixed
     */
    protected function isGroupTransactionPart()
    {
        if (!is_null($this->pushRequest->getTransactions())) {
            return $this->groupTransaction->getGroupTransactionByTrxId($this->pushRequest->getTransactions());
        }
        return false;
    }

    /**
     * Save the part group transaction.
     *
     * @return void
     * @throws \Exception
     */
    protected function savePartGroupTransaction()
    {
        $items = $this->groupTransaction->getGroupTransactionByTrxId($this->pushRequest->getTransactions());
        if (is_array($items) && count($items) > 0) {
            foreach ($items as $item) {
                $item2['status'] = $this->pushRequest->getStatusCode();
                $item2['entity_id'] = $item['entity_id'];
                $this->groupTransaction->updateGroupTransaction($item2);
            }
        }
    }

    /**
     *
     * @return true
     */
    protected function canProcessPostData()
    {
        return true;
    }

    /**
     * Checks if the payment is a partial payment using a gift card.
     *
     * @return bool
     * @throws LocalizedException
     */
    protected function giftcardPartialPayment(): bool
    {
        if ($this->payment->getMethod() != Giftcards::CODE
            || (!empty($this->pushRequest->getAmount())
                && $this->pushRequest->getAmount() >= $this->order->getGrandTotal())
            || empty($this->pushRequest->getRelatedtransactionPartialpayment())
        ) {
            return false;
        }

        if ($this->groupTransaction->isGroupTransaction($this->pushRequest->getInvoiceNumber())) {
            return false;
        }

        if (!$this->pushTransactionType->getTransactionType() == PushTransactionType::BUCK_PUSH_GROUPTRANSACTION_TYPE) {
            $this->payment->setAdditionalInformation(
                BuckarooAdapter::BUCKAROO_ORIGINAL_TRANSACTION_KEY_KEY,
                $this->pushRequest->getRelatedtransactionPartialpayment()
            );

            $this->addGiftcardPartialPaymentToPaymentInformation();
        }

        return true;
    }

    /**
     * Adds the gift card partial payment information to the payment's additional information.
     *
     * @return void
     */
    protected function addGiftcardPartialPaymentToPaymentInformation()
    {
        $payment = $this->order->getPayment();

        $transactionAmount = $this->pushRequest->getAmount();
        $transactionKey = $this->pushRequest->getTransactions();
        $transactionMethod = $this->pushRequest->getTransactionMethod();

        $transactionData = $payment->getAdditionalInformation(BuckarooAdapter::BUCKAROO_ALL_TRANSACTIONS);

        $transactionArray = [];
        if (is_array($transactionData) && count($transactionData) > 0) {
            $transactionArray = $transactionData;
        }

        if (!empty($transactionKey) && $transactionAmount > 0) {
            $transactionArray[$transactionKey] = [$transactionMethod, $transactionAmount];

            $payment->setAdditionalInformation(
                BuckarooAdapter::BUCKAROO_ALL_TRANSACTIONS,
                $transactionArray
            );
        }
    }

    /**
     * Process the push according the response status
     *
     * @return bool
     * @throws LocalizedException
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     */
    protected function processPushByStatus(): bool
    {
        $newStatus = $this->getNewStatus();
        $statusKey = $this->pushTransactionType->getStatusKey();
        $statusMessage = $this->pushTransactionType->getStatusMessage();

        if ($statusKey == 'BUCKAROO_MAGENTO2_STATUSCODE_SUCCESS') {
            return $this->processSucceededPush($newStatus, $statusMessage);
        }

        if (in_array($statusKey, $this->buckarooStatusCode->getFailedStatuses())) {
            return $this->processFailedPush($newStatus, $statusMessage);
        }

        if (in_array($statusKey, $this->buckarooStatusCode->getPendingStatuses())) {
            return $this->processPendingPaymentPush($newStatus, $statusMessage);
        }

        $this->orderRequestService->setOrderNotificationNote($statusMessage);
        return true;
    }

    /**
     * @return false|string|null
     * @throws LocalizedException
     */
    protected function getNewStatus()
    {
        return $this->orderStatusFactory->get($this->pushRequest->getStatusCode(), $this->order);
    }

    /**
     * Process the successful push response from Buckaroo and update the order accordingly.
     *
     * @param string $newStatus
     * @param string $message
     * @return bool
     * @throws LocalizedException
     *
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     * @SuppressWarnings(PHPMD.NPathComplexity)
     * @SuppressWarnings(PHPMD.ExcessiveMethodLength)
     */
    public function processSucceededPush(string $newStatus, string $message): bool
    {
        $this->logger->addDebug(sprintf(
            '[PUSH] | [Webapi] | [%s:%s] - Process the successful push response from Buckaroo | newStatus: %s',
            __METHOD__,
            __LINE__,
            var_export($newStatus, true)
        ));


        $this->setBuckarooReservationNumber();

        $this->sendOrderEmail();

        $paymentDetails = $this->getPaymentDetails($message);
        $paymentDetails['state'] = Order::STATE_PROCESSING;
        $paymentDetails['newStatus'] = $newStatus;

        $this->dontSaveOrderUponSuccessPush = false;

        if ($this->canPushInvoice()) {
            $saveInvoice = $this->invoiceShouldBeSaved($paymentDetails);
            if ($saveInvoice && !$this->saveInvoice()) {
                return false;
            }
        }

        if ($this->groupTransaction->isGroupTransaction($this->pushRequest->getInvoiceNumber())) {
            $paymentDetails['forceState'] = true;
        }

        $this->processSucceededPushAuthorization();

        $this->orderRequestService->updateOrderStatus(
            $paymentDetails['state'],
            $paymentDetails['newStatus'],
            $paymentDetails['description'],
            $paymentDetails['forceState'],
            $this->dontSaveOrderUponSuccessPush
        );

        return true;
    }

    /**
     * Process succeeded push authorization.
     *
     * @return void
     * @throws \Exception
     */
    private function processSucceededPushAuthorization(): void
    {
        $authPpaymentMethods = [
            Afterpay::CODE,
            Afterpay2::CODE,
            Afterpay20::CODE,
            Creditcard::CODE,
            Klarnakp::CODE
        ];

        if (in_array($this->payment->getMethod(), $authPpaymentMethods)) {
            if ((($this->payment->getMethod() == Klarnakp::CODE)
                    || (
                        !empty($this->pushRequest->getTransactionType())
                        && in_array($this->pushRequest->getTransactionType(), ['I038', 'I880'])
                    )
                ) && !empty($this->pushRequest->getStatusCode())
                && ($this->pushRequest->getStatusCode() == 190)
            ) {
                $this->logger->addDebug(sprintf(
                    '[PUSH] | [Webapi] | [%s:%s] - Process succeeded push authorization | paymentMethod: %s',
                    __METHOD__,
                    __LINE__,
                    var_export($this->payment->getMethod(), true)
                ));
                $this->order->setState(Order::STATE_PROCESSING);
                $this->order->save();
            }
        }
    }

    protected function setBuckarooReservationNumber(): bool
    {
        return false;
    }


    /**
     * @todo GIFTCARD PARTIAL PAYMENT TO BE MOVED in a separate class
     */

    /**
     * Send Order email if was not sent
     *
     * @return void
     * @throws LocalizedException
     */
    protected function sendOrderEmail(): void
    {
        $store = $this->order->getStore();
        $paymentMethod = $this->payment->getMethodInstance();

        if (!$this->order->getEmailSent()
            && ($this->configAccount->getOrderConfirmationEmail($store)
                || $paymentMethod->getConfigData('order_email', $store)
            )
        ) {
            $this->logger->addDebug(sprintf(
                '[PUSH] | [Webapi] | [%s:%s] - Send Order Email | orderConfirmationEmail: %s',
                __METHOD__,
                __LINE__,
                var_export($this->configAccount->getOrderConfirmationEmail($store), true)
            ));

            $this->orderRequestService->sendOrderEmail(
                $this->order,
                (bool)$this->configAccount->getOrderConfirmationEmailSync($store)
            );
        }
    }

    /**
     * Can create invoice on push
     *
     * @return bool
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     * @throws LocalizedException
     */
    protected function canPushInvoice(): bool
    {
        if ($this->payment->getMethodInstance()->getConfigData('payment_action') == 'authorize') {
            return false;
        }

        return true;
    }

    /**
     * Creates and saves the invoice and adds for each invoice the buckaroo transaction keys
     * Only when the order can be invoiced and has not been invoiced before.
     *
     * @return bool
     * @throws BuckarooException
     * @throws LocalizedException
     * @throws \Exception
     *
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     * @SuppressWarnings(PHPMD.NPathComplexity)
     */
    protected function saveInvoice(): bool
    {
        $this->logger->addDebug('[PUSH] | [Webapi] | ['. __METHOD__ .':'. __LINE__ . '] - Save Invoice');

        if (!$this->forceInvoice
            && (!$this->order->canInvoice() || $this->order->hasInvoices())) {
            $this->logger->addDebug(
                '[PUSH] | [Webapi] | ['. __METHOD__ .':'. __LINE__ . '] - Order can not be invoiced'
            );

            return false;
        }

        $this->addTransactionData();

        //Fix for suspected fraud when the order currency does not match with the payment's currency
        $amount = ($this->payment->isSameCurrency()
            && $this->payment->isCaptureFinal($this->order->getGrandTotal())) ?
            $this->order->getGrandTotal() : $this->order->getBaseTotalDue();
        $this->payment->registerCaptureNotification($amount);
        $this->payment->save();

        $transactionKey = $this->getTransactionKey();

        if (strlen($transactionKey) <= 0) {
            return true;
        }

        /** @var Invoice $invoice */
        foreach ($this->order->getInvoiceCollection() as $invoice) {
            $invoice->setTransactionId($transactionKey)->save();

            if (!empty($this->pushRequest->getInvoiceNumber())
                && $this->groupTransaction->isGroupTransaction($this->pushRequest->getInvoiceNumber())) {
                $this->logger->addDebug(
                    '[PUSH] | [Webapi] | ['. __METHOD__ .':'. __LINE__ . '] - Set invoice state PAID group transaction'
                );
                $invoice->setState(Invoice::STATE_PAID);
            }

            if (!$invoice->getEmailSent() && $this->configAccount->getInvoiceEmail($this->order->getStore())) {
                $this->logger->addDebug(
                    '[PUSH] | [Webapi] | ['. __METHOD__ .':'. __LINE__ . '] - Send Invoice Email '
                );
                $this->orderRequestService->sendInvoiceEmail($invoice, true);
            }
        }

        $this->order->setIsInProcess(true);
        $this->order->save();

        $this->dontSaveOrderUponSuccessPush = true;

        return true;
    }

    /**
     * Adds transaction data to the order payment with the given transaction key and data.
     *
     * @param bool $transactionKey
     * @param bool $data
     * @return Payment
     * @throws LocalizedException
     * @throws \Exception
     */
    public function addTransactionData(bool $transactionKey = false, bool $data = false): Payment
    {
        $this->payment = $this->order->getPayment();
        $transactionKey = $transactionKey ?: $this->getTransactionKey();

        if (strlen($transactionKey) <= 0) {
            throw new BuckarooException(__('There was no transaction ID found'));
        }

        /**
         * Save the transaction's response as additional info for the transaction.
         */
        $postData = $data ?: $this->pushRequest->getData();
        $rawInfo = $this->helper->getTransactionAdditionalInfo($postData);

        $this->payment->setTransactionAdditionalInfo(
            Transaction::RAW_DETAILS,
            $rawInfo
        );

        /**
         * Save the payment's transaction key.
         */
        $this->payment->setTransactionId($transactionKey . '-capture');

        $this->payment->setParentTransactionId($transactionKey);
        $this->payment->setAdditionalInformation(
            BuckarooAdapter::BUCKAROO_ORIGINAL_TRANSACTION_KEY_KEY,
            $transactionKey
        );

        return $this->payment;
    }

    /**
     * Process the failed push response from Buckaroo and update the order accordingly.
     *
     * @param string $newStatus
     * @param string $message
     * @return bool
     * @throws LocalizedException
     *
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     */
    public function processFailedPush(string $newStatus, string $message): bool
    {
        $this->logger->addDebug(sprintf(
            '[PUSH] | [Webapi] | [%s:%s] - Process the failed push response from Buckaroo | newStatus: %s',
            __METHOD__,
            __LINE__,
            var_export($newStatus, true)
        ));

        if (($this->order->getState() === Order::STATE_PROCESSING)
            && ($this->order->getStatus() === Order::STATE_PROCESSING)
        ) {
            $this->logger->addDebug(
                '[PUSH] | [Webapi] | ['. __METHOD__ .':'. __LINE__ . '] - Do not update to failed if we had a success'
            );
            return false;
        }

        $description = 'Payment status : ' . $message;

        if (!empty($this->pushRequest->getServiceAntifraudAction())) {
            $description .= $this->pushRequest->getServiceAntifraudAction() .
                ' ' . $this->pushRequest->getServiceAntifraudCheck() .
                ' ' . $this->pushRequest->getServiceAntifraudDetails();
        }

        $store = $this->order->getStore();

        $buckarooCancelOnFailed = $this->configAccount->getCancelOnFailed($store);

        $payment = $this->order->getPayment();

        if ($buckarooCancelOnFailed && $this->order->canCancel()) {
            $this->logger->addDebug(sprintf(
                '[PUSH] | [Webapi] | [%s:%s] - Process the failed push response from Buckaroo. Cancel Order: %s',
                __METHOD__,
                __LINE__,
                $message
            ));

            // BUCKM2-78: Never automatically cancelauthorize via push for afterpay
            // setting parameter which will cause to stop the cancel process on
            // Buckaroo/Model/Method/BuckarooAdapter.php:880
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

            $this->orderRequestService->updateOrderStatus(Order::STATE_CANCELED, $newStatus, $description);

            try {
                $this->order->cancel()->save();
            } catch (\Throwable $th) {
                $this->logger->addError(sprintf(
                    '[PUSH] | [Webapi] | [%s:%s] - Process failed push from Buckaroo. Cancel Order| [ERROR]: %s',
                    __METHOD__,
                    __LINE__,
                    $th->getMessage()
                ));
            }
            return true;
        }

        $force = false;
        if (($payment->getMethodInstance()->getCode() == 'buckaroo_magento2_mrcash')
            && ($this->order->getState() === Order::STATE_NEW)
            && ($this->order->getStatus() === 'pending')
        ) {
            $force = true;
        }
        $this->orderRequestService->updateOrderStatus(Order::STATE_CANCELED, $newStatus, $description, $force);

        return true;
    }

    /**
     * Transfer payment methods receive status pending for success order
     *
     * @param string|false|null $newStatus
     * @param string $statusMessage
     * @return bool
     */
    protected function processPendingPaymentPush($newStatus, string $statusMessage): bool
    {
        if(!$this->canProcessPendingPush()) {
            return true;
        }

        $this->logger->addDebug(
            '[PUSH] | [Webapi] | [' . __METHOD__ . ':' . __LINE__ . '] - Process Pending Push'
        );

        $store = $this->order->getStore();
        $paymentMethod = $this->payment->getMethodInstance();

        if (!$this->order->getEmailSent()
            && (
                $this->configAccount->getOrderConfirmationEmail($store)
                || $paymentMethod->getConfigData('order_email', $store)
            )
        ) {
            $this->logger->addDebug(
                '[PUSH] | [Webapi] | [' . __METHOD__ . ':' . __LINE__ . '] - Process Pending Push - SEND EMAIL'
            );
            $this->orderRequestService->sendOrderEmail($this->order);
        }

        $description = 'Payment Push Status: ' . $statusMessage . PHP_EOL;
        $transferDetails = $this->getTransferDetails();
        if (!empty($transferDetails)) {
            $this->payment->setAdditionalInformation('transfer_details', $transferDetails);
        }

        $this->orderRequestService->updateOrderStatus(Order::STATE_PENDING_PAYMENT, $newStatus, $description);

        return true;
    }

    /**
     * Get the order increment ID based on the invoice number or order number from push
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

    protected function getPaymentDetails($message)
    {
        // Set amount
        $amount = $this->order->getTotalDue();
        if (!empty($this->pushRequest->getAmount())) {
            $amount = floatval($this->pushRequest->getAmount());
        }

        /**
         * force state eventhough this can lead to a transition of the order
         * like new -> processing
         */
        $forceState = false;
        $this->dontSaveOrderUponSuccessPush = false;

        if ($this->canPushInvoice()) {
            $description = 'Payment status : <strong>' . $message . "</strong><br/>";
            $amount = $this->order->getBaseTotalDue();
            $description .= 'Total amount of ' .
                $this->order->getBaseCurrency()->formatTxt($amount) . ' has been paid';
        } else {
            $description = 'Authorization status : <strong>' . $message . "</strong><br/>";
            $description .= 'Total amount of ' . $this->order->getBaseCurrency()->formatTxt($amount)
                . ' has been authorized. Please create an invoice to capture the authorized amount.';
            $forceState = true;
        }

        return [
            'amount' => $amount,
            'description' => $description,
            'forceState' => $forceState
        ];
    }

    /**
     * @param array $paymentDetails
     * @return bool
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    protected function invoiceShouldBeSaved(array &$paymentDetails): bool
    {
        return true;
    }

    /**
     * @return array
     */
    protected function getTransferDetails(): array
    {
        return [];
    }

    /**
     * @return bool
     */
    protected function canProcessPendingPush(): bool
    {
        return false;
    }
}
