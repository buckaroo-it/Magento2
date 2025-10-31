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
use Buckaroo\Magento2\Model\ConfigProvider\Method\Klarnakp;
use Buckaroo\Magento2\Model\OrderStatusFactory;
use Buckaroo\Magento2\Model\Service\GiftCardRefundService;
use Buckaroo\Magento2\Service\Push\OrderRequestService;
use Magento\Sales\Api\Data\TransactionInterface;
use Magento\Sales\Model\Order;

/**
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class KlarnaKpProcessor extends DefaultProcessor
{
    /**
     * @var Klarnakp
     */
    private $klarnakpConfig;

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
     * @param Klarnakp                $klarnakpConfig
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
        Klarnakp $klarnakpConfig
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
            $giftCardRefundService
        );
        $this->klarnakpConfig = $klarnakpConfig;
    }

    /**
     * Skip the push if the conditions are met.
     *
     * @throws \Exception
     *
     * @return bool
     */
    protected function skipPush(): bool
    {
        if ($this->pushRequest->hasAdditionalInformation('initiated_by_magento', 1)
            && $this->pushRequest->hasAdditionalInformation('service_action_from_magento', 'pay')
            && !empty($this->pushRequest->getServiceKlarnakpCaptureid())
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
        $trxId = parent::getTransactionKey();

        if (!empty($this->pushRequest->getServiceKlarnakpAutopaytransactionkey())
        ) {
            $trxId = $this->pushRequest->getServiceKlarnakpAutopaytransactionkey();
        }

        return $trxId;
    }

    protected function setBuckarooReservationNumber(): bool
    {
        $reservationNumberFromPush = $this->pushRequest->getServiceKlarnakpReservationnumber();
        
        $this->logger->addDebug(sprintf(
            '[KLARNA_KP] | [%s:%s] - setBuckarooReservationNumber called for order %s | ' .
            'currentReservationNumber: %s | pushReservationNumber: %s',
            __METHOD__,
            __LINE__,
            $this->order->getIncrementId(),
            $this->order->getBuckarooReservationNumber() ?? 'NULL',
            $reservationNumberFromPush ?? 'NULL'
        ));
        
        if (!empty($reservationNumberFromPush)) {
            $this->order->setBuckarooReservationNumber($reservationNumberFromPush);
            $this->order->save();
            
            $this->logger->addDebug(sprintf(
                '[KLARNA_KP] | [%s:%s] - Successfully saved reservation number from PUSH for order %s: %s',
                __METHOD__,
                __LINE__,
                $this->order->getIncrementId(),
                $reservationNumberFromPush
            ));
            
            return true;
        }

        $this->logger->addWarning(sprintf(
            '[KLARNA_KP] | [%s:%s] - No reservation number in PUSH for order %s! ' .
            'Push data may be incomplete or this is not a reserve transaction.',
            __METHOD__,
            __LINE__,
            $this->order->getIncrementId()
        ));

        return false;
    }

    /**
     * @param array $paymentDetails
     *
     * @throws \Exception
     *
     * @return bool
     */
    protected function invoiceShouldBeSaved(array &$paymentDetails): bool
    {
        if ($this->pushRequest->hasAdditionalInformation('initiated_by_magento', 1) &&
            (
                $this->pushRequest->hasPostData('transaction_method', 'KlarnaKp') &&
                $this->pushRequest->hasAdditionalInformation('service_action_from_magento', 'pay') &&
                empty($this->pushRequest->getServiceKlarnakpReservationnumber()) &&
                $this->klarnakpConfig->isInvoiceCreatedAfterShipment()
            )) {
            $this->dontSaveOrderUponSuccessPush = true;
            return false;
        }

        if (!empty($this->pushRequest->getServiceKlarnakpAutopaytransactionkey())
            && ($this->pushRequest->getStatusCode() == 190)
        ) {
            return true;
        }

        return true;
    }

    /**
     * Process succeeded push authorization for Klarna KP.
     * Handles the special case where a canceled order can become successful within 48 hours.
     *
     * @throws \Exception
     */
    protected function processSucceededPushAuthorization(): void
    {
        if ($this->pushRequest->getStatusCode() == 190) {
            // For Klarna KP, we need to handle the special case where canceled orders
            // can be completed within 48 hours (as per Klarna's policy)
            $validStatesForProcessing = [
                Order::STATE_NEW,
                Order::STATE_PENDING_PAYMENT,
                Order::STATE_PAYMENT_REVIEW,
                Order::STATE_CANCELED
            ];

            if (!in_array($this->order->getState(), $validStatesForProcessing)) {
                $this->logger->addDebug(sprintf(
                    '[KLARNA_KP] | [%s:%s] - Skip setting order to processing, current state: %s is not valid for processing transition',
                    __METHOD__,
                    __LINE__,
                    $this->order->getState()
                ));
                return;
            }

            // If order was canceled, we need to reset it first (using parent logic)
            if ($this->order->getState() === Order::STATE_CANCELED) {
                // This will be handled by the canUpdateOrderStatus() method in parent class
                $this->logger->addDebug(sprintf(
                    '[KLARNA_KP] | [%s:%s] - Order was canceled, will be reset by canUpdateOrderStatus logic',
                    __METHOD__,
                    __LINE__
                ));
            }

            $this->logger->addDebug(sprintf(
                '[KLARNA_KP] | [%s:%s] - Process succeeded push authorization | paymentMethod: %s | currentState: %s',
                __METHOD__,
                __LINE__,
                $this->payment->getMethod(),
                $this->order->getState()
            ));

            // Only set to processing if not already canceled (the canUpdateOrderStatus will handle canceled->new transition)
            if ($this->order->getState() !== Order::STATE_CANCELED) {
                $this->order->setState(Order::STATE_PROCESSING);
                $this->order->save();
            }
        }
    }
}
