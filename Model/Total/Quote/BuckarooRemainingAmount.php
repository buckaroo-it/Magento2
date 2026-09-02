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

namespace Buckaroo\Magento2\Model\Total\Quote;

use Magento\Framework\Phrase;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Address\Total;
use Magento\Quote\Model\Quote\Address\Total\AbstractTotal;
use Buckaroo\Magento2\Helper\PaymentGroupTransaction;

class BuckarooRemainingAmount extends AbstractTotal
{
    /**
     * @var PaymentGroupTransaction
     */
    protected $groupTransaction;

    /**
     * Constructor
     *
     * @param PaymentGroupTransaction $groupTransaction
     */
    public function __construct(PaymentGroupTransaction $groupTransaction)
    {
        $this->setCode('remaining_amount');
        $this->groupTransaction = $groupTransaction;
    }

    /**
     * Fetch remaining amount for display in the frontend.
     *
     * @param Quote $quote
     * @param Total $total
     *
     * @return array
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function fetch(Quote $quote, Total $total)
    {
        $orderId = $quote->getReservedOrderId();

        $alreadyPaid = $this->groupTransaction->getAlreadyPaid($orderId);

        // If no order ID is set or nothing has been paid, return zero for remaining amount.
        if (!$orderId || $alreadyPaid <= 0) {
            return [
                'code'  => $this->getCode(),
                'title' => $this->getLabel(),
                'value' => 0
            ];
        }

        // Calculate the remaining amount (grand total minus the amount already paid)
        $grandTotal = $quote->getGrandTotal();
        $remainingAmount = max(0, $grandTotal - $alreadyPaid);

        return [
            'code'  => $this->getCode(),
            'title' => $this->getLabel(),
            'value' => $remainingAmount
        ];
    }

    /**
     * Get Buckaroo label.
     *
     * @return Phrase
     */
    public function getLabel()
    {
        return __('Remaining Amount');
    }
}
