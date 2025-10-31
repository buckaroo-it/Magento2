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
