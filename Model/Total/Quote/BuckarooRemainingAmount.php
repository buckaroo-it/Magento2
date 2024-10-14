<?php

namespace Buckaroo\Magento2\Model\Total\Quote;

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
     * Fetch remaining amount for display in the frontend
     *
     * @param Quote $quote
     * @param Total $total
     * @return array
     */
    public function fetch(Quote $quote, Total $total)
    {
        // Fetch the already paid amount
        $alreadyPaid = $this->groupTransaction->getAlreadyPaid($quote->getReservedOrderId());
        $grandTotal = $quote->getGrandTotal();
        $remainingAmount = max(0, $grandTotal - $alreadyPaid);

        return [
            'code'  => $this->getCode(),
            'title' => __('Remaining Amount'),
            'value' => $remainingAmount
        ];
    }

    /**
     * Get Buckaroo label
     *
     * @return \Magento\Framework\Phrase
     */
    public function getLabel()
    {
        return __('Fee');
    }
}
