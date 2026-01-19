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

        $txnIdArray = explode("-", $transaction->getTxnId());
        $txnId = reset($txnIdArray);

        if ($this->checkPaymentType->isBuckarooPayment($order->getPayment()) && $txnId !== false) {
            $txtType = $transaction->getTxnType();
            $paymentMethod = $order->getPayment()->getMethod();

            // Handle void transactions by checking parent transaction type
            if ($transaction->getTxnType() === TransactionInterface::TYPE_VOID) {
                if ($transaction->getParentId()) {
                    $parentTransaction = $this->transactionRepository->get($transaction->getParentId());
                    if ($parentTransaction) {
                        $txtType = $parentTransaction->getTxnType();
                    }
                }
            }

            if ($txtType == TransactionInterface::TYPE_REFUND && $this->isOfflineRefund($transaction->getTxnId())) {
                return;
            }

            if ($txtType == 'authorization' && $this->isKlarnaPayment($paymentMethod)) {
                $transaction->setData(
                    'html_txn_id',
                    sprintf(
                        '<a href="https://plaza.buckaroo.nl/Transaction/DataRequest/Details/%s" target="_blank">%s</a>',
                        $txnId,
                        $transaction->getTxnId()
                    )
                );
                return;
            }

            $transaction->setData(
                'html_txn_id',
                sprintf(
                    '<a href="https://plaza.buckaroo.nl/Transaction/Transactions/Details?transactionKey=%s" target="_blank">%s</a>',
                    $txnId,
                    $transaction->getTxnId()
                )
            );
        }
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
