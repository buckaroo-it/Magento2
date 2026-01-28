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

        $txnId = $this->extractTransactionId($transaction);
        $txtType = $transaction->getTxnType();
        $paymentMethod = $order->getPayment()->getMethod();
        $isVoidTransaction = false;

        // Handle void transactions by checking parent transaction type
        if ($this->isVoidTransaction($transaction)) {
            $isVoidTransaction = true;
            $txtType = $this->getParentTransactionType($transaction) ?? $txtType;
        }

        if ($this->shouldSkipOfflineRefund($txtType, $transaction)) {
            return;
        }

        $this->setTransactionHtmlLink($transaction, $paymentMethod, $txtType, $isVoidTransaction, $txnId);
    }

    /**
     * Check if transaction should be processed
     *
     * @param Transaction $transaction
     * @param \Magento\Sales\Model\Order $order
     * @return bool
     */
    private function shouldProcessTransaction(Transaction $transaction, $order): bool
    {
        $txnIdArray = explode("-", $transaction->getTxnId());
        $txnId = reset($txnIdArray);

        return $this->checkPaymentType->isBuckarooPayment($order->getPayment()) && $txnId !== false;
    }

    /**
     * Extract transaction ID from transaction
     *
     * @param Transaction $transaction
     * @return string
     */
    private function extractTransactionId(Transaction $transaction): string
    {
        $txnIdArray = explode("-", $transaction->getTxnId());
        return reset($txnIdArray);
    }

    /**
     * Check if transaction is a void transaction
     *
     * @param Transaction $transaction
     * @return bool
     */
    private function isVoidTransaction(Transaction $transaction): bool
    {
        return $transaction->getTxnType() === TransactionInterface::TYPE_VOID;
    }

    /**
     * Get parent transaction type
     *
     * @param Transaction $transaction
     * @return string|null
     */
    private function getParentTransactionType(Transaction $transaction): ?string
    {
        if (!$transaction->getParentId()) {
            return null;
        }

        $parentTransaction = $this->transactionRepository->get($transaction->getParentId());
        return $parentTransaction ? $parentTransaction->getTxnType() : null;
    }

    /**
     * Check if offline refund should be skipped
     *
     * @param string $txtType
     * @param Transaction $transaction
     * @return bool
     */
    private function shouldSkipOfflineRefund(string $txtType, Transaction $transaction): bool
    {
        return $txtType == TransactionInterface::TYPE_REFUND 
            && $this->isOfflineRefund($transaction->getTxnId());
    }

    /**
     * Set HTML transaction link
     *
     * @param Transaction $transaction
     * @param string $paymentMethod
     * @param string $txtType
     * @param bool $isVoidTransaction
     * @param string $txnId
     * @return void
     */
    private function setTransactionHtmlLink(
        Transaction $transaction,
        string $paymentMethod,
        string $txtType,
        bool $isVoidTransaction,
        string $txnId
    ): void {
        if ($this->shouldUseDataRequestUrl($paymentMethod, $txtType, $isVoidTransaction)) {
            $this->setDataRequestUrl($transaction, $txnId);
        } else {
            $this->setTransactionDetailsUrl($transaction, $txnId);
        }
    }

    /**
     * Check if DataRequest URL should be used
     *
     * @param string $paymentMethod
     * @param string $txtType
     * @param bool $isVoidTransaction
     * @return bool
     */
    private function shouldUseDataRequestUrl(
        string $paymentMethod,
        string $txtType,
        bool $isVoidTransaction
    ): bool {
        return $this->isKlarnaPayment($paymentMethod) 
            && ($txtType == 'authorization' || $isVoidTransaction);
    }

    /**
     * Set DataRequest URL for transaction
     *
     * @param Transaction $transaction
     * @param string $txnId
     * @return void
     */
    private function setDataRequestUrl(Transaction $transaction, string $txnId): void
    {
        $transaction->setData(
            'html_txn_id',
            sprintf(
                '<a href="https://plaza.buckaroo.nl/Transaction/DataRequest/Details/%s" target="_blank">%s</a>',
                $txnId,
                $transaction->getTxnId()
            )
        );
    }

    /**
     * Set Transaction Details URL for transaction
     *
     * @param Transaction $transaction
     * @param string $txnId
     * @return void
     */
    private function setTransactionDetailsUrl(Transaction $transaction, string $txnId): void
    {
        $transaction->setData(
            'html_txn_id',
            sprintf(
                '<a href="https://plaza.buckaroo.nl/Transaction/Transactions/Details?transactionKey=%s" target="_blank">%s</a>',
                $txnId,
                $transaction->getTxnId()
            )
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
