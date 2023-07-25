<?php

namespace Buckaroo\Magento2\Service\Push;

use Buckaroo\Magento2\Api\PushRequestInterface;
use Buckaroo\Magento2\Logging\Log;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Phrase;
use Magento\Sales\Api\Data\TransactionInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Email\Sender\InvoiceSender;
use Magento\Sales\Model\Order\Email\Sender\OrderSender;
use Magento\Sales\Model\Order\Invoice;
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
     * @var OrderSender
     */
    private OrderSender $orderSender;

    /**
     * @var InvoiceSender
     */
    private InvoiceSender $invoiceSender;

    /**
     * @var ResourceConnection
     */
    protected ResourceConnection $resourceConnection;

    /**
     * @param Order $order
     * @param Log $logging
     * @param TransactionInterface $transaction
     * @param OrderSender $orderSender
     * @param InvoiceSender $invoiceSender
     * @param ResourceConnection $resourceConnection
     */
    public function __construct(
        Order $order,
        Log $logging,
        TransactionInterface $transaction,
        OrderSender $orderSender,
        InvoiceSender $invoiceSender,
        ResourceConnection $resourceConnection
    ) {
        $this->order = $order;
        $this->logging = $logging;
        $this->transaction = $transaction;
        $this->orderSender = $orderSender;
        $this->invoiceSender = $invoiceSender;
        $this->resourceConnection = $resourceConnection;
    }

    /**
     * Load the order from the Push Data based on the Order Increment ID or transaction key.
     *
     * @param PushRequestInterface|null $pushRequest
     * @return Order|OrderPayment
     * @throws \Exception
     */
    public function getOrderByRequest(?PushRequestInterface $pushRequest = null): Order|OrderPayment
    {
        if ($this->order->getId()) {
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

        $brqOrderId = str_replace('LT', '', $brqOrderId);

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

    /**
     * Try to add a notification note to the order comments.
     *
     * @param Phrase|string $message
     */
    public function setOrderNotificationNote(Phrase|string $message): void
    {
        $note = 'Buckaroo attempted to update this order, but failed: ' . $message;
        try {
            $this->order->addStatusToHistory($note);
            $this->order->save();
        } catch (\Exception $e) {
            $this->logging->addDebug($e->getLogMessage());
        }
    }

    /**
     * Updates the order state and add a comment.
     *
     * @param string $orderState
     * @param string $newStatus
     * @param string $description
     * @param bool $force
     * @param bool $dontSaveOrderUponSuccessPush
     *
     * @throws \Exception
     */
    public function updateOrderStatus(
        string $orderState,
        string $newStatus,
        string $description,
        bool $force = false,
        bool $dontSaveOrderUponSuccessPush = false
    ): void {
        $this->logging->addDebug(__METHOD__ . '|0|' . var_export([$orderState, $newStatus, $description], true));
        if ($this->order->getState() == $orderState || $force) {
            $this->logging->addDebug(__METHOD__ . '|1|');
            $this->logging->addDebug('||| $orderState: ' . '|1|' . $orderState);
            if ($dontSaveOrderUponSuccessPush) {
                $this->order->addCommentToStatusHistory($description)
                    ->setIsCustomerNotified(false)
                    ->setEntityName('invoice')
                    ->setStatus($newStatus)
                    ->save();
            } else {
                $this->order->addCommentToStatusHistory($description, $newStatus);
            }
        } else {
            $this->logging->addDebug(__METHOD__ . '|2|');
            $this->logging->addDebug('||| $orderState: ' . '|2|' . $orderState);
            if ($dontSaveOrderUponSuccessPush) {
                $this->order->addCommentToStatusHistory($description)
                    ->setIsCustomerNotified(false)
                    ->setEntityName('invoice')
                    ->save();
            } else {
                $this->order->addCommentToStatusHistory($description);
            }
        }
    }

    /**
     * Sends order email to the customer.
     *
     * @param Order $order
     * @param bool $forceSyncMode
     * @return bool
     */
    public function sendOrderEmail(Order $order, bool $forceSyncMode = false): bool
    {
        return $this->orderSender->send($order, $forceSyncMode);
    }

    /**
     * Sends order invoice email to the customer.
     *
     * @param Invoice $invoice
     * @param bool $forceSyncMode
     * @return bool
     *
     * @throws \Exception
     */
    public function sendInvoiceEmail(Invoice $invoice, bool $forceSyncMode = false): bool
    {
        return $this->invoiceSender->send($invoice, $forceSyncMode);
    }

    public function updateTotalOnOrder($order) {

        try {
            $connection = $this->resourceConnection->getConnection();
            $connection->update(
                $connection->getTableName('sales_order'),
                [
                    'total_due'       => $order->getTotalDue(),
                    'base_total_due'  => $order->getTotalDue(),
                    'total_paid'      => $order->getTotalPaid(),
                    'base_total_paid' => $order->getBaseTotalPaid(),
                ],
                $connection->quoteInto('entity_id = ?', $order->getId())
            );

            return true;
        } catch (\Exception $exception) {
            return false;
        }

    }

    /**
     * Save the current order and reload it from the database.
     *
     * @return void
     * @throws \Exception
     */
    public function saveAndReloadOrder()
    {
        $this->order->save();
        $this->loadOrder();
    }

    /**
     * Load the order from the Push Data based on the Order Increment ID or transaction key.
     *
     * @return void
     * @throws \Exception
     */
    private function loadOrder()
    {
        $brqOrderId = $this->getOrderIncrementId();

        //Check if the order can receive further status updates
        $this->order->loadByIncrementId((string)$brqOrderId);

        if (!$this->order->getId()) {
            $this->logging->addDebug('Order could not be loaded by Invoice Number or Order Number');
            // try to get order by transaction id on payment.
            $this->order = $this->getOrderByTransactionKey();
        }
    }
}