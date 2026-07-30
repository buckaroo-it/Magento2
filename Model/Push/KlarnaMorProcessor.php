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

use Buckaroo\Magento2\Helper\Data;
use Buckaroo\Magento2\Helper\PaymentGroupTransaction;
use Buckaroo\Magento2\Logging\BuckarooLoggerInterface;
use Buckaroo\Magento2\Model\BuckarooStatusCode;
use Buckaroo\Magento2\Model\ConfigProvider\Account;
use Buckaroo\Magento2\Model\OrderStatusFactory;
use Buckaroo\Magento2\Model\ResourceModel\Giftcard\Collection as GiftcardCollection;
use Buckaroo\Magento2\Model\ResourceModel\GroupTransaction;
use Buckaroo\Magento2\Model\Service\GiftCardRefundService;
use Buckaroo\Magento2\Service\Order\Uncancel;
use Buckaroo\Magento2\Service\Push\KlarnaMorOrderService;
use Buckaroo\Magento2\Service\Push\OrderRequestService;
use Magento\Framework\App\ResourceConnection;
use Magento\Sales\Api\Data\TransactionInterface;
use Magento\Sales\Api\InvoiceRepositoryInterface;
use Magento\Sales\Api\OrderPaymentRepositoryInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;

/**
 * Push processor for Klarna MOR (Merchant on Record) payment method.
 * Uses Buckaroo DataRequest key instead of Klarna reservation number.
 *
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class KlarnaMorProcessor extends DefaultProcessor
{
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
     * @param GiftCardRefundService $giftCardRefundService
     * @param Uncancel $uncancelService
     * @param ResourceConnection $resourceConnection
     * @param GiftcardCollection $giftcardCollection
     * @param OrderRepositoryInterface|null $orderRepository
     * @param OrderPaymentRepositoryInterface|null $paymentRepository
     * @param InvoiceRepositoryInterface|null $invoiceRepository
     * @param GroupTransaction|null $groupTransactionResource
     * @SuppressWarnings(PHPMD.ExcessiveParameterList)
     */
    public function __construct(
        OrderRequestService $orderRequestService,
        PushTransactionType $pushTransactionType,
        BuckarooLoggerInterface $logger,
        Data                                                $helper,
        TransactionInterface             $transaction,
        PaymentGroupTransaction          $groupTransaction,
        BuckarooStatusCode               $buckarooStatusCode,
        OrderStatusFactory               $orderStatusFactory,
        Account                          $configAccount,
        GiftCardRefundService            $giftCardRefundService,
        Uncancel                         $uncancelService,
        ResourceConnection               $resourceConnection,
        GiftcardCollection               $giftcardCollection,
        ?OrderRepositoryInterface        $orderRepository = null,
        ?OrderPaymentRepositoryInterface $paymentRepository = null,
        ?InvoiceRepositoryInterface      $invoiceRepository = null,
        ?GroupTransaction                $groupTransactionResource = null
    ) {
        parent::__construct(
            $orderRequestService,
            $pushTransactionType,
            $logger,
            $helper,
            $transaction,
            $groupTransaction,
            $buckarooStatusCode,
            $orderStatusFactory,
            $configAccount,
            $giftCardRefundService,
            $uncancelService,
            $resourceConnection,
            $giftcardCollection,
            null,
            $orderRepository,
            $paymentRepository,
            $invoiceRepository,
            $groupTransactionResource
        );
    }

    /**
     * Process the push according to the response status.
     *
     * @throws \Exception
     *
     * @return bool
     */
    protected function processPushByStatus(): bool
    {
        if ($this->isPlazaSecondaryDataRequestPush()) {
            return $this->processPlazaSecondaryDataRequestPush();
        }

        return parent::processPushByStatus();
    }

    /**
     * Detect a Plaza-originated extend/update reservation push for Klarna MOR.
     *
     * @return bool
     */
    private function isPlazaSecondaryDataRequestPush(): bool
    {
        if ((int)$this->pushRequest->getStatusCode() !== BuckarooStatusCode::SUCCESS
            || $this->pushRequest->hasAdditionalInformation('initiated_by_magento', 1)
            || empty($this->pushRequest->getDatarequest())
        ) {
            return false;
        }

        return $this->shouldPreserveExistingDataRequestKey((string)$this->pushRequest->getDatarequest());
    }

    /**
     * Record a Plaza extend/update reservation on the Magento order without altering the reservation key.
     *
     * @throws \Exception
     *
     * @return bool
     */
    private function processPlazaSecondaryDataRequestPush(): bool
    {
        $dataRequestKey = (string)$this->pushRequest->getDatarequest();

        $this->logger->addDebug(sprintf(
            '[KLARNA_MOR] | [%s:%s] - Processing Plaza secondary data request push for order %s | key: %s',
            __METHOD__,
            __LINE__,
            $this->order->getIncrementId(),
            $dataRequestKey
        ));

        $this->order->addCommentToStatusHistory(
            (string)__(
                'Buckaroo: Klarna reservation updated via Buckaroo Plaza (DataRequest: %1).',
                $dataRequestKey
            )
        );
        $this->orderRepository->save($this->order);

        return true;
    }

    /**
     * Skip the push if the conditions are met.
     *
     * Skips capture callbacks initiated by Magento to avoid duplicate processing.
     *
     * @throws \Exception
     *
     * @return bool
     */
    protected function skipPush(): bool
    {
        if ($this->pushRequest->hasAdditionalInformation('initiated_by_magento', 1)
            && $this->pushRequest->hasAdditionalInformation('service_action_from_magento', 'pay')
        ) {
            return true;
        }

        return parent::skipPush();
    }

    /**
     * Retrieves the transaction key from the push request.
     *
     * @return string
     */
    protected function getTransactionKey(): string
    {
        $isCapturePush = $this->pushRequest->hasPostData('transaction_type', 'C800')
            || $this->pushRequest->hasPostData('mutationtype', 'collecting')
            || $this->pushRequest->hasPostData('mutationtype', 'Collecting');

        if ($isCapturePush && !empty($this->pushRequest->getTransactions())) {
            return $this->pushRequest->getTransactions();
        }

        return parent::getTransactionKey();
    }

    /**
     * Save Buckaroo DataRequest key from push notification.
     *
     * This replaces the old reservation number mechanism for the MOR flow.
     *
     * @return bool
     */
    protected function setBuckarooReservationNumber(): bool
    {
        return $this->saveBuckarooDataRequestKey();
    }

    /**
     * Save the DataRequest key from push notification to the order.
     *
     * @return bool
     */
    protected function saveBuckarooDataRequestKey(): bool
    {
        // brq_datarequest is the top-level field in the push, not a service parameter
        $dataRequestKey = $this->pushRequest->getDatarequest();

        $this->logger->addDebug(sprintf(
            '[KLARNA_MOR] | [%s:%s] - saveBuckarooDataRequestKey called for order %s | '
            . 'currentDataRequestKey: %s | pushDataRequestKey: %s',
            __METHOD__,
            __LINE__,
            $this->order->getIncrementId(),
            $this->order->getBuckarooDatarequestKey() ?? 'NULL',
            $dataRequestKey ?? 'NULL'
        ));

        if (!empty($dataRequestKey)) {
            if ($this->shouldPreserveExistingDataRequestKey($dataRequestKey)) {
                $this->logger->addDebug(sprintf(
                    '[KLARNA_MOR] | [%s:%s] - Preserving existing DataRequest key for order %s. '
                    . 'Incoming push key %s belongs to a secondary MOR data request.',
                    __METHOD__,
                    __LINE__,
                    $this->order->getIncrementId(),
                    $dataRequestKey
                ));
                return false;
            }

            $this->order->setBuckarooDatarequestKey($dataRequestKey);
            $this->payment->setAdditionalInformation('buckaroo_datarequest_key', $dataRequestKey);
            $this->orderRepository->save($this->order);

            $this->logger->addDebug(sprintf(
                '[KLARNA_MOR] | [%s:%s] - Successfully saved DataRequest key from PUSH for order %s: %s',
                __METHOD__,
                __LINE__,
                $this->order->getIncrementId(),
                $dataRequestKey
            ));

            return true;
        }

        $this->logger->addWarning(sprintf(
            '[KLARNA_MOR] | [%s:%s] - No DataRequest key in PUSH for order %s! '
            . 'Push data may be incomplete or this is not a Reserve transaction.',
            __METHOD__,
            __LINE__,
            $this->order->getIncrementId()
        ));

        return false;
    }

    /**
     * Secondary MOR data requests (extend/update) must not replace the original reservation key.
     *
     * @param string $incomingDataRequestKey
     *
     * @return bool
     */
    private function shouldPreserveExistingDataRequestKey(string $incomingDataRequestKey): bool
    {
        $existingKey = $this->order->getBuckarooDatarequestKey()
            ?? $this->payment->getAdditionalInformation('buckaroo_datarequest_key');

        if (empty($existingKey) || $existingKey === $incomingDataRequestKey) {
            return false;
        }

        $secondaryActions = ['extendreservation', 'updatereservation'];

        if ($this->pushRequest->hasAdditionalInformation('initiated_by_magento', 1)
            && $this->pushRequest->hasAdditionalInformation('service_action_from_magento', $secondaryActions)
        ) {
            return true;
        }

        $pendingKeys = (array)$this->payment->getAdditionalInformation(
            KlarnaMorOrderService::PENDING_DATAREQUEST_PUSH_KEYS
        );

        return isset($pendingKeys[$incomingDataRequestKey]);
    }

    /**
     * Determine whether an invoice should be created for this push.
     *
     * When "Create Invoice After Shipment" is enabled, defer invoice creation.
     *
     * @throws \Magento\Framework\Exception\LocalizedException
     *
     * @return bool
     */
    protected function saveInvoice(): bool
    {
        $isCapturePush = $this->pushRequest->hasPostData('transaction_type', 'C800')
            || $this->pushRequest->hasPostData('mutationtype', 'collecting')
            || $this->pushRequest->hasPostData('mutationtype', 'Collecting');

        if ($isCapturePush) {
            $captureTransactionKey = $this->getTransactionKey();

            if (!empty($captureTransactionKey)) {
                $this->logger->addDebug(sprintf(
                    '[KLARNA_MOR] | [%s:%s] - Plaza capture push for order %s; '
                    . 'saving capture transaction key: %s',
                    __METHOD__,
                    __LINE__,
                    $this->order->getIncrementId(),
                    $captureTransactionKey
                ));
                $this->payment->setAdditionalInformation('buckaroo_capture_transaction_key', $captureTransactionKey);
                $this->payment->setAdditionalInformation('buckaroo_already_captured', true);
                $this->paymentRepository->save($this->payment);
            }

            if ($this->order->hasInvoices()) {
                return true;
            }
        }

        return parent::saveInvoice();
    }

    /**
     * Process succeeded push authorization for Klarna MOR.
     *
     * @throws \Exception
     */
    protected function processSucceededPushAuthorization(): void
    {
        if ((int)$this->pushRequest->getStatusCode() === BuckarooStatusCode::SUCCESS) {
            $validStatesForProcessing = [
                Order::STATE_NEW,
                Order::STATE_PENDING_PAYMENT,
                Order::STATE_PAYMENT_REVIEW,
                Order::STATE_CANCELED,
            ];

            if (!in_array($this->order->getState(), $validStatesForProcessing)) {
                $this->logger->addDebug(sprintf(
                    '[KLARNA_MOR] | [%s:%s] - Skip processing, current state %s is not valid.',
                    __METHOD__,
                    __LINE__,
                    $this->order->getState()
                ));
                return;
            }

            $this->logger->addDebug(sprintf(
                '[KLARNA_MOR] | [%s:%s] - Process succeeded push authorization | paymentMethod: %s | currentState: %s',
                __METHOD__,
                __LINE__,
                $this->payment->getMethod(),
                $this->order->getState()
            ));

            if ($this->order->getState() !== Order::STATE_CANCELED) {
                $this->order->setState(Order::STATE_PROCESSING);
                $this->orderRepository->save($this->order);
            }
        }
    }
}
