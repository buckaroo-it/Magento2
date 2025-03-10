<?php
/**
 * NOTICE OF LICENSE
 *
 * This source file is subject to the MIT License
 * It is available through the world-wide-web at this URL:
 * https://tldrlegal.com/license/mit-license
 * If you are unable to obtain it through the world-wide-web, please send an email
 * to support@buckaroo.nl so we can send you a copy immediately.
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

namespace Buckaroo\Magento2\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Sales\Api\Data\OrderPaymentInterface;
use Magento\Sales\Api\Data\TransactionInterface;
use Magento\Sales\Api\TransactionRepositoryInterface;
use Magento\Sales\Model\Order\Payment\Transaction;

class HtmlTransactionIdObserver implements ObserverInterface
{
    /**
     * @var TransactionRepositoryInterface
     */
    private $transactionRepository;

    /**
     * Example constructor injection if you want your own logger:
     * (If you already have a logger property, just reuse that.)
     */
    public function __construct(
        TransactionRepositoryInterface $transactionRepository
    ) {
        $this->transactionRepository = $transactionRepository;
    }
    /**
     * Update txn_id to a link for the plaza transaction
     *
     * @param Observer $observer
     * @return void
     */
    public function execute(Observer $observer)
    {
        /** @var Transaction $transaction */
        $transaction = $observer->getDataObject();
        $order = $transaction->getOrder();

        $txnIdArray = explode("-", $transaction->getTxnId());
        $txnId = reset($txnIdArray);

        if ($this->isBuckarooPayment($order->getPayment()) && $txnId !== false) {
            $txtType = $transaction->getTxnType();
            if ($transaction->getTxnType() === TransactionInterface::TYPE_VOID) {
                if ($transaction->getParentId()) {
                    $parentTransaction = $this->transactionRepository->get($transaction->getParentId());
                    if ($parentTransaction) {
                        $txtType = $parentTransaction->getTxnType();
                    }
                }
            }

            if($txtType == 'authorization'){
                $transaction->setData('html_txn_id',
                    sprintf(
                        '<a href="https://plaza.buckaroo.nl/Transaction/DataRequest/Details/%s" target="_blank">%s</a>',
                        $txnId,
                        $transaction->getTxnId()
                    )
                );
                return;
            }
            $transaction->setData('html_txn_id',
                sprintf(
                    '<a href="https://plaza.buckaroo.nl/Transaction/Transactions/Details?transactionKey=%s" target="_blank">%s</a>',
                    $txnId,
                    $transaction->getTxnId()
                )
            );
        }
    }

    /**
     * Is one of our payment methods
     *
     * @param OrderPaymentInterface|null $payment
     *
     * @return boolean
     */
    public function isBuckarooPayment($payment)
    {
        if (!$payment instanceof OrderPaymentInterface) {
            return false;
        }
        return strpos($payment->getMethod(), 'buckaroo_magento2') !== false;
    }
}
