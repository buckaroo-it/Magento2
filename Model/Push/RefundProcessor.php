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

namespace Buckaroo\Magento2\Model\Push;

use Buckaroo\Magento2\Api\Data\PushRequestInterface;
use Buckaroo\Magento2\Exception as BuckarooException;
use Buckaroo\Magento2\Helper\Data;
use Buckaroo\Magento2\Helper\PaymentGroupTransaction;
use Buckaroo\Magento2\Logging\BuckarooLoggerInterface;
use Buckaroo\Magento2\Model\BuckarooStatusCode;
use Buckaroo\Magento2\Model\ConfigProvider\Account;
use Buckaroo\Magento2\Model\Method\BuckarooAdapter;
use Buckaroo\Magento2\Model\OrderStatusFactory;
use Buckaroo\Magento2\Model\Refund\Push as RefundPush;
use Buckaroo\Magento2\Model\ResourceModel\Giftcard\Collection as GiftcardCollection;
use Buckaroo\Magento2\Model\ResourceModel\GroupTransaction;
use Buckaroo\Magento2\Model\Service\GiftCardRefundService;
use Buckaroo\Magento2\Service\Order\Uncancel;
use Buckaroo\Magento2\Service\Push\OrderRequestService;
use Exception;
use Magento\Directory\Model\CurrencyFactory;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Escaper;
use Magento\Sales\Api\Data\TransactionInterface;
use Magento\Sales\Api\InvoiceRepositoryInterface;
use Magento\Sales\Api\OrderManagementInterface;
use Magento\Sales\Api\OrderPaymentRepositoryInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Api\TransactionRepositoryInterface;

/**
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class RefundProcessor extends DefaultProcessor
{
    /**
     * @var RefundPush
     */
    private $refundPush;

    /**
     * @param OrderRequestService $orderRequestService
     * @param PushTransactionType $pushTransactionType
     * @param BuckarooLoggerInterface $logger
     * @param Data $helper
     * @param TransactionInterface $transaction
     * @param PaymentGroupTransaction $groupTransaction
     * @param BuckarooStatusCode $buckarooStatusCode
     * @param OrderStatusFactory $orderStatusFactory
     * @param Account $configAccount
     * @param GiftCardRefundService $giftCardRefundService
     * @param Uncancel $uncancelService
     * @param ResourceConnection $resourceConnection
     * @param GiftcardCollection $giftcardCollection
     * @param RefundPush $refundPush
     * @param CurrencyFactory $currencyFactory
     * @param OrderRepositoryInterface $orderRepository
     * @param OrderPaymentRepositoryInterface $paymentRepository
     * @param InvoiceRepositoryInterface $invoiceRepository
     * @param GroupTransaction $groupTransactionResource
     * @param TransactionRepositoryInterface $transactionRepository
     * @param SearchCriteriaBuilder $searchCriteriaBuilder
     * @param OrderManagementInterface $orderManagement
     * @param Escaper $escaper
     * @SuppressWarnings(PHPMD.ExcessiveParameterList)
     */
    public function __construct(
        OrderRequestService $orderRequestService,
        PushTransactionType $pushTransactionType,
        BuckarooLoggerInterface $logger,
        Data $helper,
        TransactionInterface             $transaction,
        PaymentGroupTransaction          $groupTransaction,
        BuckarooStatusCode               $buckarooStatusCode,
        OrderStatusFactory               $orderStatusFactory,
        Account                          $configAccount,
        GiftCardRefundService            $giftCardRefundService,
        Uncancel                         $uncancelService,
        ResourceConnection               $resourceConnection,
        GiftcardCollection               $giftcardCollection,
        RefundPush                       $refundPush,
        CurrencyFactory $currencyFactory,
        OrderRepositoryInterface        $orderRepository,
        OrderPaymentRepositoryInterface $paymentRepository,
        InvoiceRepositoryInterface      $invoiceRepository,
        GroupTransaction                $groupTransactionResource,
        TransactionRepositoryInterface  $transactionRepository,
        SearchCriteriaBuilder           $searchCriteriaBuilder,
        OrderManagementInterface        $orderManagement,
        Escaper                         $escaper
    ) {
        parent::__construct(
            $orderRequestService,
            $pushTransactionType,
            $logger,
            $helper,
            $transaction,
            $groupTransaction,
            $buckarooStatusCode,
            $orderStatusFactory,
            $configAccount,
            $giftCardRefundService,
            $uncancelService,
            $resourceConnection,
            $giftcardCollection,
            $currencyFactory,
            $orderRepository,
            $paymentRepository,
            $invoiceRepository,
            $groupTransactionResource,
            $transactionRepository,
            $searchCriteriaBuilder,
            $orderManagement,
            $escaper
        );
        $this->refundPush = $refundPush;
    }

    /**
     * Process a refund push request received from Buckaroo.
     *
     * @param PushRequestInterface $pushRequest
     *
     * @return bool
     * @throws BuckarooException
     */
    public function processPush(PushRequestInterface $pushRequest): bool
    {
        $this->pushRequest = $pushRequest;

        $this->orderRequestService->loadOrder();

        $this->order = $this->orderRequestService->getOrderByRequest($pushRequest);

        $this->payment = $this->order->getPayment();

        if ($this->skipPendingRefundPush($pushRequest)) {
            return true;
        }

        if ($this->pushTransactionType->getStatusKey() !== 'BUCKAROO_MAGENTO2_STATUSCODE_SUCCESS') {
            if ($this->order->hasInvoices()) {
                //don't proceed failed refund push if order has invoices
                $this->orderRequestService->setOrderNotificationNote(
                    __('Push notification for refund has no success status, ignoring.')
                );
                return true;
            } else {
                throw new BuckarooException(
                    __(
                        'Refund failed ! Status : %1 and the order does not contain an invoice',
                        $this->pushTransactionType->getStatusKey()
                    )
                );
            }
        }

        if ($this->isRefundOfUnpaidPartialPayment()) {
            return $this->handleRefundOfUnpaidPartialPayment();
        }

        return $this->refundPush->receiveRefundPush($this->pushRequest, true, $this->order);
    }

    /**
     * Whether this refund returns one part of a split payment on an order that was never paid.
     *
     * A customer who pays part of an order with a gift card and then leaves the payment page
     * gets that part refunded. The order has no invoice and nothing paid, so there is nothing to
     * credit: the refund only undoes the part.
     *
     * @return bool
     */
    private function isRefundOfUnpaidPartialPayment(): bool
    {
        $refundedTransaction = (string)$this->pushRequest->getRelatedtransactionRefund();

        if ($refundedTransaction === ''
            || $this->order->hasInvoices()
            || round((float)$this->order->getTotalPaid(), 2) > 0
        ) {
            return false;
        }

        $part = $this->groupTransaction->getGroupTransactionByTrxId($refundedTransaction);

        return $part !== null
            && $part->getId()
            && (string)$part->getData('order_id') === (string)$this->order->getIncrementId();
    }

    /**
     * Record the refunded part and cancel the order once nothing paid is left.
     *
     * @throws Exception
     *
     * @return bool
     */
    private function handleRefundOfUnpaidPartialPayment(): bool
    {
        $refundedTransaction = (string)$this->pushRequest->getRelatedtransactionRefund();
        $part = $this->groupTransaction->getGroupTransactionByTrxId($refundedTransaction);
        $refundedAmount = (float)$this->pushRequest->getAmountCredit();

        $partAmount = (float)$part->getData('amount');
        $alreadyRefunded = (float)$part->getData('refunded_amount') >= $partAmount;

        // A repeated push: already recorded and the order already cancelled.
        if ($alreadyRefunded && $this->order->getState() === \Magento\Sales\Model\Order::STATE_CANCELED) {
            return true;
        }

        // The refund response may already have counted it (gift card removed from the checkout).
        $this->groupTransaction->updateGroupTransaction([
            'entity_id' => $part->getId(),
            'refunded_amount' => min($partAmount, (float)$part->getData('refunded_amount') + $refundedAmount),
        ]);

        $stillPaid = round(
            (float)$this->groupTransaction->getGroupTransactionAmount($this->order->getIncrementId()),
            2
        );
        $refundedText = $this->formatCommentAmount($refundedAmount);

        $this->logger->addDebug(sprintf(
            '[PUSH_REFUND] | [%s:%s] - Order %s: partial payment %s refunded before the order was paid'
            . ' (%s) | still paid: %s',
            __METHOD__,
            __LINE__,
            $this->order->getIncrementId(),
            $refundedTransaction,
            $refundedAmount,
            $stillPaid
        ));

        if ($stillPaid > 0 || !$this->order->canCancel()) {
            $this->order->addCommentToStatusHistory((string)__(
                'Buckaroo: the partial payment of %1 (transaction %2) was refunded.',
                $refundedText,
                $refundedTransaction
            ));
            $this->orderRepository->save($this->order);

            return true;
        }

        $this->cancelWithoutGatewayCall();

        // The cancel saved its own copy of the order; continue on a fresh one.
        $order = $this->orderRepository->get((int)$this->order->getId());
        if (!$order instanceof \Magento\Sales\Model\Order) {
            return true;
        }
        $order->addCommentToStatusHistory((string)__(
            'Buckaroo: the partial payment of %1 (transaction %2) was refunded before the order was paid.'
            . ' The order is cancelled.',
            $refundedText,
            $refundedTransaction
        ));
        $this->orderRepository->save($order);

        return true;
    }

    /**
     * Cancel the order; the part was refunded already, so there is nothing to void at Buckaroo.
     *
     * @throws Exception
     *
     * @return void
     */
    private function cancelWithoutGatewayCall(): void
    {
        $originalRequestOnVoid = BuckarooAdapter::$requestOnVoid;
        BuckarooAdapter::$requestOnVoid = false;

        try {
            $this->orderManagement->cancel((int)$this->order->getId());
        } finally {
            BuckarooAdapter::$requestOnVoid = $originalRequestOnVoid;
        }
    }

    /**
     * Skip Pending Refund Push
     *
     * @param PushRequestInterface $pushRequest
     *
     * @throws Exception
     *
     * @return bool
     */
    private function skipPendingRefundPush(PushRequestInterface $pushRequest): bool
    {
        if (!$pushRequest->hasAdditionalInformation('initiated_by_magento', 1)
            || !$pushRequest->hasAdditionalInformation('service_action_from_magento', ['refund'])
        ) {
            return false;
        }

        // Skip Refund Pending Approval with status Pending Approval
        if ((int)$pushRequest->getStatusCode() === BuckarooStatusCode::PENDING_APPROVAL) {
            return true;
        }

        if ((int)$pushRequest->getStatusCode() === BuckarooStatusCode::SUCCESS
            && !empty($pushRequest->getRelatedtransactionRefund())) {
            if ($this->receivePushCheckDuplicates(
                BuckarooStatusCode::PENDING_APPROVAL,
                $pushRequest->getRelatedtransactionRefund()
            )) {
                return true;
            } else {
                return false;
            }
        }

        return true;
    }
}
