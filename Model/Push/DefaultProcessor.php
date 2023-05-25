<?php

namespace Buckaroo\Magento2\Model\Push;

use Buckaroo\Magento2\Api\PushProcessorInterface;
use Buckaroo\Magento2\Api\PushRequestInterface;
use Buckaroo\Magento2\Logging\Log;
use Magento\Sales\Api\Data\TransactionInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Payment as OrderPayment;
use Magento\Sales\Model\Order\Payment\Transaction;

class DefaultProcessor implements PushProcessorInterface
{
    /**
     * @var PushRequestInterface
     */
    public PushRequestInterface $pushRequest;

    /**
     * @var Log $logging
     */
    public Log $logging;

    /**
     * @var Order|OrderPayment $order
     */
    public $order;

    /**
     * @var Transaction
     */
    private $transaction;

    public function __construct(
        Log $logging,
        TransactionInterface $transaction
    ) {
        $this->logging = $logging;
        $this->transaction = $transaction;
    }

    public function processSucceded()
    {
        // TODO: Implement processSucceded() method.
    }

    public function processFailed()
    {
        // TODO: Implement processFailed() method.
    }

    public function processPush(PushRequestInterface $pushRequest): void
    {
        $this->pushRequest = $pushRequest;

        // Load order by transaction id
        $this->loadOrder();

        // Validate Signature
        $store = $this->order?->getStore();
        //Check if the push can be processed and if the order can be updated IMPORTANT => use the original post data.
        $validSignature = $this->pushRequest->validate($store);

    }

    /**
     * Load the order from the Push Data based on the Order Increment ID or transaction key.
     *
     * @return void
     * @throws \Exception
     */
    protected function loadOrder(): void
    {
        $brqOrderId = $this->getOrderIncrementId();

        $this->order->loadByIncrementId((string)$brqOrderId);

        if (!$this->order->getId()) {
            $this->logging->addDebug('Order could not be loaded by Invoice Number or Order Number');
            // Try to get order by transaction id on payment.
            $this->order = $this->getOrderByTransactionKey();
        }
    }

    /**
     * Get the order increment ID based on the invoice number or order number.
     *
     * @return string|null
     */
    protected function getOrderIncrementId(): ?string
    {
        $brqOrderId = null;

        if (!empty($this->pushRequest->getInvoiceNumber()) && strlen($this->pushRequest->getInvoiceNumber()) > 0) {
            $brqOrderId = $this->pushRequest->getInvoiceNumber();
        }

        if (!empty($this->pushRequest->getOrderNumber()) && strlen($this->pushRequest->getOrderNumber()) > 0) {
            $brqOrderId = $this->pushRequest->getOrderNumber();
        }

        return $brqOrderId;
    }

    /**
     * Sometimes the push does not contain the order id, when that's the case try to get the order by his payment,
     * by using its own transaction key.
     *
     * @return OrderPayment
     * @throws \Exception
     */
    protected function getOrderByTransactionKey(): OrderPayment
    {
        $trxId = $this->getTransactionKey();

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
     * @return string
     */
    protected function getTransactionKey(): string
    {
        $trxId = '';

        if (!empty($this->pushRequest->getTransactions())) {
            $trxId = $this->pushRequest->getTransactions();
        }

        if (!empty($this->pushRequest->getDatarequest())) {
            $trxId = $this->pushRequest->getDatarequest();
        }

        if (!empty($this->pushRequest->getRelatedtransactionRefund())) {
            $trxId = $this->pushRequest->getRelatedtransactionRefund();
        }

        return $trxId;
    }
}