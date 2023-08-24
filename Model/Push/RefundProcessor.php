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

use Buckaroo\Magento2\Api\PushRequestInterface;
use Buckaroo\Magento2\Exception as BuckarooException;
use Buckaroo\Magento2\Helper\Data;
use Buckaroo\Magento2\Helper\PaymentGroupTransaction;
use Buckaroo\Magento2\Logging\BuckarooLoggerInterface;
use Buckaroo\Magento2\Model\BuckarooStatusCode;
use Buckaroo\Magento2\Model\ConfigProvider\Account;
use Buckaroo\Magento2\Model\OrderStatusFactory;
use Buckaroo\Magento2\Model\Refund\Push as RefundPush;
use Buckaroo\Magento2\Service\Push\OrderRequestService;
use Magento\Sales\Api\Data\TransactionInterface;

/**
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class RefundProcessor extends DefaultProcessor
{
    /**
     * @var RefundPush
     */
    private RefundPush $refundPush;

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
     * @param RefundPush $refundPush
     *
     * @SuppressWarnings(PHPMD.ExcessiveParameterList)
     */
    public function __construct(
        OrderRequestService $orderRequestService,
        PushTransactionType $pushTransactionType,
        BuckarooLoggerInterface $logger,
        Data $helper,
        TransactionInterface $transaction,
        PaymentGroupTransaction $groupTransaction,
        BuckarooStatusCode $buckarooStatusCode,
        OrderStatusFactory $orderStatusFactory,
        Account $configAccount,
        RefundPush $refundPush
    ) {
        parent::__construct($orderRequestService, $pushTransactionType, $logger, $helper, $transaction,
            $groupTransaction, $buckarooStatusCode,$orderStatusFactory, $configAccount);
        $this->refundPush = $refundPush;
    }

    /**
     * @throws BuckarooException
     * @throws \Exception
     */
    public function processPush(PushRequestInterface $pushRequest): bool
    {
        $this->pushRequest = $pushRequest;
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
                    __('Refund failed ! Status : %1 and the order does not contain an invoice',
                        $this->pushTransactionType->getStatusKey())
                );
            }
        }

        return $this->refundPush->receiveRefundPush($this->pushRequest, true, $this->order);
    }

    /**
     * Skip Pending Refund Push
     *
     * @param PushRequestInterface $pushRequest
     * @return bool
     * @throws \Exception
     */
    private function skipPendingRefundPush(PushRequestInterface $pushRequest): bool
    {
        if (!$pushRequest->hasAdditionalInformation('initiated_by_magento', 1)
            || !$pushRequest->hasAdditionalInformation('service_action_from_magento', ['refund'])
        ) {
            return false;
        }

        if ($pushRequest->hasPostData('statuscode', BuckarooStatusCode::SUCCESS)
            && !empty($pushRequest->getRelatedtransactionRefund())
            && $this->receivePushCheckDuplicates(
                BuckarooStatusCode::PENDING_APPROVAL,
                $pushRequest->getRelatedtransactionRefund()
            )) {
            return false;
        }


        return true;
    }
}