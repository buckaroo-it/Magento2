<?php

namespace Buckaroo\Magento2\Observer;

use Buckaroo\Magento2\Model\ConfigProvider\Refund as RefundConfigProvider;
use Buckaroo\Magento2\Model\Transaction\Status\Response;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Sales\Model\Order\Creditmemo;

class PendingApprovalChangeCreditMemoStatus implements ObserverInterface
{
    /**
     * @var RefundConfigProvider
     */
    protected $refundConfigProvider;

    /**
     * @param RefundConfigProvider $refundConfigProvider
     */
    public function __construct(RefundConfigProvider $refundConfigProvider)
    {
        $this->refundConfigProvider = $refundConfigProvider;
    }

    /**
     * Change the credit memo status based on the refund configuration
     *
     * @param Observer $observer
     * @return void
     * @throws \Exception
     */
    public function execute(Observer $observer)
    {
        /** @var Creditmemo $creditmemo */
        $creditmemo = $observer->getEvent()->getCreditmemo();
        if (!$creditmemo) {
            return;
        }

        $payment = $creditmemo->getOrder()->getPayment();
        $pendingRefundStatus = $payment->getAdditionalInformation('pending_refund_status');

        if ($pendingRefundStatus && $pendingRefundStatus == Response::STATUSCODE_PENDING_APPROVAL) {
            $creditmemo->setState(Creditmemo::STATE_OPEN);
            $creditmemo->getOrder()->setTotalRefunded();
            $creditmemo->save();
        }

        if ($pendingRefundStatus && $pendingRefundStatus == Response::STATUSCODE_CANCELLED_BY_MERCHANT) {
            $creditmemo->setState(Creditmemo::STATE_CANCELED);
            $creditmemo->save();
        }
    }
}
