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
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Exception\LocalizedException;
use Magento\Sales\Api\Data\TransactionInterface;
use Magento\Sales\Api\InvoiceRepositoryInterface;
use Magento\Sales\Api\OrderPaymentRepositoryInterface;
use Magento\Sales\Api\OrderRepositoryInterface;

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
     * @param OrderRepositoryInterface|null $orderRepository
     * @param OrderPaymentRepositoryInterface|null $paymentRepository
     * @param InvoiceRepositoryInterface|null $invoiceRepository
     * @param GroupTransaction|null $groupTransactionResource
     * @param \Magento\Sales\Api\TransactionRepositoryInterface|null $transactionRepository
     * @param \Magento\Framework\Api\SearchCriteriaBuilder|null $searchCriteriaBuilder
     * @param \Magento\Sales\Api\OrderManagementInterface|null $orderManagement
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
        Account                                                  $configAccount,
        GiftCardRefundService                                    $giftCardRefundService,
        Uncancel                                                 $uncancelService,
        ResourceConnection                                       $resourceConnection,
        GiftcardCollection                                       $giftcardCollection,
        PaypalConfig                                             $paypalConfig,
        ?OrderRepositoryInterface                                $orderRepository = null,
        ?OrderPaymentRepositoryInterface                         $paymentRepository = null,
        ?InvoiceRepositoryInterface                              $invoiceRepository = null,
        ?GroupTransaction $groupTransactionResource = null,
        ?\Magento\Sales\Api\TransactionRepositoryInterface $transactionRepository = null,
        ?\Magento\Framework\Api\SearchCriteriaBuilder $searchCriteriaBuilder = null,
        ?\Magento\Sales\Api\OrderManagementInterface $orderManagement = null
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
            null,
            $orderRepository,
            $paymentRepository,
            $invoiceRepository,
            $groupTransactionResource,
            $transactionRepository,
            $searchCriteriaBuilder,
            $orderManagement
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
