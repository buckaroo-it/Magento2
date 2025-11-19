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

namespace Buckaroo\Magento2\Service\Push;

use Buckaroo\Magento2\Exception as BuckarooException;
use Buckaroo\Magento2\Api\Data\PushRequestInterface;
use Buckaroo\Magento2\Logging\BuckarooLoggerInterface;
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
     * @var BuckarooLoggerInterface $logger
     */
    public $logger;

    /**
     * @var Transaction
     */
    private $transaction;

    /**
     * @var OrderSender
     */
    private $orderSender;

    /**
     * @var InvoiceSender
     */
    private $invoiceSender;

    /**
     * @var ResourceConnection
     */
    protected $resourceConnection;

    /**
     * @param Order                   $order
     * @param BuckarooLoggerInterface $logger
     * @param TransactionInterface    $transaction
     * @param OrderSender             $orderSender
     * @param InvoiceSender           $invoiceSender
     * @param ResourceConnection      $resourceConnection
     */
    public function __construct(
        Order $order,
        BuckarooLoggerInterface $logger,
        TransactionInterface $transaction,
        OrderSender $orderSender,
        InvoiceSender $invoiceSender,
        ResourceConnection $resourceConnection
    ) {
        $this->order = $order;
        $this->logger = $logger;
        $this->transaction = $transaction;
        $this->orderSender = $orderSender;
        $this->invoiceSender = $invoiceSender;
        $this->resourceConnection = $resourceConnection;
    }

    /**
     * Load the order from the Push Data based on the Order Increment ID or transaction key.
     *
     * @param PushRequestInterface|null $pushRequest
     *
     * @throws \Exception
     *
     * @return Order|OrderPayment
     */
    public function getOrderByRequest(?PushRequestInterface $pushRequest = null)
    {
        if ($this->order->getId()) {
            return $this->order;
        } else {
            $brqOrderId = $this->getOrderIncrementIdFromRequest($pushRequest);

            $this->order->loadByIncrementId((string)$brqOrderId);

            if (!$this->order->getId()) {
                $this->logger->addDebug(sprintf(
                    '[ORDER] | [Service] | [%s:%s] - Order could not be loaded by Invoice Number or Order Number',
                    __METHOD__,
                    __LINE__
                ));
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
     *
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
     *
     * @throws \Exception
     *
     * @return OrderPayment|Order
     */
    protected function getOrderByTransactionKey($pushRequest)
    {
        $trxId = $this->getTransactionKey($pushRequest);

        $this->transaction->load($trxId, 'txn_id');
        $order = $this->transaction->getOrder();

        if (!$order) {
            throw new BuckarooException(__('There was no order found by transaction Id'));
        }

        return $order;
    }

    /**
     * Retrieves the transaction key from the push request.
     *
     * @param $pushRequest
     *
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
    public function setOrderNotificationNote($message): void
    {
        $note = 'Buckaroo attempted to update this order, but failed: ' . $message;
        try {
            $this->order->addCommentToStatusHistory($note, $this->order->getStatus());
            $this->order->save();
        } catch (\Exception $e) {
            $this->logger->addError(sprintf(
                '[ORDER] | [Service] | [%s:%s] - Set Order Notification Note Failed | [ERROR]: %s',
                __METHOD__,
                __LINE__,
                $e->getLogMessage()
            ));
        }
    }

    /**
     * Updates the order state and add a comment.
     *
     * @param string $orderState
     * @param string $newStatus
     * @param string $description
     * @param bool   $force
     * @param bool   $dontSaveOrderUponSuccessPush
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
        $this->logger->addDebug(sprintf(
            '[ORDER] | [Service] | [%s:%s] - Updates the order state and add a comment | data: %s',
            __METHOD__,
            __LINE__,
            var_export([
                'orderState' => $orderState,
                'newStatus'  => $newStatus,
                'description' => $description
            ], true)
        ));

        // Always set the order state - this is crucial for admin dropdown
        $this->order->setState($orderState);

        if ($this->order->getState() == $orderState || $force) {
            if ($dontSaveOrderUponSuccessPush) {
                $this->order->addCommentToStatusHistory($description)
                    ->setIsCustomerNotified(false)
                    ->setEntityName('invoice')
                    ->setStatus($newStatus)
                    ->save();
            } else {
                $this->order->addCommentToStatusHistory($description, $newStatus);
                $this->order->save(); // Save the order to persist state and status changes
            }
        } else {
            if ($dontSaveOrderUponSuccessPush) {
                $this->order->addCommentToStatusHistory($description)
                    ->setIsCustomerNotified(false)
                    ->setEntityName('invoice')
                    ->save();
            } else {
                $this->order->addCommentToStatusHistory($description);
                $this->order->save(); // Save the order to persist changes
            }
        }

        $this->logger->addDebug(sprintf(
            '[ORDER] | [Service] | [%s:%s] - Order state and status updated successfully | finalState: %s | finalStatus: %s',
            __METHOD__,
            __LINE__,
            $this->order->getState(),
            $this->order->getStatus()
        ));
    }

    /**
     * Sends order email to the customer.
     *
     * @param Order $order
     * @param bool  $forceSyncMode
     *
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
     * @param bool    $forceSyncMode
     *
     * @throws \Exception
     *
     * @return bool
     */
    public function sendInvoiceEmail(Invoice $invoice, bool $forceSyncMode = false): bool
    {
        return $this->invoiceSender->send($invoice, $forceSyncMode);
    }

    public function updateTotalOnOrder($order)
    {

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
     * @throws \Exception
     */
    public function loadOrder()
    {
        $brqOrderId = $this->getOrderByRequest()->getIncrementId();
        $this->order->loadByIncrementId((string)$brqOrderId);
    }
}
