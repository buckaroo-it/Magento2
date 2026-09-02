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

use Buckaroo\Magento2\Exception as BuckarooException;
use Buckaroo\Magento2\Helper\Data;
use Buckaroo\Magento2\Helper\PaymentGroupTransaction;
use Buckaroo\Magento2\Logging\BuckarooLoggerInterface;
use Buckaroo\Magento2\Model\BuckarooStatusCode;
use Buckaroo\Magento2\Model\ConfigProvider\Account;
use Buckaroo\Magento2\Model\ConfigProvider\Method\Paypal as PaypalConfig;
use Buckaroo\Magento2\Model\OrderStatusFactory;
use Buckaroo\Magento2\Model\ResourceModel\Giftcard\Collection as GiftcardCollection;
use Buckaroo\Magento2\Model\ResourceModel\GroupTransaction;
use Buckaroo\Magento2\Model\Service\GiftCardRefundService;
use Buckaroo\Magento2\Service\Order\Uncancel;
use Buckaroo\Magento2\Service\Push\OrderRequestService;
use Magento\Directory\Model\CurrencyFactory;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Escaper;
use Magento\Framework\Exception\LocalizedException;
use Magento\Sales\Api\Data\TransactionInterface;
use Magento\Sales\Api\InvoiceRepositoryInterface;
use Magento\Sales\Api\OrderManagementInterface;
use Magento\Sales\Api\OrderPaymentRepositoryInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Api\TransactionRepositoryInterface;

/**
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class PaypalProcessor extends DefaultProcessor
{
    /**
     * @var PaypalConfig
     */
    private $paypalConfig;

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
     * @param PaypalConfig $paypalConfig
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
        TransactionInterface                                     $transaction,
        PaymentGroupTransaction                                  $groupTransaction,
        BuckarooStatusCode                                       $buckarooStatusCode,
        OrderStatusFactory                                       $orderStatusFactory,
        Account                          $configAccount,
        GiftCardRefundService            $giftCardRefundService,
        Uncancel                         $uncancelService,
        ResourceConnection               $resourceConnection,
        GiftcardCollection               $giftcardCollection,
        PaypalConfig                     $paypalConfig,
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
        $this->paypalConfig = $paypalConfig;
    }

    /**
     * Get the new order status, adjusted for PayPal seller's protection when applicable.
     *
     * @return false|string|null
     * @throws BuckarooException
     * @throws LocalizedException
     */
    protected function getNewStatus()
    {
        $newStatus = $this->orderStatusFactory->get($this->pushRequest->getStatusCode(), $this->order);

        if ($this->pushTransactionType->getStatusKey() == 'BUCKAROO_MAGENTO2_STATUSCODE_SUCCESS'
            && $this->order->getPayment()->getMethod() == PaypalConfig::CODE) {
            $newSellersProtectionStatus = $this->paypalConfig->getSellersProtectionIneligible();
            if ($this->paypalConfig->getSellersProtection() && !empty($newSellersProtectionStatus)) {
                $newStatus = $newSellersProtectionStatus;
            }
        }

        $this->logger->addDebug(sprintf(
            '[PUSH - Paypal] | [Webapi] | [%s:%s] - Get New Status | newStatus: %s',
            __METHOD__,
            __LINE__,
            var_export($newStatus, true)
        ));

        return $newStatus;
    }
}
