<?php

namespace Buckaroo\Magento2\Model\Push;

use Buckaroo\Magento2\Api\PushProcessorInterface;
use Buckaroo\Magento2\Api\PushRequestInterface;
use Buckaroo\Magento2\Exception as BuckarooException;
use Buckaroo\Magento2\Helper\Data;
use Buckaroo\Magento2\Helper\PaymentGroupTransaction;
use Buckaroo\Magento2\Logging\Log;
use Buckaroo\Magento2\Model\BuckarooStatusCode;
use Buckaroo\Magento2\Model\ConfigProvider\Account;
use Buckaroo\Magento2\Model\OrderStatusFactory;
use Buckaroo\Magento2\Model\Refund\Push as RefundPush;
use Buckaroo\Magento2\Model\Validator\Push as ValidatorPush;
use Buckaroo\Magento2\Service\Push\OrderRequestService;
use Magento\Sales\Api\Data\TransactionInterface;

class RefundProcessor extends DefaultProcessor
{
    /**
     * @var RefundPush
     */
    private RefundPush $refundPush;


    public function __construct(
        OrderRequestService $orderRequestService,
        PushTransactionType $pushTransactionType,
        Log $logging,
        Data $helper,
        TransactionInterface $transaction,
        PaymentGroupTransaction $groupTransaction,
        BuckarooStatusCode $buckarooStatusCode,
        OrderStatusFactory $orderStatusFactory,
        Account $configAccount,
        RefundPush $refundPush
    ) {
        parent::__construct($orderRequestService, $pushTransactionType, $logging, $helper, $transaction,
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

        if ($this->pushTransactionType->getStatusKey() !== 'BUCKAROO_MAGENTO2_STATUSCODE_SUCCESS'
            && !$this->order->hasInvoices()
        ) {
            throw new BuckarooException(
                __('Refund failed ! Status : %1 and the order does not contain an invoice',
                    $this->pushTransactionType->getStatusKey())
            );
        } elseif ($this->pushTransactionType->getStatusKey() !== 'BUCKAROO_MAGENTO2_STATUSCODE_SUCCESS'
            && $this->order->hasInvoices()
        ) {
            //don't proceed failed refund push
            $this->logging->addDebug(__METHOD__ . '|10|');
            $this->orderRequestService->setOrderNotificationNote(
                __('Push notification for refund has no success status, ignoring.')
            );
            return true;
        }

        return $this->refundPush->receiveRefundPush($this->pushRequest, true, $this->order);
    }

    /**
     * Skip Pending Refund Push
     *
     * @param PushRequestInterface $pushRequest
     * @return bool
     */
    private function skipPendingRefundPush(PushRequestInterface $pushRequest): bool
    {
        if ($pushRequest->hasAdditionalInformation('initiated_by_magento', 1)
            && $pushRequest->hasAdditionalInformation('service_action_from_magento', ['refund'])
        ) {
            if ($pushRequest->hasPostData('statuscode', BuckarooStatusCode::SUCCESS)
                && !empty($pushRequest->getRelatedtransactionRefund())
                && $this->receivePushCheckDuplicates(
                    BuckarooStatusCode::PENDING_APPROVAL,
                    $pushRequest->getRelatedtransactionRefund()
                )) {
                $this->logging->addDebug(__METHOD__ . '|4|');
                return false;
            }
            $this->logging->addDebug(__METHOD__ . '|5|');
            return true;
        }

        return false;
    }
}