<?php

namespace Buckaroo\Magento2\Model\Push;

use Buckaroo\Magento2\Api\PushProcessorInterface;
use Buckaroo\Magento2\Api\PushRequestInterface;
use Buckaroo\Magento2\Helper\PaymentGroupTransaction;
use Buckaroo\Magento2\Logging\Log as BuckarooLogger;
use Buckaroo\Magento2\Model\BuckarooStatusCode;
use Buckaroo\Magento2\Service\Push\OrderRequestService;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Model\AbstractExtensibleModel;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\QuoteFactory;
use Magento\Quote\Model\QuoteManagement;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\OrderManagementInterface;
use Magento\Sales\Model\Order;
use Magento\Quote\Model\ResourceModel\Quote as ResourceQuote;

class GroupTransactionPushProcessor implements PushProcessorInterface
{
    /**
     * @var PushRequestInterface
     */
    private PushRequestInterface $pushRequest;

    /**
     * @var BuckarooLogger
     */
    private BuckarooLogger $logging;

    /**
     * @var PaymentGroupTransaction
     */
    private PaymentGroupTransaction $groupTransaction;

    /**
     * @var OrderRequestService
     */
    private OrderRequestService $orderRequestService;

    /**
     * @var Order\Payment|Order
     */
    private Order\Payment|Order $order;

    /**
     * @var OrderManagementInterface
     */
    private OrderManagementInterface $orderManagement;

    /**
     * @var QuoteManagement
     */
    private QuoteManagement $quoteManagement;

    /**
     * @var QuoteFactory
     */
    private QuoteFactory $quoteFactory;

    /**
     * @var ResourceQuote
     */
    private ResourceQuote $quoteResource;

    /**
     * @param PaymentGroupTransaction $groupTransaction
     * @param BuckarooLogger $logging
     * @param OrderRequestService $orderRequestService
     * @param OrderManagementInterface $orderManagement
     * @param QuoteManagement $quoteManagement
     * @param QuoteFactory $quoteFactory
     * @param ResourceQuote $quoteResource
     */
    public function __construct(
        PaymentGroupTransaction $groupTransaction,
        BuckarooLogger $logging,
        OrderRequestService $orderRequestService,
        OrderManagementInterface $orderManagement,
        QuoteManagement $quoteManagement,
        QuoteFactory $quoteFactory,
        ResourceQuote $quoteResource
    ) {
        $this->groupTransaction = $groupTransaction;
        $this->logging = $logging;
        $this->orderRequestService = $orderRequestService;
        $this->orderManagement = $orderManagement;
        $this->quoteManagement = $quoteManagement;
        $this->quoteFactory = $quoteFactory;
        $this->quoteResource = $quoteResource;
    }

    /**
     * @inheritdoc
     *
     * @throws LocalizedException
     * @throws \Exception
     */
    public function processPush(PushRequestInterface $pushRequest): bool
    {
        $this->pushRequest = $pushRequest;
        $this->order = $this->orderRequestService->getOrderByRequest($pushRequest);

        if ($this->isFailedGroupTransaction()) {
            $this->handleGroupTransactionFailed();
            return true;
        }

        if($this->isCanceledGroupTransaction()) {
            $this->cancelGroupTransactionOrder();
            return true;
        }

        // Check if is group transaction info
        if ($this->isGroupTransactionInfo()) {
            return true;
        }

        // Skip Handle group transaction
        if ($this->skipHandlingForFailedGroupTransactions()) {
            return true;
        }

        return false;
    }

    public function processSucceededPush(PushRequestInterface $pushRequest): bool
    {
        // TODO: Implement processSucceededPush() method.
    }

    public function processFailedPush(PushRequestInterface $pushRequest): bool
    {
        // TODO: Implement processFailedPush() method.
    }

    public function processPendingPaymentPush(PushRequestInterface $pushRequest): bool
    {
        // TODO: Implement processPendingPaymentPush() method.
    }

    /**
     * Check if is a failed transaction
     *
     * @return boolean
     */
    protected function isFailedGroupTransaction(): bool
    {
        return $this->pushRequest->hasPostData('statuscode', BuckarooStatusCode::FAILED);
    }

    /**
     * Checks if the group transaction is an info transaction
     *
     * @return bool
     */
    private function isGroupTransactionInfo()
    {
        return $this->pushRequest->getStatusCode() != BuckarooStatusCode::SUCCESS;
    }

    /**
     * Check if the request is a canceled group transaction
     *
     * @return boolean
     */
    public function isCanceledGroupTransaction()
    {
        return $this->pushRequest->hasPostData('brq_statuscode', BuckarooStatusCode::CANCELLED_BY_USER);
    }

    /**
     * Handle push from main group transaction fail
     *
     * @return void
     */
    protected function handleGroupTransactionFailed()
    {
        try {
            $this->cancelOrder($this->pushRequest->getInvoiceNumber());
            $this->groupTransaction->setGroupTransactionsStatus(
                $this->pushRequest->getTransactions(),
                $this->pushRequest->getStatusCode()
            );

            $this->savePartGroupTransaction();
        } catch (\Throwable $th) {
            $this->logging->addDebug(__METHOD__ . '|' . (string)$th);
        }
    }

    /**
     * Cancel order when group transaction is canceled
     *
     * @return void
     * @throws LocalizedException
     */
    public function cancelGroupTransactionOrder(): void
    {
        if (is_string($this->pushRequest->getInvoiceNumber())) {
            $this->cancelOrder(
                $this->pushRequest->getInvoiceNumber(),
                'Inline giftcard order was canceled'
            );
        }
    }

    /**
     * Ship push handling for a failed transaction
     *
     * @return bool
     */
    protected function skipHandlingForFailedGroupTransactions(): bool
    {
        return
            $this->order->getId() !== null &&
            $this->order->getState() == Order::STATE_CANCELED &&
            $this->pushRequest->hasPostData('transaction_type', ['V202','V203', 'V204']);
    }

    /**
     * Cancel order for failed group transaction
     *
     * @param string $reservedOrderId
     * @param string $historyComment
     * @return void
     * @throws LocalizedException
     */
    protected function cancelOrder(string $reservedOrderId, string $historyComment = 'Giftcard has expired'): void
    {
        $order = $this->order->loadByIncrementId($reservedOrderId);

        if ($order->getEntityId() === null) {
            $order = $this->createOrderFromQuote($reservedOrderId);
        }

        if ($order instanceof OrderInterface &&
            $order->getEntityId() !== null &&
            $order->getState() !== Order::STATE_CANCELED
        ) {
            $this->orderManagement->cancel($order->getEntityId());

            $order->addCommentToStatusHistory(__($historyComment))
                ->setIsCustomerNotified(false)
                ->setEntityName('invoice')
                ->save();
        }
    }

    /**
     * Create order from quote
     *
     * @param string $reservedOrderId
     * @return AbstractExtensibleModel|OrderInterface|object|null
     * @throws \Exception
     * @throws LocalizedException
     */
    protected function createOrderFromQuote(string $reservedOrderId)
    {
        $quote = $this->getQuoteByReservedOrderId($reservedOrderId);
        if (!$quote instanceof Quote) {
            return null;
        }

        // fix missing email validation
        if ($quote->getCustomerEmail() == null) {
            $quote->setCustomerEmail(
                $quote->getBillingAddress()->getEmail()
            );
        }

        $order = $this->quoteManagement->submit($quote);

        // keep the quote active but remove the canceled order from it
        $quote->setIsActive(true);
        $quote->setOrigOrderId(0);
        $quote->setReservedOrderId(null);
        $quote->save();
        return $order;
    }

    /**
     * Get quote by increment/reserved order id
     *
     * @param string $reservedOrderId
     * @return Quote|null
     */
    protected function getQuoteByReservedOrderId(string $reservedOrderId): ?Quote
    {
        $quote = $this->quoteFactory->create();

        $this->quoteResource->load($quote, $reservedOrderId, 'reserved_order_id');
        if (!$quote->isEmpty()) {
            return $quote;
        }

        return null;
    }

    /**
     * Save the part group transaction.
     *
     * @return void
     * @throws \Exception
     */
    private function savePartGroupTransaction()
    {
        $items = $this->groupTransaction->getGroupTransactionByTrxId($this->pushRequest->getTransactions());
        if (is_array($items) && count($items) > 0) {
            foreach ($items as $item) {
                $item2['status'] = $this->pushRequest->getStatusCode();
                $item2['entity_id'] = $item['entity_id'];
                $this->groupTransaction->updateGroupTransaction($item2);
            }
        }
    }
}