<?php
/**
 * Buckaroo Magento 2 payment module (https://www.buckaroo.eu/)
 *
 * Copyright (c) Buckaroo B.V.
 * See LICENSE for license details.
 *
 * Support: support@buckaroo.nl
 *
 * @copyright Copyright (c) Buckaroo B.V.
 * @license   MIT
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

        return $this->refundPush->receiveRefundPush($this->pushRequest, true, $this->order);
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
