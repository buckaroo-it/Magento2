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

use Buckaroo\Magento2\Api\PushRequestInterface;
use Buckaroo\Magento2\Exception as BuckarooException;
use Buckaroo\Magento2\Helper\Data;
use Buckaroo\Magento2\Helper\PaymentGroupTransaction;
use Buckaroo\Magento2\Logging\BuckarooLoggerInterface;
use Buckaroo\Magento2\Model\BuckarooStatusCode;
use Buckaroo\Magento2\Model\ConfigProvider\Account;
use Buckaroo\Magento2\Model\ConfigProvider\Method\Giftcards;
use Buckaroo\Magento2\Model\ConfigProvider\Method\PayPerEmail;
use Buckaroo\Magento2\Model\ConfigProvider\Method\Voucher;
use Buckaroo\Magento2\Model\Method\BuckarooAdapter;
use Buckaroo\Magento2\Model\OrderStatusFactory;
use Buckaroo\Magento2\Service\Push\OrderRequestService;
use Magento\Framework\Exception\FileSystemException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Lock\LockManagerInterface;
use Magento\Sales\Api\Data\TransactionInterface;
use Magento\Sales\Model\Order;

/**
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class PayPerEmailProcessor extends LockedPushProcessor
{
    protected const LOCK_PREFIX = 'bk_push_ppe_';

    /**
     * @var PayPerEmail
     */
    private PayPerEmail $configPayPerEmail;

    /**
     * @var bool
     */
    private bool $isPayPerEmailB2BModePushInitial = false;

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
     * @param LockManagerInterface $lockManager
     * @param PayPerEmail $configPayPerEmail
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
        LockManagerInterface $lockManager,
        PayPerEmail $configPayPerEmail
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
            $lockManager
        );
        $this->configPayPerEmail = $configPayPerEmail;
    }

    /**
     * @throws FileSystemException
     * @throws \Exception
     *
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     * @SuppressWarnings(PHPMD.NPathComplexity)
     */
    public function processPush(PushRequestInterface $pushRequest): bool
    {
        $this->initializeFields($pushRequest);

        $lockName = $this->generateLockName();
        $this->stopLockedPush($lockName);

        if ($this->lockPushProcessingCriteria()) {
            $this->lockManager->lock($lockName);
        }

        try {
            //Check if the push is PayLink request
            $this->receivePushCheckPayLink();

            // Skip Push
            if ($this->skipPush()) {
                return true;
            }

            //Check second push for PayPerEmail
            $isDifferentPaymentMethod = $this->setPaymentMethodIfDifferent();

            // Check Push Dublicates
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
                    '[PUSH - PayPerEmail] | [Webapi] | ['.__METHOD__.':'.__LINE__.'] - Order can not receive updates'
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
        } finally {
            $this->lockManager->unlock($lockName);
        }

        return true;
    }

    /**
     * Determine if the lock push processing criteria are met.
     *
     * @return bool
     */
    protected function lockPushProcessingCriteria(): bool
    {
        return !empty($this->pushRequest->getAdditionalInformation('frompayperemail'));
    }

    /**
     * Set Payment method as PayPerEmail if the push request is PayLink
     *
     * @return void
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
     * @return bool
     * @throws \Exception
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
     * @return bool
     * @throws \Exception
     */
    private function setPaymentMethodIfDifferent(): bool
    {
        $status = $this->pushTransactionType->getStatusKey();
        if (!empty($this->pushRequest->getTransactionMethod())
            && $status == 'BUCKAROO_MAGENTO2_STATUSCODE_SUCCESS'
            && $this->pushRequest->getTransactionMethod() != 'payperemail') {
            $transactionMethod = strtolower($this->pushRequest->getTransactionMethod());
            $this->payment->setAdditionalInformation('isPayPerEmail', $transactionMethod);

            $options = new \Buckaroo\Magento2\Model\Config\Source\PaymentMethods\PayPerEmail();
            foreach ($options->toOptionArray() as $item) {
                if (($item['value'] == $transactionMethod) && isset($item['code'])) {
                    $this->payment->setMethod($item['code']);
                    $this->payment->setAdditionalInformation(
                        BuckarooAdapter::BUCKAROO_ORIGINAL_TRANSACTION_KEY_KEY,
                        $this->getTransactionKey()
                    );
                    if ($item['code'] == 'buckaroo_magento2_creditcards') {
                        $this->payment->setAdditionalInformation('card_type', $transactionMethod);
                    }
                }
            }
            $this->payment->save();
            $this->order->save();
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
                && empty($this->pushRequest->getAdditionalInformation('frompayperemail'))
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
     * @return false|string|null
     * @throws BuckarooException
     * @throws LocalizedException
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
     * @return bool
     * @throws \Exception
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
            $this->order->addStatusHistoryComment($paymentDetails['description'], 'complete');
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
}
