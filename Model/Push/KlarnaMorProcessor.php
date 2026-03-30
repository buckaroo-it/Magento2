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
use Buckaroo\Magento2\Model\ConfigProvider\Method\Klarna;
use Buckaroo\Magento2\Model\OrderStatusFactory;
use Buckaroo\Magento2\Model\ResourceModel\Giftcard\Collection as GiftcardCollection;
use Buckaroo\Magento2\Model\Service\GiftCardRefundService;
use Buckaroo\Magento2\Service\Order\Uncancel;
use Buckaroo\Magento2\Service\Push\OrderRequestService;
use Magento\Framework\App\ResourceConnection;
use Magento\Sales\Api\Data\TransactionInterface;
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
     * @var Klarna
     */
    private Klarna $klarnaConfig;

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
     * @param ResourceConnection      $resourceConnection
     * @param GiftcardCollection      $giftcardCollection
     * @param Klarna                  $klarnaConfig
     *
     * @SuppressWarnings(PHPMD.ExcessiveParameterList)
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
        Account $configAccount,
        GiftCardRefundService $giftCardRefundService,
        Uncancel $uncancelService,
        ResourceConnection $resourceConnection,
        GiftcardCollection $giftcardCollection,
        Klarna $klarnaConfig
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
            $giftcardCollection
        );
        $this->klarnaConfig = $klarnaConfig;
    }

    /**
     * Skip the push if the conditions are met.
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
     * For Klarna MOR, use the DataRequest key from push if available.
     *
     * @return string
     */
    protected function getTransactionKey(): string
    {
        $trxId = parent::getTransactionKey();

        if (!empty($this->pushRequest->getDatarequest())) {
            $trxId = $this->pushRequest->getDatarequest();
        }

        return $trxId;
    }

    /**
     * Save Buckaroo DataRequest key from push notification.
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
            $this->order->setBuckarooDatarequestKey($dataRequestKey);
            $this->payment->setAdditionalInformation('buckaroo_datarequest_key', $dataRequestKey);
            $this->order->save();

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
     * Determine whether an invoice should be created for this push.
     * When "Create Invoice After Shipment" is enabled, defer invoice creation.
     *
     * @param array $paymentDetails
     *
     * @throws \Exception
     *
     * @return bool
     */
    protected function invoiceShouldBeSaved(array &$paymentDetails): bool
    {
        if ($this->pushRequest->hasAdditionalInformation('initiated_by_magento', 1)
            && $this->pushRequest->hasAdditionalInformation('service_action_from_magento', 'pay')
            && $this->klarnaConfig->isInvoiceCreatedAfterShipment()
        ) {
            $this->dontSaveOrderUponSuccessPush = true;
            return false;
        }

        if (!empty($this->pushRequest->getDatarequest())
            && ($this->pushRequest->getStatusCode() == 190)
        ) {
            return true;
        }

        return true;
    }

    /**
     * Process succeeded push authorization for Klarna MOR.
     *
     * @throws \Exception
     */
    protected function processSucceededPushAuthorization(): void
    {
        if ($this->pushRequest->getStatusCode() == 190) {
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
                $this->order->save();
            }
        }
    }
}
