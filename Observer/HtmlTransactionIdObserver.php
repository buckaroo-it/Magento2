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

namespace Buckaroo\Magento2\Observer;

use Buckaroo\Magento2\Service\CheckPaymentType;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Sales\Api\Data\OrderPaymentInterface;
use Magento\Sales\Api\Data\TransactionInterface;
use Magento\Sales\Api\TransactionRepositoryInterface;
use Magento\Sales\Model\Order\Payment\Transaction;

class HtmlTransactionIdObserver implements ObserverInterface
{
    /**
     * @var CheckPaymentType
     */
    private $checkPaymentType;

    /**
     * @var TransactionRepositoryInterface
     */
    private $transactionRepository;

    /**
     * @param CheckPaymentType               $checkPaymentType
     * @param TransactionRepositoryInterface $transactionRepository
     */
    public function __construct(
        CheckPaymentType $checkPaymentType,
        TransactionRepositoryInterface $transactionRepository
    ) {
        $this->checkPaymentType = $checkPaymentType;
        $this->transactionRepository = $transactionRepository;
    }

    /**
     * Update txn_id to a link for the plaza transaction
     *
     * @param Observer $observer
     */
    public function execute(Observer $observer)
    {
        /** @var Transaction $transaction */
        $transaction = $observer->getDataObject();
        $order = $transaction->getOrder();

        if (!$this->shouldProcessTransaction($transaction, $order)) {
            return;
        }

        $txnId = $this->getBaseTransactionId($transaction);
        $txtType = $this->getEffectiveTransactionType($transaction);
        $paymentMethod = $order->getPayment()->getMethod();

        if ($this->shouldSkipTransaction($txtType, $transaction)) {
            return;
        }

        $htmlLink = $this->buildTransactionLink($txtType, $paymentMethod, $txnId, $transaction->getTxnId());
        $transaction->setData('html_txn_id', $htmlLink);
    }

    /**
     * Check if the transaction should be processed
     *
     * @param Transaction $transaction
     * @param mixed $order
     * @return bool
     */
    private function shouldProcessTransaction($transaction, $order): bool
    {
        $txnId = $this->getBaseTransactionId($transaction);
        return $this->checkPaymentType->isBuckarooPayment($order->getPayment()) && $txnId !== false;
    }

    /**
     * Get base transaction ID without a suffix
     *
     * @param Transaction $transaction
     * @return string|false
     */
    private function getBaseTransactionId($transaction)
    {
        $txnIdArray = explode("-", $transaction->getTxnId());
        return reset($txnIdArray);
    }

    /**
     * Get effective transaction type (handles void transactions)
     *
     * @param Transaction $transaction
     * @return string
     */
    private function getEffectiveTransactionType($transaction): string
    {
        $txtType = $transaction->getTxnType();

        if ($transaction->getTxnType() === TransactionInterface::TYPE_VOID && $transaction->getParentId()) {
            $parentTransaction = $this->transactionRepository->get($transaction->getParentId());
            if ($parentTransaction) {
                $txtType = $parentTransaction->getTxnType();
            }
        }

        return $txtType;
    }

    /**
     * Check if transaction should be skipped
     *
     * @param string $txtType
     * @param Transaction $transaction
     * @return bool
     */
    private function shouldSkipTransaction(string $txtType, $transaction): bool
    {
        return $txtType == TransactionInterface::TYPE_REFUND && $this->isOfflineRefund($transaction->getTxnId());
    }

    /**
     * Build HTML transaction link
     *
     * @param string $txtType
     * @param string $paymentMethod
     * @param string $txnId
     * @param string $fullTxnId
     * @return string
     */
    private function buildTransactionLink(string $txtType, string $paymentMethod, string $txnId, string $fullTxnId): string
    {
        if ($txtType == 'authorization' && $this->isKlarnaPayment($paymentMethod)) {
            return sprintf(
                '<a href="https://plaza.buckaroo.nl/Transaction/DataRequest/Details/%s" target="_blank">%s</a>',
                $txnId,
                $fullTxnId
            );
        }

        return sprintf(
            '<a href="https://plaza.buckaroo.nl/Transaction/Transactions/Details?transactionKey=%s" target="_blank">%s</a>',
            $txnId,
            $fullTxnId
        );
    }

    /**
     * Check if the payment method is a Klarna payment
     *
     * @param string $paymentMethod
     * @return bool
     */
    private function isKlarnaPayment(string $paymentMethod): bool
    {
        $klarnaPaymentMethods = [
            'buckaroo_magento2_klarna',
            'buckaroo_magento2_klarnakp',
            'buckaroo_magento2_klarnain'
        ];

        return in_array($paymentMethod, $klarnaPaymentMethods);
    }

    /**
     * Check if this is an offline refund (reusing parent transaction ID)
     *
     * @param string $transactionId
     * @return bool
     */
    private function isOfflineRefund(string $transactionId): bool
    {
        return preg_match('/-(?:capture|auth|authorization|void)$/', $transactionId) === 1;
    }
}
