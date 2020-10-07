<?php
/**
 * NOTICE OF LICENSE
 *
 * This source file is subject to the MIT License
 * It is available through the world-wide-web at this URL:
 * https://tldrlegal.com/license/mit-license
 * If you are unable to obtain it through the world-wide-web, please send an email
 * to support@buckaroo.nl so we can send you a copy immediately.
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

namespace Buckaroo\Magento2\Model\Total\Creditmemo;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\ResourceConnection;
use Buckaroo\Magento2\Logging\Log;

class BuckarooFee extends \Magento\Sales\Model\Order\Creditmemo\Total\AbstractTotal
{
    /**
     * Request instance
     *
     * @var \Magento\Framework\App\RequestInterface
     */
    protected $request;

    /**
     * @var ResourceConnection $resourceConnection
     */
    protected $resourceConnection;

    protected $logger;

    /**
     * @param RequestInterface $request
     */
    public function __construct(RequestInterface $request, ResourceConnection $resourceConnection, Log $logger)
    {
        $this->request = $request;
        $this->resourceConnection = $resourceConnection;
        $this->logger = $logger;
        parent::__construct();
    }

    /**
     * Collect totals for credit memo
     *
     * @param  \Magento\Sales\Model\Order\Creditmemo $creditmemo
     * @return $this
     */
    public function collect(\Magento\Sales\Model\Order\Creditmemo $creditmemo)
    {

        $postData = $this->request->getParams();
        $this->logger->addDebug(__METHOD__ . '$postData ||| ' . var_export($postData, true));

        $order = $creditmemo->getOrder();
        $invoice = $creditmemo->getInvoice();

        $transactionData = [];
        $transactionData['order_id'] = $order->getData('increment_id');
        $this->logger->addDebug('ADDITIONAL INF ||| ' . var_export($order->getPayment()->getAdditionalInformation(), true));
        $transactionData['transaction_id'] = $order->getPayment()->getAdditionalInformation('buckaroo_original_transaction_key');

        if (isset($postData['brq_transactions'])) {
            $transactionData['transaction_key'] = $postData['brq_transactions'];
            if ($this->isWaitingForApproveOrderExist($this->resourceConnection, $transactionData )) {
                return $this;
            }
        }
        $method = $order->getPayment()->getMethod();
        $refundCollection = $order->getCreditmemosCollection();

        $salesModel = ($invoice ? $invoice : $order);

        $refundItem = null;

        $refundItem = $this->request->getPost('creditmemo');

        $this->logger->addDebug('FEE ||||');

        if ($salesModel->getBaseBuckarooFee()
            && $order->getBaseBuckarooFeeInvoiced() > $order->getBaseBuckarooFeeRefunded()
        ) {
            $baseBuckarooFee = !isset($refundItem['buckaroo_fee_refundable']) && !empty($refundItem) ? 0 : $salesModel->getBaseBuckarooFee();
            $buckarooFee = !isset($refundItem['buckaroo_fee_refundable']) && !empty($refundItem) ? 0 : $salesModel->getBuckarooFee();

            if (preg_match('/afterpay/', $method) && count($refundCollection) > 1) {
                $baseBuckarooFee = 0;
                $buckarooFee = 0;
            }

            $order->setBaseBuckarooFeeRefunded($order->getBaseBuckarooFeeRefunded() + $baseBuckarooFee);
            $order->setBuckarooFeeRefunded($order->getBuckarooFeeRefunded() + $buckarooFee);

            $creditmemo->setBaseBuckarooFee($baseBuckarooFee);
            $creditmemo->setBuckarooFee($buckarooFee);
        }

        /**
         * @noinspection PhpUndefinedMethodInspection
         */
        $creditmemo->setBaseGrandTotal(
            $creditmemo->getBaseGrandTotal() +
            $creditmemo->getBaseBuckarooFee()
        );
        /**
         * @noinspection PhpUndefinedMethodInspection
         */
        $creditmemo->setGrandTotal(
            $creditmemo->getGrandTotal() +
            $creditmemo->getBuckarooFee()
        );

        return $this;
    }

    private function isWaitingForApproveOrderExist($resourceConnection, $transactionData)
    {

        if (!$resourceConnection->getConnection()->isTableExists('buckaroo_magento2_waiting_for_approval')) {
            return false;
        }

        $this->logger->addDebug('$transactionData |||' . var_export($transactionData, true));
        $dataWaitingForApprove = $resourceConnection->getConnection()->select()
            ->from('buckaroo_magento2_waiting_for_approval')
            ->where('order_id = ?', $transactionData['order_id'])
            ->where('transaction_id = ?', $transactionData['transaction_id'])
            ->where('transaction_key = ?', $transactionData['transaction_key']);

        $isBuckarooFeeWaitingForRefund = $resourceConnection->getConnection()->fetchRow($dataWaitingForApprove);
        $this->logger->addDebug('$isBuckarooFeeWaitingForRefund |||' . var_export($isBuckarooFeeWaitingForRefund, true));

        if (!empty($isBuckarooFeeWaitingForRefund)) {
            $this->logger->addDebug(__METHOD__ . '|1|' . var_export($isBuckarooFeeWaitingForRefund['buckaroo_is_fee_waiting_for_refund'], true));
            if (empty($isBuckarooFeeWaitingForRefund['buckaroo_is_fee_waiting_for_refund'])) {
                $this->logger->addDebug('$isBuckarooFeeWaitingForRefund |||' . var_export($isBuckarooFeeWaitingForRefund['buckaroo_is_fee_waiting_for_refund'], true));
                return true;
            }
        }

        return false;
    }
}
