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

use Buckaroo\Magento2\Api\Data\PushRequestInterface;
use Buckaroo\Magento2\Exception as BuckarooException;
use Buckaroo\Magento2\Helper\Data;
use Buckaroo\Magento2\Helper\PaymentGroupTransaction;
use Buckaroo\Magento2\Logging\BuckarooLoggerInterface;
use Buckaroo\Magento2\Model\BuckarooStatusCode;
use Buckaroo\Magento2\Model\Config\Source\InvoiceHandlingOptions;
use Buckaroo\Magento2\Model\ConfigProvider\Account;
use Buckaroo\Magento2\Model\ConfigProvider\Method\Afterpay;
use Buckaroo\Magento2\Model\ConfigProvider\Method\Afterpay2;
use Buckaroo\Magento2\Model\ConfigProvider\Method\Afterpay20;
use Buckaroo\Magento2\Model\ConfigProvider\Method\Creditcard;
use Buckaroo\Magento2\Model\ConfigProvider\Method\Giftcards;
use Buckaroo\Magento2\Model\ResourceModel\Giftcard\Collection as GiftcardCollection;
use Buckaroo\Magento2\Model\ConfigProvider\Method\Klarnakp;
use Buckaroo\Magento2\Model\ConfigProvider\Method\Transfer;
use Buckaroo\Magento2\Model\GroupTransaction;
use Buckaroo\Magento2\Model\Method\BuckarooAdapter;
use Buckaroo\Magento2\Model\OrderStatusFactory;
use Buckaroo\Magento2\Model\Service\GiftCardRefundService;
use Buckaroo\Magento2\Service\Order\Uncancel;
use Buckaroo\Magento2\Service\Push\OrderRequestService;
use Exception;
use Magento\Framework\App\ObjectManager;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Exception\FileSystemException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Phrase;
use Magento\Sales\Api\Data\TransactionInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Invoice;
use Magento\Sales\Model\Order\Payment;
use Magento\Sales\Model\Order\Payment as OrderPayment;
use Magento\Sales\Model\Order\Payment\Transaction;
use Buckaroo\Magento2\Model\Transaction\Status\Response;

/**
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 * @SuppressWarnings(PHPMD.ExcessiveClassComplexity)
 */
class DefaultProcessor implements PushProcessorInterface
{
    public const BUCKAROO_RECEIVED_TRANSACTIONS = 'buckaroo_received_transactions';
    public const BUCKAROO_RECEIVED_TRANSACTIONS_STATUSES = 'buckaroo_received_transactions_statuses';

    /**
     * @var Account
     */
    public $configAccount;

    /**
     * @var PushRequestInterface
     */
    protected $pushRequest;

    /**
     * @var PushTransactionType
     */
    protected $pushTransactionType;

    /**
     * @var OrderRequestService
     */
    protected $orderRequestService;

    /**
     * @var Order|OrderPayment $order
     */
    protected $order;

    /**
     * @var OrderPayment|null
     */
    protected $payment;

    /**
     * @var BuckarooLoggerInterface $logger
     */
    protected $logger;

    /**
     * @var Data
     */
    protected $helper;

    /**
     * @var Transaction
     */
    protected $transaction;

    /**
     * @var PaymentGroupTransaction
     */
    protected $groupTransaction;

    /**
     * @var bool
     */
    protected $forceInvoice = false;

    /**
     * @var bool
     */
    protected $dontSaveOrderUponSuccessPush = false;

    /**
     * @var BuckarooStatusCode
     */
    protected $buckarooStatusCode;

    /**
     * @var OrderStatusFactory
     */
    protected $orderStatusFactory;

    /**
     * @var GiftCardRefundService
     */
    private $giftCardRefundService;

    /**
     * @var Uncancel
     */
    private $uncancelService;

    /**
     * @var ResourceConnection
     */
    private $resourceConnection;

    /**
     * @var GiftcardCollection
     */
    private $giftcardCollection;

    /**
     * @param OrderRequestService     $orderRequestService
     * @param PushTransactionType     $pushTransactionType
     * @param BuckarooLoggerInterface $logger
     * @param Data                    $helper
     * @param TransactionInterface    $transaction
     * @param PaymentGroupTransaction $groupTransaction
     * @param BuckarooStatusCode      $buckarooStatusCode
     * @param OrderStatusFactory      $orderStatusFactory
     * @param Account                 $configAccount
     * @param GiftCardRefundService   $giftCardRefundService
     * @param Uncancel                $uncancelService
     * @param ResourceConnection $resourceConnection
     * @param GiftcardCollection $giftcardCollection
     *
     * @SuppressWarnings(PHPMD.ExcessiveParameterList)
     */
    public function __construct(
        OrderRequestService       $orderRequestService,
        PushTransactionType       $pushTransactionType,
        BuckarooLoggerInterface   $logger,
        Data                      $helper,
        TransactionInterface      $transaction,
        PaymentGroupTransaction   $groupTransaction,
        BuckarooStatusCode        $buckarooStatusCode,
        OrderStatusFactory        $orderStatusFactory,
        Account                   $configAccount,
        GiftCardRefundService     $giftCardRefundService,
        Uncancel                  $uncancelService,
        ResourceConnection        $resourceConnection,
        GiftcardCollection        $giftcardCollection
    ) {
        $this->pushTransactionType = $pushTransactionType;
        $this->orderRequestService = $orderRequestService;
        $this->logger = $logger;
        $this->logger->setAction('[PUSH] | [Webapi] | ');
        $this->helper = $helper;
        $this->transaction = $transaction;
        $this->groupTransaction = $groupTransaction;
        $this->buckarooStatusCode = $buckarooStatusCode;
        $this->orderStatusFactory = $orderStatusFactory;
        $this->configAccount = $configAccount;
        $this->giftCardRefundService = $giftCardRefundService;
        $this->uncancelService = $uncancelService;
        $this->resourceConnection = $resourceConnection;
        $this->giftcardCollection = $giftcardCollection;
    }

    /**
     * @param PushRequestInterface $pushRequest
     *
     * @return bool
     * @throws BuckarooException
     * @throws LocalizedException
     */
    public function processPush(PushRequestInterface $pushRequest): bool
    {
        $this->initializeFields($pushRequest);

        // Skip Push
        if ($this->skipPush()) {
            return true;
        }

        // Check Push Duplicates
        if ($this->receivePushCheckDuplicates()) {
            throw new BuckarooException(__('Skipped handling this push, duplicate'));
        }

        // Check if the order can be updated
        if (!$this->canUpdateOrderStatus()) {
            $this->logger->addDebug('[' . __METHOD__ . ':' . __LINE__ . '] - Order can not receive updates');

            $this->orderRequestService->setOrderNotificationNote(__('The order has already been processed.'));
            throw new BuckarooException(
                __('Signature from push is correct but the order can not receive updates')
            );
        }

        $this->setTransactionKey();

        $this->setOrderStatusMessage();

        if ($this->isGroupTransactionPart() || $this->pushRequest->getRelatedtransactionPartialpayment()) {
            $this->savePartGroupTransaction();
            $this->saveNewGroupTransactionIfNeeded();
            $this->addGiftcardPartialPaymentToPaymentInformation();
            $this->order->save();
            return true;
        }

        // Store single giftcard payment metadata for refund support
        $this->storeSingleGiftcardPaymentInfo();

        if ($this->giftcardPartialPayment()) {
            return true;
        }

        if (!$this->canProcessPostData()) {
            return true;
        }

        $this->processPushByStatus();

        if (!$this->dontSaveOrderUponSuccessPush) {
            $this->order->save();
        }

        return true;
    }

    /**
     * @param \Buckaroo\Magento2\Api\Data\PushRequestInterface $pushRequest
     *
     * @throws Exception
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
     * @throws Exception
     *
     * @return bool
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
     * @throws Exception
     *
     * @return bool
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
     * @throws LocalizedException
     *
     * @return bool
     */
    protected function skipFirstPush(): bool
    {
        $skipFirstPush = $this->payment->getAdditionalInformation('skip_push');
        $this->logger->addDebug('[' . __METHOD__ . ':' . __LINE__ . '] - Skip First Push: ' . $skipFirstPush);

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
     * @param int|null    $receivedStatusCode
     * @param string|null $trxId
     *
     * @throws Exception
     *
     * @return bool
     *
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     * @SuppressWarnings(PHPMD.NPathComplexity)
     */
    protected function receivePushCheckDuplicates(?int $receivedStatusCode = null, ?string $trxId = null): bool
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

        $isRefund = $this->pushRequest->hasAdditionalInformation('service_action_from_magento', 'refund');

        $ignoredPaymentMethods = [
            Giftcards::CODE,
            Transfer::CODE
        ];

        if ($receivedStatusCode
            && $this->payment && $this->payment->getMethod()
            && ($this->pushTransactionType->getPushType() == PushTransactionType::BUCK_PUSH_TYPE_TRANSACTION)
            && (!in_array($this->payment->getMethod(), $ignoredPaymentMethods) || $isRefund)
        ) {
            if ($this->isDuplicateTransaction($receivedStatusCode, $trxId)
            ) {
                if ($this->isNewOrderAndReceivedSuccess($receivedStatusCode)) {
                    return false;
                }

                $this->logger->addDebug('[' . __METHOD__ . ':' . __LINE__ . '] - Skip Push the request is duplicate');
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
     * Check if transaction was already processed based on transaction statuses from payment additional information
     *
     * @param string $trxId
     * @param int    $receivedStatusCode
     *
     * @return bool
     */
    private function isDuplicateTransaction($receivedStatusCode, string $trxId): bool
    {
        $receivedTrxStatuses = $this->payment->getAdditionalInformation(
            self::BUCKAROO_RECEIVED_TRANSACTIONS_STATUSES
        );

        $this->logger->addDebug(sprintf(
            '[%s:%s] - Check for duplicate transaction pushes | order: %s',
            __METHOD__,
            __LINE__,
            var_export([
                'receivedTrxStatuses' => $receivedTrxStatuses,
                'receivedStatusCode' => $receivedStatusCode
            ], true)
        ));

        return $receivedTrxStatuses
            && is_array($receivedTrxStatuses)
            && isset($receivedTrxStatuses[$trxId])
            && ($receivedTrxStatuses[$trxId] == $receivedStatusCode);
    }

    /**
     * If the order has status new/pending and received status success then we skip from duplication transaction check
     *
     * @param int $receivedStatusCode
     *
     * @return bool
     */
    private function isNewOrderAndReceivedSuccess($receivedStatusCode): bool
    {
        $orderStatus = $this->helper->getOrderStatusByState($this->order, Order::STATE_NEW);
        if (($this->order->getState() == Order::STATE_NEW)
            && ($this->order->getStatus() == $orderStatus)
            && ($receivedStatusCode == BuckarooStatusCode::SUCCESS)
        ) {
            $this->logger->addDebug('[' . __METHOD__ . ':' . __LINE__ . '] - allow duplicated pushes '
                . 'for 190 statuses in case if order stills to be new/pending');
            return true;
        }

        return false;
    }

    /**
     * It updates the BUCKAROO_RECEIVED_TRANSACTIONS_STATUSES payment additional information
     * with the current received tx status.
     *
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
     * Checks if the order can be updated by checking its state.
     * If order is canceled and receives success push, reactivates it.
     *
     * Following Magento core's approach (Order::canInvoice, etc.) which checks state only.
     *
     * @return bool
     * @throws LocalizedException
     */
    protected function canUpdateOrderStatus(): bool
    {
        $currentState = $this->order->getState();

        $this->logger->addDebug(sprintf(
            '[%s:%s] - Checks if the order can be updated | State: %s | Status: %s',
            __METHOD__,
            __LINE__,
            $currentState,
            $this->order->getStatus()
        ));

        // Check if order is in a final state that cannot be updated
        // Following Magento core pattern: check state only, not status
        if ($currentState === Order::STATE_COMPLETE ||
            $currentState === Order::STATE_CLOSED ||
            $currentState === Order::STATE_HOLDED
        ) {
            return false;
        }

        // Check if canceled order should be reactivated
        if ($currentState === Order::STATE_CANCELED) {
            if ($this->shouldReactivateCanceledOrder()) {
                return $this->reactivateCanceledOrder();
            }
            return false;
        }

        // Order is in a normal updatable state (new, pending_payment, processing)
        return true;
    }

    /**
     * Check if a canceled order should be reactivated
     *
     * @return bool
     */
    private function shouldReactivateCanceledOrder(): bool
    {
        return ($this->order->getState() === Order::STATE_CANCELED)
            && ($this->order->getStatus() === Order::STATE_CANCELED)
            && ($this->pushTransactionType->getStatusKey() === 'BUCKAROO_MAGENTO2_STATUSCODE_SUCCESS')
            && $this->pushRequest->getRelatedtransactionPartialpayment() == null
            && !$this->payment->getAdditionalInformation('buckaroo_order_reactivated');
    }

    /**
     * Reactivate a canceled order when receiving success push
     * Handles uncanceling, invoice handling setup, and authorization transaction
     *
     * @return bool
     * @throws LocalizedException
     */
    private function reactivateCanceledOrder(): bool
    {
        $orderNumber = $this->order->getIncrementId();

        $this->logger->addDebug(sprintf(
            '[%s:%s] - Reactivating canceled order: %s',
            __METHOD__,
            __LINE__,
            $orderNumber
        ));

        // 1. Get the transaction key for authorization (used in comment)
        $transactionKey = $this->pushRequest->getTransactions();
        if (!$transactionKey || strlen($transactionKey) === 0) {
            $transactionKey = $this->pushRequest->getDatarequest();
        }

        // 2. Uncancel the order (resets all amounts, inventory, etc)
        $comment = __(
            'Order reactivated: Payment completed after cancellation (Push notification received). ' .
            'Transaction ID: "%1"',
            $transactionKey
        );
        $this->uncancelService->execute($this->order, (string)$comment);

        // 3. Mark as reactivated to prevent duplicates
        $this->payment->setAdditionalInformation('buckaroo_order_reactivated', true);

        // 4. Setup invoice handling flags (for Klarna/Afterpay with SHIPMENT mode)
        $this->setupInvoiceHandlingForReactivation();

        // 5. Preserve Klarna reservation number in payment additional information
        $reservationNumber = $this->order->getBuckarooReservationNumber();
        if ($reservationNumber) {
            $this->payment->setAdditionalInformation('buckaroo_reservation_number', $reservationNumber);
        }

        // 6. Delete ALL existing transactions to start fresh
        // This prevents circular references and stale transaction states
        try {
            $connection = $this->resourceConnection->getConnection();
            $tableName = $this->resourceConnection->getTableName('sales_payment_transaction');

            $deleted = $connection->delete(
                $tableName,
                ['order_id = ?' => $this->order->getId()]
            );

            if ($deleted > 0) {
                $this->logger->addDebug(sprintf(
                    '[%s:%s] - Order %s: Deleted %d transaction(s) to prevent circular references',
                    __METHOD__,
                    __LINE__,
                    $orderNumber,
                    $deleted
                ));
            }

            // Clear payment transaction references
            $this->payment->setLastTransId(null);
            $this->payment->setData('_transactionsLookup', null);
            $this->payment->setData('transaction', null);

        } catch (\Exception $e) {
            $this->logger->addError(sprintf(
                '[%s:%s] - Order %s: Failed to delete transactions - %s',
                __METHOD__,
                __LINE__,
                $orderNumber,
                $e->getMessage()
            ));
        }

        // 7. Save payment and order changes
        $this->payment->save();
        $this->order->save();

        $this->logger->addDebug(sprintf(
            '[%s:%s] - Order %s: Successfully reactivated and set to %s state',
            __METHOD__,
            __LINE__,
            $orderNumber,
            $this->order->getState()
        ));

        // 8. Force invoice flag for immediate processing
        $this->forceInvoice = true;

        return true;
    }

    /**
     * Setup invoice handling flags for reactivated orders
     * Detects if order should use SHIPMENT mode and sets appropriate flags
     *
     * @return void
     * @throws LocalizedException
     */
    private function setupInvoiceHandlingForReactivation(): void
    {
        $invoiceHandlingMode = $this->payment->getAdditionalInformation(
            InvoiceHandlingOptions::INVOICE_HANDLING
        );

        // If flag not set (e.g., failed authorization), check configs directly
        if ($invoiceHandlingMode === null || $invoiceHandlingMode === '') {
            $invoiceHandlingMode = $this->detectInvoiceHandlingMode();

            // Set the flag now for future use if it's SHIPMENT mode
            if ($invoiceHandlingMode == InvoiceHandlingOptions::SHIPMENT) {
                $this->payment->setAdditionalInformation(
                    InvoiceHandlingOptions::INVOICE_HANDLING,
                    InvoiceHandlingOptions::SHIPMENT
                );
            }
        }

        // For SHIPMENT mode, mark payment as already captured
        // This ensures the shipment observer will use offline capture
        if ($invoiceHandlingMode == InvoiceHandlingOptions::SHIPMENT) {
            $this->payment->setAdditionalInformation('buckaroo_already_captured', true);
            $this->logger->addDebug(sprintf(
                '[%s:%s] - Order %s: Set SHIPMENT invoice handling mode',
                __METHOD__,
                __LINE__,
                $this->order->getIncrementId()
            ));
        }
    }

    /**
     * Detect invoice handling mode from configuration
     * Checks method-specific config first, then falls back to general config
     *
     * @return int|string|null
     * @throws LocalizedException
     */
    private function detectInvoiceHandlingMode()
    {
        // Check method-specific config first (e.g., Klarna's "create_invoice_after_shipment")
        $methodSpecificConfig = $this->payment->getMethodInstance()->getConfigData('create_invoice_after_shipment');

        if ($methodSpecificConfig !== null && $methodSpecificConfig !== '') {
            return ($methodSpecificConfig == 1) ? InvoiceHandlingOptions::SHIPMENT : null;
        }

        // Fall back to general account config
        return $this->configAccount->getInvoiceHandling();
    }

    /**
     * Register authorization transaction for reactivated order
     * Allows manual "Capture Online" option in admin
     *
     * @return void
     */
    private function registerAuthorizationForReactivation(): void
    {
        // Get transaction key from push data (already determined in reactivateCanceledOrder)
        $transactionKey = $this->pushRequest->getTransactions();

        // If no brq_transactions, try brq_datarequest (used for Klarna/Afterpay authorization)
        if (!$transactionKey || strlen($transactionKey) === 0) {
            $transactionKey = $this->pushRequest->getDatarequest();
        }

        $this->logger->addDebug(sprintf(
            '[%s:%s] - Register authorization | Transaction key: %s | Source: %s',
            __METHOD__,
            __LINE__,
            $transactionKey ?? 'NULL',
            $this->pushRequest->getTransactions() ? 'brq_transactions' : 'brq_datarequest'
        ));

        if (!$transactionKey || strlen($transactionKey) === 0) {
            $this->logger->addDebug(sprintf(
                '[%s:%s] - No transaction key found in push data, skipping authorization registration',
                __METHOD__,
                __LINE__
            ));
            return;
        }

        $baseGrandTotal = $this->order->getBaseGrandTotal();

        $newTransactionId = $transactionKey . '-reauth';

        $this->payment->setTransactionId($newTransactionId);
        $this->payment->setCurrencyCode($this->order->getBaseCurrencyCode());
        $this->payment->setIsTransactionClosed(false);
        $this->payment->registerAuthorizationNotification($baseGrandTotal);

        // CRITICAL: Ensure the transaction ID stays as the reauth one after registerAuthorizationNotification
        // Magento might reset it, so we set it again along with LastTransId
        $this->payment->setTransactionId($newTransactionId);
        $this->payment->setLastTransId($newTransactionId);

        // Store the reauth transaction ID so addTransactionData() uses it as parent for captures
        $this->payment->setAdditionalInformation('buckaroo_reauth_transaction_id', $newTransactionId);

        // Verify canCapture works
        $canCapture = $this->payment->canCapture();
        $this->logger->addDebug(sprintf(
            '[%s:%s] - Authorization setup complete - CanCapture: %s',
            __METHOD__,
            __LINE__,
            $canCapture ? 'YES' : 'NO'
        ));
    }

    /**
     * Sets the transaction key in the payment's additional information if it's not already set.
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
     */
    protected function setOrderStatusMessage(): void
    {
        if (!empty($this->pushRequest->getStatusmessage())) {
            // Refresh order state to get the most current state
            $this->order = $this->order->load($this->order->getId());

            if ($this->order->getState() === Order::STATE_NEW
                && empty($this->pushRequest->getRelatedtransactionPartialpayment())
                && $this->pushRequest->hasPostData('statuscode', BuckarooStatusCode::SUCCESS)
            ) {
                $this->order->setState(Order::STATE_PROCESSING);
                $this->order->addCommentToStatusHistory(
                    $this->pushRequest->getStatusmessage(),
                    $this->helper->getOrderStatusByState($this->order, Order::STATE_PROCESSING)
                );
            } else {
                // Log the reason why we're not setting to processing
                if ($this->order->getState() !== Order::STATE_NEW) {
                    $this->logger->addDebug(sprintf(
                        '[%s:%s] - Skip setting order to processing, current state: %s (not NEW)',
                        __METHOD__,
                        __LINE__,
                        $this->order->getState()
                    ));
                }
                if ((
                        $this->pushRequest->hasPostData('statuscode', BuckarooStatusCode::PENDING_PROCESSING)
                        && in_array($this->order->getState(), [Order::STATE_PENDING_PAYMENT, Order::STATE_NEW], true)
                    )
                    ||
                    !$this->pushRequest->hasPostData('statuscode', BuckarooStatusCode::PENDING_PROCESSING)
                ) {
                    $this->order->addCommentToStatusHistory($this->pushRequest->getStatusmessage());
                }
            }
        }
    }

    /**
     * Checks if the push request is a group transaction
     *
     * @return bool
     */
    protected function isGroupTransactionPart()
    {
        if ($this->pushRequest->getTransactions() !== null) {
            $groupTransaction = $this->groupTransaction->getGroupTransactionByTrxId(
                $this->pushRequest->getTransactions()
            );
            if ($groupTransaction->getType() == 'partialpayment') {
                return true;
            }
        }
        return false;
    }

    /**
     * Update the status of an existing group transaction.
     *
     * @throws Exception
     */
    protected function savePartGroupTransaction(): void
    {
        $groupTransaction = $this->groupTransaction->getGroupTransactionByTrxId($this->pushRequest->getTransactions());

        // Only update if transaction exists and has an entity_id (not empty)
        if ($groupTransaction instanceof GroupTransaction && $groupTransaction->getEntityId()) {
            $groupTransaction->setData('status', $this->pushRequest->getStatusCode());
            $groupTransaction->save();

            $this->logger->addDebug(sprintf(
                '[GROUP_TRANSACTION] | [Push] | [%s:%s] - Updated group transaction status | Key: %s | Status: %s',
                __METHOD__,
                __LINE__,
                $this->pushRequest->getTransactions(),
                $this->pushRequest->getStatusCode()
            ));
        }
    }

    /**
     * Save new group transaction if needed
     *
     * For mixed payments, this ensures all payment methods (not just giftcards)
     * are saved to the group_transaction table for proper refund handling.
     *
     * @return void
     */
    protected function saveNewGroupTransactionIfNeeded(): void
    {
        // Validate required data
        if (!$this->hasRequiredGroupTransactionData()) {
            return;
        }

        // Check if this transaction is already saved to prevent duplicates
        if ($this->isGroupTransactionAlreadySaved()) {
            return;
        }

        // Create and save the transaction using typed method (Magento best practice)
        try {
            $amount = $this->pushRequest->getAmount() ?? $this->pushRequest->getAmountDebit();

            $this->groupTransaction->createGroupTransaction(
                $this->pushRequest->getInvoiceNumber(),
                $this->pushRequest->getTransactions(),
                $this->pushRequest->getTransactionMethod(),
                $this->pushRequest->getCurrency(),
                (float)$amount,
                (int)$this->pushRequest->getStatusCode(),
                $this->pushRequest->getRelatedtransactionPartialpayment(),
                'partialpayment'
            );
        } catch (Exception $e) {
            $this->logger->addError(sprintf(
                '[GROUP_TRANSACTION] | [Push] | [%s:%s] - ERROR saving group transaction: %s',
                __METHOD__,
                __LINE__,
                $e->getMessage()
            ));
        }
    }

    /**
     * Store single giftcard payment information in payment additional_information
     * This is for single full giftcard payments (not mixed/partial payments)
     * Used by refund system to identify and refund giftcard correctly
     *
     * @return void
     * @throws LocalizedException
     */
    private function storeSingleGiftcardPaymentInfo(): void
    {
        // Only for giftcard payment method
        if (!$this->payment || $this->payment->getMethod() !== 'buckaroo_magento2_giftcards') {
            return;
        }

        // Only for successful transactions
        if ((int)$this->pushRequest->getStatusCode() !== $this->buckarooStatusCode::SUCCESS) {
            return;
        }

        $transactionMethod = $this->pushRequest->getTransactionMethod();

        // Only if it's an actual giftcard
        if (!$this->isActualGiftcard($transactionMethod)) {
            return;
        }

        // Store giftcard metadata for refund
        $this->payment->setAdditionalInformation('single_giftcard_payment', true);
        $this->payment->setAdditionalInformation('single_giftcard_servicecode', $transactionMethod);
        $this->payment->setAdditionalInformation('single_giftcard_amount', $this->pushRequest->getAmount() ?? $this->pushRequest->getAmountDebit());
        $this->payment->setAdditionalInformation('single_giftcard_transaction_id', $this->pushRequest->getTransactions());
        $this->payment->setAdditionalInformation('single_giftcard_currency', $this->pushRequest->getCurrency());

        $this->logger->addDebug(sprintf(
            '[SINGLE_GIFTCARD] | [%s:%s] - Stored single giftcard payment info | Method: %s | Amount: %s',
            __METHOD__,
            __LINE__,
            $transactionMethod,
            $this->pushRequest->getAmount()
        ));
    }

    /**
     * Check if required data is present for group transaction
     * Only for MIXED/PARTIAL payments (not single giftcards)
     *
     * @return bool
     */
    private function hasRequiredGroupTransactionData(): bool
    {
        // Basic requirements
        if (!$this->pushRequest->getTransactions() || !$this->pushRequest->getInvoiceNumber()) {
            return false;
        }

        // Only for mixed/partial payments with relatedtransaction
        return (bool)$this->pushRequest->getRelatedtransactionPartialpayment();
    }

    /**
     * Check if transaction method is an actual giftcard
     *
     * @param string|null $transactionMethod
     * @return bool
     */
    private function isActualGiftcard(?string $transactionMethod): bool
    {
        if (!$transactionMethod) {
            return false;
        }

        // Check if it's in the giftcard collection
        $foundGiftcard = $this->giftcardCollection->getItemByColumnValue('servicecode', $transactionMethod);

        // Also check for buckaroovoucher
        return $foundGiftcard !== null || $transactionMethod === 'buckaroovoucher';
    }

    /**
     * Check if group transaction is already saved
     *
     * @return bool
     */
    private function isGroupTransactionAlreadySaved(): bool
    {
        $existingTransaction = $this->groupTransaction->getGroupTransactionByTrxId(
            $this->pushRequest->getTransactions()
        );

        return $existingTransaction && $existingTransaction->getEntityId();
    }


    /**
     * @return true
     */
    protected function canProcessPostData()
    {
        return true;
    }

    /**
     * Checks if the payment is a partial payment using a gift card.
     *
     * @throws LocalizedException
     *
     * @return bool
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
     */
    protected function addGiftcardPartialPaymentToPaymentInformation()
    {
        $payment = $this->order->getPayment();

        $transactionAmount = $this->pushRequest->getAmount();
        $transactionStatus = $this->pushRequest->getStatusCode();
        $transactionKey = $this->pushRequest->getTransactions();
        $transactionMethod = $this->pushRequest->getTransactionMethod();

        $transactionData = $payment->getAdditionalInformation(BuckarooAdapter::BUCKAROO_ALL_TRANSACTIONS);

        $transactionArray = [];
        if (is_array($transactionData) && count($transactionData) > 0) {
            $transactionArray = $transactionData;
        }

        if (!empty($transactionKey) && $transactionAmount > 0) {
            $transactionArray[$transactionKey] = [$transactionMethod, $transactionAmount, $transactionStatus];

            $payment->setAdditionalInformation(
                BuckarooAdapter::BUCKAROO_ALL_TRANSACTIONS,
                $transactionArray
            );
        }
    }

    /**
     * Process the push according the response status
     *
     * @throws LocalizedException
     *
     * @return bool
     *
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
     * @throws LocalizedException
     *
     * @return false|string|null
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
     *
     * @throws LocalizedException
     *
     * @return bool
     *
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     * @SuppressWarnings(PHPMD.NPathComplexity)
     * @SuppressWarnings(PHPMD.ExcessiveMethodLength)
     */
    public function processSucceededPush(string $newStatus, string $message): bool
    {
        $this->logger->addDebug(sprintf(
            '[%s:%s] - Process the successful push response from Buckaroo | newStatus: %s',
            __METHOD__,
            __LINE__,
            var_export($newStatus, true)
        ));

        $this->setBuckarooReservationNumber();

        $this->sendOrderEmail();

        $paymentDetails = $this->getPaymentDetails($message);
        $paymentDetails['state'] = Order::STATE_PROCESSING;
        $paymentDetails['newStatus'] = $newStatus;

        $this->setSpecificPaymentDetails();

        $this->dontSaveOrderUponSuccessPush = false;

        // Handle capture transactions sent by Buckaroo (C800, mutationtype=collecting)
        $isCaptureTx = $this->pushRequest->hasPostData('transaction_type', 'C800');
        $isCaptureMutation = $this->pushRequest->hasPostData('mutationtype', 'collecting')
            || $this->pushRequest->hasPostData('mutationtype', 'Collecting');
        $isKlarnaMethod = $this->pushRequest->hasPostData('transaction_method', ['klarnakp', 'KlarnaKp']);
        $hasKlarnaCaptureId = $isKlarnaMethod && !empty($this->pushRequest->getServiceKlarnakpCaptureid());
        $isSuccessStatus = ((int)$this->pushRequest->getStatusCode() === $this->buckarooStatusCode::SUCCESS);

        if ($isCaptureTx || $isCaptureMutation || ($hasKlarnaCaptureId && $isSuccessStatus)) {
            // Build capture description using current amount context
            $amount = $this->order->getBaseTotalDue();
            if (!empty($this->pushRequest->getAmount())) {
                $amount = (float)$this->pushRequest->getAmount();
            }

            // Check if invoice should be created on shipment instead
            // First, try to read from payment's additional_information (set during order placement)
            $invoiceHandlingMode = $this->order->getPayment()->getAdditionalInformation(
                InvoiceHandlingOptions::INVOICE_HANDLING
            );

            // If not set (e.g., order was canceled before authorization completed),
            // check method-specific config directly
            if ($invoiceHandlingMode === null || $invoiceHandlingMode === '') {
                $methodSpecificConfig = $this->payment->getMethodInstance()->getConfigData('create_invoice_after_shipment');
                if ($methodSpecificConfig !== null && $methodSpecificConfig !== '') {
                    $invoiceHandlingMode = ($methodSpecificConfig == 1) ? InvoiceHandlingOptions::SHIPMENT : null;
                } else {
                    // Fall back to general account config
                    $invoiceHandlingMode = $this->configAccount->getInvoiceHandling();
                }
            }

            if ($invoiceHandlingMode == InvoiceHandlingOptions::SHIPMENT) {
                $this->logger->addDebug(sprintf(
                    '[%s:%s] - CAPTURE detected but invoice handling is SHIPMENT mode - skipping invoice creation',
                    __METHOD__,
                    __LINE__
                ));

                $payment = $this->order->getPayment();
                $payment->setAdditionalInformation('buckaroo_already_captured', true);

                // Ensure the invoice handling mode is persisted so the shipment observer can detect it
                $payment->setAdditionalInformation(
                    InvoiceHandlingOptions::INVOICE_HANDLING,
                    InvoiceHandlingOptions::SHIPMENT
                );
                $payment->save();

                $description = 'Capture status : <strong>' . $message . '</strong><br/>'
                    . 'Total amount of ' . $this->order->getBaseCurrency()->formatTxt($amount) . ' has been captured. Invoice will be created on shipment.';

                $this->orderRequestService->updateOrderStatus(
                    Order::STATE_PROCESSING,
                    $newStatus,
                    $description,
                    false,
                    $this->dontSaveOrderUponSuccessPush
                );
                return true;
            }

            $description = 'Capture status : <strong>' . $message . '</strong><br/>'
                . 'Total amount of ' . $this->order->getBaseCurrency()->formatTxt($amount) . ' has been captured.';

            if (!$this->saveInvoice()) {
                $this->logger->addDebug(sprintf('[%s:%s] - CAPTURE_INVOICE_FAILED', __METHOD__, __LINE__));
                return false;
            }

            $this->orderRequestService->updateOrderStatus(
                Order::STATE_PROCESSING,
                $newStatus,
                $description,
                false,
                $this->dontSaveOrderUponSuccessPush
            );
            $this->logger->addDebug(sprintf('[%s:%s] - CAPTURE_COMPLETE', __METHOD__, __LINE__));
            return true;
        }

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
     * @throws Exception
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

        if (in_array($this->payment->getMethod(), $authPpaymentMethods)
            && (($this->payment->getMethod() == Klarnakp::CODE
                    || (
                        !empty($this->pushRequest->getTransactionType())
                        && in_array($this->pushRequest->getTransactionType(), ['I038', 'I880'])
                    )
                ) && ($this->pushRequest->getStatusCode() == 190))
        ) {
            // Check if order is in a valid state to be updated to processing
            $validStatesForProcessing = [
                Order::STATE_NEW,
                Order::STATE_PENDING_PAYMENT,
                Order::STATE_PAYMENT_REVIEW
            ];

            if (!in_array($this->order->getState(), $validStatesForProcessing)) {
                $this->logger->addDebug(sprintf(
                    '[%s:%s] - Skip setting order to processing, current state: %s is not valid for processing transition',
                    __METHOD__,
                    __LINE__,
                    $this->order->getState()
                ));
                return;
            }

            $this->logger->addDebug(sprintf(
                '[%s:%s] - Process succeeded push authorization | paymentMethod: %s',
                __METHOD__,
                __LINE__,
                var_export($this->payment->getMethod(), true)
            ));

            $this->order->setState(Order::STATE_PROCESSING);
            $this->order->save();
        }
    }

    protected function setBuckarooReservationNumber(): bool
    {
        return false;
    }

    /**
     * Send Order email if was not sent
     *
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
            $this->logger->addDebug('[' . __METHOD__ . ':' . __LINE__ . '] - Send order email');
            $syncMode = (bool)$this->configAccount->getOrderConfirmationEmailSync($store);
            $this->orderRequestService->sendOrderEmail($this->order, $syncMode);
        } else {
            $this->logger->addDebug('[' . __METHOD__ . ':' . __LINE__ . '] - Skip sending order email (EmailSent: ' . ($this->order->getEmailSent() ? 'Yes' : 'No') . ')');
        }
    }

    /**
     * Can create invoice on push
     *
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     *
     * @throws LocalizedException
     *
     * @return bool
     */
    protected function canPushInvoice(): bool
    {
        if ($this->payment->getMethodInstance()->getConfigData('payment_action') == 'authorize') {
            // For authorize payments with shipment-based invoicing, allow processing to set the flag
            $invoiceHandlingMode = $this->order->getPayment()->getAdditionalInformation(
                InvoiceHandlingOptions::INVOICE_HANDLING
            );
            return ($invoiceHandlingMode == InvoiceHandlingOptions::SHIPMENT);
        }

        return true;
    }

    /**
     * Creates and saves the invoice and adds for each invoice the buckaroo transaction keys
     * Only when the order can be invoiced and has not been invoiced before.
     *
     * @throws BuckarooException
     * @throws LocalizedException
     * @throws Exception
     *
     * @return bool
     *
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     * @SuppressWarnings(PHPMD.NPathComplexity)
     */
    protected function saveInvoice(): bool
    {
        $this->logger->addDebug('[' . __METHOD__ . ':' . __LINE__ . '] - Save Invoice');

        if (!$this->forceInvoice
            && (!$this->order->canInvoice() || $this->order->hasInvoices())) {
            $this->logger->addDebug('[' . __METHOD__ . ':' . __LINE__ . '] - Order can not be invoiced');

            return false;
        }

        $this->addTransactionData();

        // Check method-specific config first, fall back to general config
        // Method-specific config key: create_invoice_after_shipment (only exists for Klarna & Afterpay)
        // If "Yes" (value=1), invoice should be created after shipment (SHIPMENT mode)
        $methodSpecificConfig = $this->payment->getMethodInstance()->getConfigData('create_invoice_after_shipment');
        $useShipmentMode = false;

        if ($methodSpecificConfig !== null && $methodSpecificConfig !== '') {
            // Method has specific config value - use it (1 = Yes = SHIPMENT mode, 0 = No = immediate)
            $useShipmentMode = ($methodSpecificConfig == 1);
        } else {
            // No method-specific config or not set - use general account config
            $useShipmentMode = ($this->configAccount->getInvoiceHandling() == InvoiceHandlingOptions::SHIPMENT);
        }

        if ($useShipmentMode) {
            // In shipment mode, record the payment as authorized but don't capture yet
            // Store the invoice handling setting for later use
            $this->payment->setAdditionalInformation(
                InvoiceHandlingOptions::INVOICE_HANDLING,
                InvoiceHandlingOptions::SHIPMENT
            );
            $this->payment->save();
            return true;
        }

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
                    '[' . __METHOD__ . ':' . __LINE__ . '] - Set invoice state PAID group transaction'
                );
                $invoice->setState(Invoice::STATE_PAID);
            }

            if (!$invoice->getEmailSent() && $this->configAccount->getInvoiceEmail($this->order->getStore())) {
                $this->logger->addDebug('[' . __METHOD__ . ':' . __LINE__ . '] - Send Invoice Email ');
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
     *
     * @throws LocalizedException
     * @throws Exception
     *
     * @return Payment
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

        $rawDetails = $this->payment->getAdditionalInformation(Transaction::RAW_DETAILS);
        $rawDetails = $rawDetails ?: [];
        $rawDetails[$transactionKey] = $rawInfo;
        $this->payment->setAdditionalInformation(Transaction::RAW_DETAILS, $rawDetails);

        /**
         * Save the payment's transaction key.
         */
        $this->payment->setTransactionId($transactionKey . '-capture');

        // For reactivated orders, use the reauth transaction ID as parent to avoid circular references
        // Otherwise, use the original transaction key
        $reauthTransactionId = $this->payment->getAdditionalInformation('buckaroo_reauth_transaction_id');
        $parentTransactionId = $reauthTransactionId ?: $transactionKey;

        $this->payment->setParentTransactionId($parentTransactionId);

        if ($reauthTransactionId) {
            $this->logger->addDebug(sprintf(
                '[%s:%s] - Using reauth transaction ID as parent for capture: %s (original: %s)',
                __METHOD__,
                __LINE__,
                $reauthTransactionId,
                $transactionKey
            ));
        }

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
     *
     * @throws LocalizedException
     *
     * @return bool
     *
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     */
    public function processFailedPush(string $newStatus, string $message): bool
    {
        $this->logger->addDebug(sprintf(
            '[%s:%s] - Process the failed push response from Buckaroo | newStatus: %s',
            __METHOD__,
            __LINE__,
            var_export($newStatus, true)
        ));

        if (($this->order->getState() === Order::STATE_PROCESSING)
            && ($this->order->getStatus() === Order::STATE_PROCESSING)
        ) {
            $this->logger->addDebug('[' . __METHOD__ . ':' . __LINE__ . '] - Do not update to failed if we had a success');
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
                '[%s:%s] - Process the failed push response from Buckaroo. Cancel Order: %s',
                __METHOD__,
                __LINE__,
                $message
            ));

            // Add a clear cancellation message to order history before canceling
            $this->order->addCommentToStatusHistory('Payment failed. Canceling order due to payment failure: ' . $message);

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

            try {
                $this->order->cancel()->save();

                if (!$this->isMagentoGiftCardRefundActive()) {
                    $this->giftCardRefundService->refund($this->order);
                }

                $this->orderRequestService->updateOrderStatus(Order::STATE_CANCELED, $newStatus, $description);
            } catch (\Throwable $th) {
                $this->logger->addError(sprintf(
                    '[%s:%s] - Process failed push from Buckaroo. Cancel Order| [ERROR]: %s',
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

        // Add clear failure message to order history
        $this->order->addCommentToStatusHistory('Payment failed: ' . $message);

        $this->orderRequestService->updateOrderStatus(Order::STATE_CANCELED, $newStatus, $description, $force);

        return true;
    }

    /**
     * Transfer payment methods receive status pending for success order
     *
     * @param string|false|null $newStatus
     * @param string            $statusMessage
     *
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     *
     * @throws Exception
     *
     * @return bool
     */
    protected function processPendingPaymentPush($newStatus, string $statusMessage): bool
    {
        if (!$this->canProcessPendingPush()) {
            return true;
        }

        $this->logger->addDebug('[' . __METHOD__ . ':' . __LINE__ . '] - Process Pending Push');

        $this->processPendingPaymentEmail();
        $description = $this->buildPendingPaymentDescription($statusMessage);
        $this->orderRequestService->updateOrderStatus(Order::STATE_PENDING_PAYMENT, $newStatus, $description);

        return true;
    }

    /**
     * Process email sending for pending payment push
     *
     * @throws LocalizedException
     */
    private function processPendingPaymentEmail(): void
    {
        $store = $this->order->getStore();
        $paymentMethod = $this->payment->getMethodInstance();
        $orderIsCanceledOrWillBeCanceled = $this->order->isCanceled() || $this->order->getState() === Order::STATE_CANCELED;

        $statusCode = $this->pushRequest->getStatusCode();
        $statusCodeInt = $statusCode !== null ? (int)$statusCode : 0;
        $isSuccessfulPayment = $this->isSuccessfulPaymentStatus($statusCodeInt);

        if ($this->shouldSendPendingPaymentEmail($isSuccessfulPayment, $store, $paymentMethod)) {
            $this->logger->addDebug('[' . __METHOD__ . ':' . __LINE__ . '] - Process Pending Push - SEND EMAIL (Success Status: ' . $statusCode . ')');
            $this->orderRequestService->sendOrderEmail($this->order);
        } else {
            $this->logger->addDebug('[' . __METHOD__ . ':' . __LINE__ . '] - Process Pending Push - SKIP EMAIL (Status: ' . $statusCode . ', EmailSent: ' . ($this->order->getEmailSent() ? 'Yes' : 'No') . ', OrderCanceled: ' . ($orderIsCanceledOrWillBeCanceled ? 'Yes' : 'No') . ')');
        }
    }

    /**
     * Check if pending payment email should be sent
     *
     * @param bool  $isSuccessfulPayment
     * @param mixed $store
     * @param mixed $paymentMethod
     *
     * @return bool
     */
    private function shouldSendPendingPaymentEmail(bool $isSuccessfulPayment, $store, $paymentMethod): bool
    {
        return !$this->order->getEmailSent()
            && $isSuccessfulPayment
            && (
                $this->configAccount->getOrderConfirmationEmail($store)
                || $paymentMethod->getConfigData('order_email', $store)
            );
    }

    /**
     * Build description for pending payment
     *
     * @param string $statusMessage
     *
     * @throws LocalizedException
     *
     * @return string
     */
    private function buildPendingPaymentDescription(string $statusMessage): string
    {
        $description = 'Payment Push Status: ' . __($statusMessage);
        $transferDetails = $this->getTransferDetails();

        if (!empty($transferDetails)) {
            $this->payment->setAdditionalInformation('transfer_details', $transferDetails);
            foreach ($transferDetails as $key => $transferDetail) {
                $description .= '<br/><strong>' . $this->getLabel($key) . '</strong>: ' . $transferDetail;
            }
        }

        return $description;
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

        // Check if this is shipment mode - payment authorized but not captured yet
        $invoiceHandlingMode = $this->order->getPayment()->getAdditionalInformation(
            InvoiceHandlingOptions::INVOICE_HANDLING
        );
        $isShipmentMode = ($invoiceHandlingMode == InvoiceHandlingOptions::SHIPMENT);

        if ($this->canPushInvoice() && !$isShipmentMode) {
            $description = 'Payment status : <strong>' . $message . "</strong><br/>";
            $amount = $this->order->getBaseTotalDue();
            $description .= 'Total amount of ' .
                $this->order->getBaseCurrency()->formatTxt($amount) . ' has been paid';
        } else {
            $description = 'Authorization status : <strong>' . $message . "</strong><br/>";
            if ($isShipmentMode) {
                $description .= 'Total amount of ' . $this->order->getBaseCurrency()->formatTxt($amount)
                    . ' has been authorized. Payment will be captured when order is shipped.';
            } else {
                $description .= 'Total amount of ' . $this->order->getBaseCurrency()->formatTxt($amount)
                    . ' has been authorized. Please create an invoice to capture the authorized amount.';
            }
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
     *
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

    /**
     * Set Specific Payment Details that will appear under the Payment Method Name on Order
     *
     * @throws LocalizedException
     */
    protected function setSpecificPaymentDetails(): void
    {
        $specificPaymentDetails = $this->getSpecificPaymentDetails();
        if (!empty($specificPaymentDetails)) {
            $this->payment->setAdditionalInformation('specific_payment_details', $specificPaymentDetails);
        }
    }

    /**
     * Return Specific details that will appear on order payment details in admin
     *
     * @return array
     */
    protected function getSpecificPaymentDetails(): array
    {
        return [];
    }

    /**
     * Returns label
     *
     * @param string $field
     *
     * @return Phrase
     */
    protected function getLabel(string $field)
    {
        $words = explode('_', $field);
        $transformedWords = array_map('ucfirst', $words);
        return __(implode(' ', $transformedWords));
    }

    /**
     * Checks if a given status code is a successful payment status.
     *
     * @param int $statusCode
     *
     * @return bool
     */
    private function isSuccessfulPaymentStatus(int $statusCode): bool
    {
        return $statusCode === Response::STATUSCODE_SUCCESS;
    }

    /**
     * Check if Magento's native gift card refund observers will be triggered
     * Prevents duplicate refunds by checking if redirect setting triggers Magento observers
     *
     * @return bool
     */
    private function isMagentoGiftCardRefundActive(): bool
    {
        try {
            // Use Buckaroo refund if Adobe Commerce gift card classes don't exist
            if (!$this->hasGiftCardAccountClasses()) {
                return false;
            }

            // Check if redirect setting will trigger Magento observers
            $failureRedirectEnabled = $this->isFailureRedirectToCheckoutEnabled();

            if (!$failureRedirectEnabled) {
                // Redirect disabled: Magento observers won't trigger, use Buckaroo refund
                return false;
            }

            // Redirect enabled: Skip Buckaroo refund to prevent duplicates
            return true;

        } catch (\Throwable $e) {
            $this->logger->addError('Gift card observer detection failed: ' . $e->getMessage() . ' - using Buckaroo fallback');
            return false;
        }
    }

    /**
     * Check if Adobe Commerce gift card classes exist
     */
    private function hasGiftCardAccountClasses(): bool
    {
        return interface_exists(\Magento\GiftCardAccount\Api\GiftCardAccountRepositoryInterface::class) &&
               class_exists(\Magento\GiftCardAccount\Observer\RevertGiftCardAccountBalance::class);
    }

    /**
     * Check if failure redirect setting is enabled (triggers Magento observers)
     */
    private function isFailureRedirectToCheckoutEnabled(): bool
    {
        $accountConfig = ObjectManager::getInstance()
            ->get('Buckaroo\Magento2\Model\ConfigProvider\Account');

        return (bool) $accountConfig->getFailureRedirectToCheckout($this->order->getStore());
    }
}
