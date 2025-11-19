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

namespace Buckaroo\Magento2\Block\Adminhtml\Sales\Order;

use Buckaroo\Magento2\Helper\PaymentGroupTransaction;
use Buckaroo\Magento2\Model\ResourceModel\Giftcard\Collection as GiftcardCollection;
use Magento\Framework\DataObject;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;

class GiftcardTotals extends Template
{
    /**
     * @var PaymentGroupTransaction
     */
    protected $groupTransaction;

    /**
     * @var GiftcardCollection
     */
    protected $giftcardCollection;

    /**
     * @param Context $context
     * @param PaymentGroupTransaction $groupTransaction
     * @param GiftcardCollection $giftcardCollection
     * @param array $data
     */
    public function __construct(
        Context $context,
        PaymentGroupTransaction $groupTransaction,
        GiftcardCollection $giftcardCollection,
        array $data = []
    ) {
        $this->groupTransaction = $groupTransaction;
        $this->giftcardCollection = $giftcardCollection;
        parent::__construct($context, $data);
    }

    /**
     * Initialize giftcard totals for order view
     *
     * @return $this
     */
    public function initTotals()
    {
        $parent = $this->getParentBlock();
        $order = $parent->getOrder();

        $totals = $this->getGiftcardTotals($order);
        foreach ($totals as $total) {
            $parent->addTotalBefore(new DataObject($total), 'grand_total');
        }

        return $this;
    }

    /**
     * Get giftcard totals for order
     *
     * IMPORTANT: This block ONLY handles single full giftcard payments without group transactions.
     * Orders with group transactions are already handled by PaymentFee->getTotals() via Totals block.
     *
     * @param \Magento\Sales\Model\Order $order
     * @return array
     */
    protected function getGiftcardTotals($order)
    {
        $totals = [];

        // Check if group transactions exist - if they do, the PaymentFee helper already displays them
        // This prevents duplicate display
        $items = $this->groupTransaction->getGroupTransactionItems($order->getIncrementId());
        if (!empty($items)) {
            // Group transactions exist - already being displayed by the standard Totals block
            return $totals;
        }

        // Only handle single giftcard payments without group transactions
        if ($this->isSingleGiftcardPayment($order)) {
            $giftcardInfo = $this->getSingleGiftcardInfo($order);
            if ($giftcardInfo) {
                $totals[] = $giftcardInfo;
            }
        }

        return $totals;
    }

    /**
     * Check if this is a single giftcard payment (not a group transaction)
     *
     * @param \Magento\Sales\Model\Order $order
     * @return bool
     */
    private function isSingleGiftcardPayment($order): bool
    {
        $payment = $order->getPayment();
        $method = $payment->getMethod();

        // Check if payment method is giftcards
        if ($method !== 'buckaroo_magento2_giftcards') {
            return false;
        }

        // Check if transaction method in raw details is a giftcard
        $rawDetailsInfo = $payment->getAdditionalInformation('raw_details_info');
        if (!is_array($rawDetailsInfo) || empty($rawDetailsInfo)) {
            return false;
        }

        $firstTransaction = reset($rawDetailsInfo);
        $transactionMethod = $firstTransaction['brq_transaction_method'] ?? null;

        return !empty($transactionMethod);
    }

    /**
     * Get single giftcard information from raw transaction details
     *
     * @param \Magento\Sales\Model\Order $order
     * @return array|null
     */
    private function getSingleGiftcardInfo($order): ?array
    {
        $payment = $order->getPayment();
        $rawDetailsInfo = $payment->getAdditionalInformation('raw_details_info');

        if (!is_array($rawDetailsInfo) || empty($rawDetailsInfo)) {
            return null;
        }

        $firstTransaction = reset($rawDetailsInfo);
        $servicecode = $firstTransaction['brq_transaction_method'] ?? null;
        $amount = $firstTransaction['brq_amount'] ?? null;

        if (!$servicecode || !$amount) {
            return null;
        }

        // Try to find the giftcard in the collection
        $foundGiftcard = $this->giftcardCollection->getItemByColumnValue('servicecode', $servicecode);

        if ($foundGiftcard) {
            // It's a real giftcard
            $label = $foundGiftcard['label'];
        } elseif ($servicecode === 'buckaroovoucher') {
            // It's a buckaroo voucher
            $label = __('Voucher');
        } else {
            // It's NOT a giftcard (e.g., ideal, paypal, etc.)
            // Don't display it as a giftcard total
            return null;
        }

        return [
            'code'  => 'buckaroo_giftcard_' . $servicecode,
            'label' => __('Paid with %1', $label),
            'value' => (float)$amount,
            'base_value' => (float)$amount,
        ];
    }
}

