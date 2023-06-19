<?php

namespace Buckaroo\Magento2\Service\Push;

use Buckaroo\Magento2\Logging\Log;
use Magento\Sales\Api\Data\TransactionInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Payment as OrderPayment;
use Magento\Sales\Model\Order\Payment\Transaction;

class OrderRequestService
{
    /**
     * @var Order|OrderPayment $order
     */
    public $order = null;

    /**
     * @var Log $logging
     */
    public Log $logging;

    /**
     * @var Transaction
     */
    private TransactionInterface $transaction;

    /**
     * @param Log $logging
     * @param TransactionInterface $transaction
     */
    public function __construct(
        Log $logging,
        TransactionInterface $transaction
    ) {
        $this->logging = $logging;
        $this->transaction = $transaction;
    }

    /**
     * Load the order from the Push Data based on the Order Increment ID or transaction key.
     *
     * @param $pushRequest
     * @return Order|OrderPayment
     * @throws \Exception
     */
    public function getOrderByRequest($pushRequest): Order|OrderPayment
    {
        if ($this->order) {
            return $this->order;
        } else {
            $brqOrderId = $this->getOrderIncrementIdFromRequest($pushRequest);

            $this->order->loadByIncrementId((string)$brqOrderId);

            if (!$this->order->getId()) {
                $this->logging->addDebug('Order could not be loaded by Invoice Number or Order Number');
                // Try to get order by transaction id on payment.
                $this->order = $this->getOrderByTransactionKey($pushRequest);
            }
        }

        return $this->order;
    }

    /**
     * Get the order increment ID based on the invoice number or order number.
     *
     * @param $pushRequest
     * @return string|null
     */
    protected function getOrderIncrementIdFromRequest($pushRequest): ?string
    {
        $brqOrderId = null;

        if (!empty($pushRequest->getInvoiceNumber()) && strlen($pushRequest->getInvoiceNumber()) > 0) {
            $brqOrderId = $pushRequest->getInvoiceNumber();
        }

        if (!empty($pushRequest->getOrderNumber()) && strlen($pushRequest->getOrderNumber()) > 0) {
            $brqOrderId = $pushRequest->getOrderNumber();
        }

        return $brqOrderId;
    }

    /**
     * Sometimes the push does not contain the order id, when that's the case try to get the order by his payment,
     * by using its own transaction key.
     *
     * @param $pushRequest
     * @return OrderPayment
     * @throws \Exception
     */
    protected function getOrderByTransactionKey($pushRequest): OrderPayment
    {
        $trxId = $this->getTransactionKey($pushRequest);

        $this->transaction->load($trxId, 'txn_id');
        $order = $this->transaction->getOrder();

        if (!$order) {
            throw new \Exception(__('There was no order found by transaction Id'));
        }

        return $order;
    }

    /**
     * Retrieves the transaction key from the push request.
     *
     * @param $pushRequest
     * @return string
     */
    protected function getTransactionKey($pushRequest): string
    {
        $trxId = '';

        if (!empty($pushRequest->getTransactions())) {
            $trxId = $pushRequest->getTransactions();
        }

        if (!empty($pushRequest->getDatarequest())) {
            $trxId = $pushRequest->getDatarequest();
        }

        if (!empty($pushRequest->getRelatedtransactionRefund())) {
            $trxId = $pushRequest->getRelatedtransactionRefund();
        }

        return $trxId;
    }
}