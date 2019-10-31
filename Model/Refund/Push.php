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
namespace TIG\Buckaroo\Model\Refund;

use Magento\Sales\Api\CreditmemoManagementInterface;
use Magento\Sales\Model\Order\CreditmemoFactory;
use Magento\Sales\Model\Order\Email\Sender\CreditmemoSender;
use TIG\Buckaroo\Exception;
use TIG\Buckaroo\Logging\Log;
use TIG\Buckaroo\Model\ConfigProvider\Refund;

/**
 * Class Creditmemo
 *
 * @package TIG\Buckaroo\Model\Refund
 */
class Push
{
    public $postData;

    public $creditAmount;

    /**
     * @var \Magento\Sales\Model\Order $order
     */
    public $order;

    /**
     * @var  CreditmemoFactory $creditmemoFactory
     */
    public $creditmemoFactory;

    /** @var CreditmemoManagementInterface */
    private $creditmemoManagement;

    /**
     * @var CreditmemoSender $creditEmailSender
     */
    public $creditEmailSender;

    /**
     * @var Refund
     */
    public $configRefund;

    /**
     * @var Log $logging
     */
    public $logging;

    /**
     * @param CreditmemoFactory             $creditmemoFactory
     * @param CreditmemoManagementInterface $creditmemoManagement
     * @param CreditmemoSender              $creditEmailSender
     * @param Refund                        $configRefund
     * @param Log                           $logging
     */
    public function __construct(
        CreditmemoFactory $creditmemoFactory,
        CreditmemoManagementInterface $creditmemoManagement,
        CreditmemoSender $creditEmailSender,
        Refund $configRefund,
        Log $logging
    ) {
        $this->creditmemoFactory     = $creditmemoFactory;
        $this->creditmemoManagement  = $creditmemoManagement;
        $this->creditEmailSender     = $creditEmailSender;
        $this->logging               = $logging;
        $this->configRefund          = $configRefund;
    }

    /**
     * This is called when a refund is made in Buckaroo Payment Plaza.
     * This Function will result in a creditmemo being created for the order in question.
     *
     * @param array $postData
     * @param bool  $signatureValidation
     * @param $order
     *
     * @return bool
     * @throws \TIG\Buckaroo\Exception
     */
    public function receiveRefundPush($postData, $signatureValidation, $order)
    {
        $this->postData = $postData;
        $this->order    = $order;

        $this->logging->addDebug('Trying to refund order ' . $this->order->getId(). ' out of paymentplaza. ');

        if (!$this->configRefund->getAllowPush()) {
            $this->logging->addDebug('But failed, the configuration is set not to accept refunds out of Payment Plaza');
            throw new Exception(
                __('Buckaroo refund is disabled')
            );
        }

        if (!$signatureValidation && !$this->order->canCreditmemo()) {
            $debugMessage = 'Validation incorrect: ' . PHP_EOL;
            $debugMessage .= print_r(
                [
                    'signature'      => $signatureValidation,
                    'canOrderCredit' => $this->order->canCreditmemo()
                ],
                true
            );
            $this->logging->addDebug($debugMessage);
            throw new Exception(
                __('Buckaroo refund push validation failed')
            );
        }

        $creditmemoCollection = $this->order->getCreditmemosCollection();
        $creditmemosByTransactionId = $creditmemoCollection->getItemsByColumnValue(
            'transaction_id',
            $this->postData['brq_transactions']
        );

        if (count($creditmemosByTransactionId) > 0) {
            $this->logging->addDebug('The transaction has already been refunded.');

            return false;
        }

        $creditmemo = $this->createCreditmemo();

        $this->logging->addDebug('Order successful refunded = '. $creditmemo);

        return $creditmemo;
    }

    /**
     * Create the creditmemo
     */
    public function createCreditmemo()
    {
        $creditData = $this->getCreditmemoData();
        $creditmemo = $this->initCreditmemo($creditData);

        try {
            if ($creditmemo) {
                if (!$creditmemo->isValidGrandTotal()) {
                    $this->logging->addDebug('The credit memo\'s total must be positive.');
                    throw new \Magento\Framework\Exception\LocalizedException(
                        __('The credit memo\'s total must be positive.')
                    );
                }
                $creditmemo->setTransactionId($this->postData['brq_transactions']);

                $this->creditmemoManagement->refund(
                    $creditmemo,
                    (bool)$creditData['do_offline'],
                    !empty($creditData['send_email'])
                );
                if (!empty($data['send_email'])) {
                    $this->creditEmailSender->send($creditmemo);
                }
                return true;
            } else {
                $debugMessage = 'Failed to create the creditmemo, method saveCreditmemo return value: ' . PHP_EOL;
                $debugMessage .= print_r($creditmemo, true);
                $this->logging->addDebug($debugMessage);
                throw new Exception(
                    __('Failed to create the creditmemo')
                );
            }
        } catch (\Magento\Framework\Exception\LocalizedException $e) {
            $this->logging->addDebug('Buckaroo failed to create the credit memo\'s { '. $e->getLogMessage().' }');
        }
        return false;
    }

    /**
     * @param $creditData
     *
     * @return \Magento\Sales\Model\Order\Creditmemo
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function initCreditmemo($creditData)
    {
        try {
            /**
             * @var \Magento\Sales\Model\Order\Creditmemo $creditmemo
             */
            $creditmemo = $this->creditmemoFactory->createByOrder($this->order, $creditData);

            /**
             * @var \Magento\Sales\Model\Order\Creditmemo\Item $creditmemoItem
             */
            foreach ($creditmemo->getAllItems() as $creditmemoItem) {
                /**
                 * @noinspection PhpUndefinedMethodInspection
                 */
                $creditmemoItem->setBackToStock(false);
            }

            return $creditmemo;
        } catch (\Magento\Framework\Exception\LocalizedException $e) {
            $this->logging
                ->addDebug('Buckaroo can not initialize the credit memo\'s by order { '. $e->getLogMessage().' }');
        }
        return false;
    }

    /**
     * Create array of data to use within the creditmemo.
     *
     * @return array
     */
    public function getCreditmemoData()
    {
        $data = [
            'do_offline'   => '0',
            'do_refund'    => '0',
            'comment_text' => ' '
        ];

        $totalAmountToRefund = $this->totalAmountToRefund();
        $this->creditAmount  = $totalAmountToRefund + $this->order->getBaseTotalRefunded();

        if ($this->creditAmount != $this->order->getBaseGrandTotal()) {
            $adjustment = $this->getAdjustmentRefundData();
            $this->logging->addDebug('This is an adjustment refund of '. $totalAmountToRefund);
            $data['shipping_amount']     = '0';
            $data['adjustment_negative'] = '0';
            $data['adjustment_positive'] = $adjustment;
            $data['items']               = $this->getCreditmemoDataItems();
            $data['qtys']                = $this->setCreditQtys($data['items']);
        } else {
            $this->logging->addDebug('With this refund of '. $this->creditAmount.' the grand total will be refunded.');
            $data['shipping_amount']     = $this->caluclateShippingCostToRefund();
            $data['adjustment_negative'] = $this->getTotalCreditAdjustments();
            $data['adjustment_positive'] = $this->calculateRemainder();
            $data['items']               = $this->getCreditmemoDataItems();
            $data['qtys']                = $this->setCreditQtys($data['items']);
        }

        $debugMessage = 'Data used for credit nota: ' . PHP_EOL;
        $debugMessage .= print_r($data, true);
        $this->logging->addDebug($debugMessage);

        return $data;
    }

    /**
     * Get total of adjustments made by previous credits.
     *
     * @return int
     */
    public function getTotalCreditAdjustments()
    {
        $totalAdjustments = 0;

        foreach ($this->order->getCreditmemosCollection() as $creditmemo) {
            /**
             * @var \Magento\Sales\Model\Order\Creditmemo $creditmemo
             */
            $adjustment = $creditmemo->getBaseAdjustmentPositive() - $creditmemo->getBaseAdjustmentNegative();
            $totalAdjustments += $adjustment;
        }

        return $totalAdjustments;
    }

    /**
     * Get adjustment refund data
     *
     * @return float
     */
    public function getAdjustmentRefundData()
    {
        $totalAmount = $this->totalAmountToRefund();

        if ($this->order->getBaseTotalRefunded() == null) {
            /**
             * @noinspection PhpUndefinedMethodInspection
             */
            $totalAmount = $totalAmount - $this->order->getBaseBuckarooFeeInvoiced() - $this->order->getBuckarooFeeBaseTaxAmountInvoiced();
        }

        return $totalAmount;
    }

    /**
     * Calculate the amount to be refunded.
     *
     * @return int $amount
     */
    public function totalAmountToRefund()
    {
        if ($this->postData['brq_currency'] == $this->order->getBaseCurrencyCode()) {
            $amount = $this->postData['brq_amount_credit'];
        } else {
            $amount = round($this->postData['brq_amount_credit'] * $this->order->getBaseToOrderRate(), 2);
        }

        return $amount;
    }

    /**
     * Cacluate the remainder of to be refunded
     *
     * @return float
     */
    public function calculateRemainder()
    {
        $baseTotalToBeRefunded = $this->caluclateShippingCostToRefund() +
            ($this->order->getBaseSubtotal() - $this->order->getBaseSubtotalRefunded()) +
            ($this->order->getBaseAdjustmentNegative() - $this->order->getBaseAdjustmentPositive()) +
            ($this->order->getBaseTaxAmount() - $this->order->getBaseTaxRefunded()) +
            ($this->order->getBaseDiscountAmount() - $this->order->getBaseDiscountRefunded());

        $remainderToRefund = $this->order->getBaseGrandTotal()
            - $baseTotalToBeRefunded
            - $this->order->getBaseTotalRefunded();

        if ($this->totalAmountToRefund() == $this->order->getBaseGrandTotal()) {
            $remainderToRefund = 0;
        }

        return $remainderToRefund;
    }

    /**
     * Calculate the total of shipping cost to be refunded.
     *
     * @return float
     */
    public function caluclateShippingCostToRefund()
    {
        return $this->order->getBaseShippingAmount()
        - $this->order->getBaseShippingRefunded();
    }

    /**
     * Check if there are items to correct on the creditmemo
     *
     * @return array $items
     */
    public function getCreditmemoDataItems()
    {
        $items = [];
        $qty   = 0;

        foreach ($this->order->getAllItems() as $orderItem) {
            /**
             * @var \Magento\Sales\Model\Order\Item $orderItem
             */
            if (!array_key_exists($orderItem->getId(), $items)) {
                if ((float)$this->creditAmount == (float)$this->order->getBaseGrandTotal()) {
                    $qty = $orderItem->getQtyInvoiced() - $orderItem->getQtyRefunded();
                }

                $items[$orderItem->getId()] = ['qty' => (int)$qty];
            }
        }

        $debugMessage = 'Total items to be refunded: ' . PHP_EOL;
        $debugMessage .= print_r($items, true);
        $this->logging->addDebug($debugMessage);

        return $items;
    }

    /**
     * Set quantity items
     *
     * @param array $items
     *
     * @return array $qtys
     */
    public function setCreditQtys($items)
    {
        $qtys = [];

        if (!empty($items)) {
            foreach ($items as $orderItemId => $itemData) {
                $qtys[$orderItemId] = $itemData['qty'];
            }
        }

        return $qtys;
    }
}
