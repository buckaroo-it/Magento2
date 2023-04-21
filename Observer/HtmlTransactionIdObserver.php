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
use Magento\Sales\Model\Order\Payment\Transaction;

class HtmlTransactionIdObserver implements ObserverInterface
{
    /**
     * @var CheckPaymentType
     */
    private $checkPaymentType;

    /**
     * @param CheckPaymentType $checkPaymentType
     */
    public function __construct(CheckPaymentType $checkPaymentType)
    {
        $this->checkPaymentType = $checkPaymentType;
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
        if ($this->checkPaymentType->isBuckarooPayment($order->getPayment()) && $txnId !== false) {
            $transaction->setData(
                'html_txn_id',
                sprintf(
                    '<a href="https://plaza.buckaroo.nl/Transaction/Transactions/Details?transactionKey=%s"'
                    . ' target="_blank">%s</a>',
                    $txnId,
                    $transaction->getTxnId()
                )
            );
        }
    }
}
