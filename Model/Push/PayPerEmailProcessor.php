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
use Buckaroo\Magento2\Model\ConfigProvider\Account;
use Buckaroo\Magento2\Model\ConfigProvider\Method\PayPerEmail;
use Buckaroo\Magento2\Model\Method\BuckarooAdapter;
use Buckaroo\Magento2\Model\OrderStatusFactory;
use Buckaroo\Magento2\Model\ResourceModel\Giftcard\Collection as GiftcardCollection;
use Buckaroo\Magento2\Model\Service\GiftCardRefundService;
use Buckaroo\Magento2\Service\Order\Uncancel;
use Buckaroo\Magento2\Service\Push\OrderRequestService;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Exception\FileSystemException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Sales\Api\Data\TransactionInterface;
use Magento\Sales\Model\Order;

/**
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class PayPerEmailProcessor extends DefaultProcessor
{
    protected const LOCK_PREFIX = 'bk_push_ppe_';

    /**
     * @var PayPerEmail
     */
    private $configPayPerEmail;

    /**
     * @var bool
     */
    private $isPayPerEmailB2BModePushInitial = false;

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
     * @param PayPerEmail             $configPayPerEmail
     *
     * @SuppressWarnings(PHPMD.ExcessiveParameterList)
     */
    public function __construct(
        OrderRequestService     $orderRequestService,
        PushTransactionType     $pushTransactionType,
        BuckarooLoggerInterface $logger,
        Data                    $helper,
        TransactionInterface    $transaction,
        PaymentGroupTransaction $groupTransaction,
        BuckarooStatusCode      $buckarooStatusCode,
        OrderStatusFactory      $orderStatusFactory,
        Account                 $configAccount,
        GiftCardRefundService   $giftCardRefundService,
        Uncancel                $uncancelService,
        ResourceConnection      $resourceConnection,
        GiftcardCollection      $giftcardCollection,
        PayPerEmail             $configPayPerEmail
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
        $this->configPayPerEmail = $configPayPerEmail;
    }

    /**
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     * @SuppressWarnings(PHPMD.NPathComplexity)
     *
     * @param PushRequestInterface $pushRequest
     *
     * @throws FileSystemException
     * @throws \Exception
     */
    public function processPush(PushRequestInterface $pushRequest): bool
    {
        $this->initializeFields($pushRequest);

        //Check if the push is PayLink request
        $this->receivePushCheckPayLink();

        // Skip Push
        if ($this->skipPush()) {
            return true;
        }

        //Check second push for PayPerEmail
        $isDifferentPaymentMethod = $this->setPaymentMethodIfDifferent();

        // Check Push Duplicates
        if ($this->receivePushCheckDuplicates()) {
            throw new BuckarooException(__('Skipped handling this push, duplicate'));
        }

        // Check if the order can be updated
        if (!$this->canUpdateOrderStatus()) {
            if ($isDifferentPaymentMethod && $this->configPayPerEmail->isEnabledB2B()) {
                $this->logger->addDebug(sprintf(
                    '[PUSH - PayPerEmail] | [Webapi] | [%s:%s] - Update Order State | currentState: %s',
                    __METHOD__,
                    __LINE__,
                    $this->order->getState()
                ));
                if ($this->order->getState() === Order::STATE_COMPLETE) {
                    $this->order->setState(Order::STATE_PROCESSING);
                    $this->order->save();
                }
                return true;
            }
            $this->logger->addDebug(
                '[PUSH - PayPerEmail] | [Webapi] | [' . __METHOD__ . ':' . __LINE__ . '] - Order can not receive updates'
            );
            $this->orderRequestService->setOrderNotificationNote(__('The order has already been processed.'));
            throw new BuckarooException(
                __('Signature from push is correct but the order can not receive updates')
            );
        }

        $this->setTransactionKey();

        $this->setOrderStatusMessage();

        if ($this->isGroupTransactionPart()) {
            $this->savePartGroupTransaction();
            return true;
        }

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
     * Set Payment method as PayPerEmail if the push request is PayLink
     *
     * @throws \Exception
     */
    private function receivePushCheckPayLink(): void
    {
        if (!empty($this->pushRequest->getAdditionalInformation('frompaylink'))
            && $this->pushTransactionType->getStatusKey() == 'BUCKAROO_MAGENTO2_STATUSCODE_SUCCESS'
        ) {
            $this->payment->setMethod('buckaroo_magento2_payperemail');
            $this->payment->save();
            $this->order->save();
        }
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
        if ($this->skipPayPerEmailCancel()) {
            return true;
        }

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
     * Skip Process PayPerEmail cancel request if cron is enabled
     *
     * @return bool
     */
    private function skipPayPerEmailCancel(): bool
    {
        $failedStatuses = $this->buckarooStatusCode->getFailedStatuses();
        if (!empty($this->pushRequest->getTransactionMethod())
            && in_array($this->pushTransactionType->getStatusKey(), $failedStatuses)
            && $this->configPayPerEmail->getEnabledCronCancelPPE()
        ) {
            return true;
        }
        return false;
    }

    /**
     * Set the payment method if the request is from Pay Per Email
     *
     * @throws \Exception
     *
     * @return bool
     */
    private function setPaymentMethodIfDifferent(): bool
    {
        $status = $this->pushTransactionType->getStatusKey();
        if ($status !== 'BUCKAROO_MAGENTO2_STATUSCODE_SUCCESS') {
            return false;
        }

        $transactionKey = $this->getTransactionKey();
        $payPerEmailKey = $this->payment->getAdditionalInformation(BuckarooAdapter::BUCKAROO_ORIGINAL_TRANSACTION_KEY_KEY);
        $isPayPerEmailOrder = $this->payment->getMethod() === 'buckaroo_magento2_payperemail'
            || $this->payment->getAdditionalInformation('isPayPerEmail') !== null;

        $transactionMethod = $this->pushRequest->getTransactionMethod();
        if (!empty($transactionMethod) && strtolower( $transactionMethod) !== 'payperemail') {
            $transactionMethod = strtolower($transactionMethod);
            $this->saveActualPaymentMethodAndKeyForRefund($transactionKey, $transactionMethod);
            return true;
        }

        if ($isPayPerEmailOrder && !empty($transactionKey) && $transactionKey !== $payPerEmailKey) {
            $transactionMethod = $this->deriveActualPaymentMethodFromPush();
            if ($transactionMethod !== null) {
                $this->saveActualPaymentMethodAndKeyForRefund($transactionKey, $transactionMethod);
                return true;
            }
        }

        return false;
    }

    private function saveActualPaymentMethodAndKeyForRefund(string $transactionKey, string $transactionMethod): void
    {
        $this->payment->setAdditionalInformation(
            BuckarooAdapter::BUCKAROO_ACTUAL_PAYMENT_METHOD,
            $transactionMethod
        );
        $this->payment->setAdditionalInformation(
            BuckarooAdapter::BUCKAROO_ACTUAL_PAYMENT_TRANSACTION_KEY,
            $transactionKey
        );
        $this->payment->save();
        $this->order->save();
    }

    private function deriveActualPaymentMethodFromPush(): ?string
    {
        if (method_exists($this->pushRequest, 'getPrimaryService')) {
            $primary = $this->pushRequest->getPrimaryService();
            if (!empty($primary) && strtolower((string) $primary) !== 'payperemail') {
                return strtolower((string) $primary);
            }
        }
        if (method_exists($this->pushRequest, 'getData')) {
            $data = $this->pushRequest->getData();
            if (is_array($data)) {
                foreach (array_keys($data) as $key) {
                    if (preg_match('/^brq_service_([a-z0-9]+)_/i', (string) $key, $m)) {
                        $service = strtolower($m[1]);
                        if ($service !== 'payperemail' && $service !== 'paylink') {
                            return $service;
                        }
                    }
                }
            }
        }
        return null;
    }

    /**
     */
    protected function setOrderStatusMessage(): void
    {
        if (!empty($this->pushRequest->getStatusmessage())) {
            if ($this->order->getState() === Order::STATE_NEW
                && empty($this->pushRequest->getAdditionalInformation('frompayperemail'))
                && empty($this->pushRequest->getRelatedtransactionPartialpayment())
                && $this->pushRequest->hasPostData('statuscode', BuckarooStatusCode::SUCCESS)
            ) {
                $this->order->setState(Order::STATE_PROCESSING);
                $this->order->addCommentToStatusHistory(
                    $this->pushRequest->getStatusmessage(),
                    $this->helper->getOrderStatusByState($this->order, Order::STATE_PROCESSING)
                );
            } else {
                $this->order->addCommentToStatusHistory($this->pushRequest->getStatusmessage());
            }
        }
    }

    /**
     * Check if the Pay Per Email payment is in B2B mode.
     *
     * @return bool
     */
    public function isPayPerEmailB2BModePush(): bool
    {
        if (!isset($this->isPayPerEmailB2BModePushInitial)) {
            if (!empty($this->pushRequest->getAdditionalInformation('frompayperemail'))
                && !empty($this->pushRequest->getTransactionMethod())
                && ($this->pushRequest->getTransactionMethod() == 'payperemail')
                && $this->configPayPerEmail->isEnabledB2B()) {
                $this->logger->addDebug(sprintf(
                    '[PUSH - PayPerEmail] | [Webapi] | [%s:%s] - The transaction is PayPerEmail B2B',
                    __METHOD__,
                    __LINE__
                ));
                $this->isPayPerEmailB2BModePushInitial = true;
            }
        } else {
            $this->isPayPerEmailB2BModePushInitial = false;
        }

        return $this->isPayPerEmailB2BModePushInitial;
    }

    /**
     * Check if the Pay Per Email payment is in B2B mode and in the initial push.
     *
     * @return bool
     */
    public function isPayPerEmailB2BModePushInitial(): bool
    {
        return $this->isPayPerEmailB2BModePush()
            && ($this->pushTransactionType->getStatusKey() == 'BUCKAROO_MAGENTO2_STATUSCODE_WAITING_ON_CONSUMER');
    }

    /**
     * @throws BuckarooException
     * @throws LocalizedException
     *
     * @return false|string|null
     */
    protected function getNewStatus()
    {
        $newStatus = $this->orderStatusFactory->get($this->pushRequest->getStatusCode(), $this->order);

        $this->logger->addDebug(sprintf(
            '[PUSH - PayPerEmail] | [Webapi] | [%s:%s] - Get New Status | newStatus: %s',
            __METHOD__,
            __LINE__,
            var_export($newStatus, true)
        ));

        if ($this->isPayPerEmailB2BModePushInitial()) {
            $this->pushTransactionType->setStatusKey('BUCKAROO_MAGENTO2_STATUSCODE_SUCCESS');
            $newStatus = $this->configAccount->getOrderStatusSuccess();
            $this->logger->addDebug(sprintf(
                '[PUSH - PayPerEmail] | [Webapi] | [%s:%s] - Get New Status | newStatus: %s',
                __METHOD__,
                __LINE__,
                var_export([$this->pushTransactionType->getStatusKey(), $newStatus], true)
            ));
        }

        return $newStatus;
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

        if ($this->isPayPerEmailB2BModePushInitial) {
            $description = '';
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
     * @throws \Exception
     *
     * @return bool
     */
    protected function invoiceShouldBeSaved(array &$paymentDetails): bool
    {
        if (!$this->isPayPerEmailB2BModePushInitial && $this->isPayPerEmailB2BModePush()) {
            //Fix for suspected fraud when the order currency does not match with the payment's currency
            $amount = $this->payment->isSameCurrency() && $this->payment->isCaptureFinal($this->order->getGrandTotal())
                ? $this->order->getGrandTotal()
                : $this->order->getBaseTotalDue();
            $this->payment->registerCaptureNotification($amount);
            $this->payment->save();
            $this->order->setState('complete');
            $this->order->addCommentToStatusHistory($paymentDetails['description'], 'complete');
            $this->order->save();

            if ($transactionKey = $this->getTransactionKey()) {
                foreach ($this->order->getInvoiceCollection() as $invoice) {
                    $invoice->setTransactionId($transactionKey)->save();
                }
            }
            return false;
        }
        return true;
    }

    /**
     * @inheritdoc
     */
    protected function canProcessPendingPush(): bool
    {
        return true;
    }
}
