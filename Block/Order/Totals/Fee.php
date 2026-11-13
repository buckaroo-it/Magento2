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
declare(strict_types=1);

namespace Buckaroo\Magento2\Block\Order\Totals;

use Buckaroo\Magento2\Helper\PaymentFee;
use Magento\Framework\DataObject;
use Magento\Framework\View\Element\AbstractBlock;
use Magento\Framework\View\Element\Context;
use Magento\Sales\Block\Order\Totals;

/**
 * Adds the Buckaroo payment fee lines to the order, invoice or credit memo totals block it is placed in.
 *
 * Placed as a child of the existing totals block: Magento calls initTotals() on every child before the totals
 * render. The totals block itself (core or theme) stays in place, so the lines other modules add are kept.
 */
class Fee extends AbstractBlock
{
    /**
     * @var PaymentFee
     */
    private PaymentFee $paymentFee;

    /**
     * @param Context    $context
     * @param PaymentFee $paymentFee
     * @param array      $data
     */
    public function __construct(
        Context $context,
        PaymentFee $paymentFee,
        array $data = []
    ) {
        $this->paymentFee = $paymentFee;
        parent::__construct($context, $data);
    }

    /**
     * Add the fee (and already paid) lines before the grand total of the parent totals block
     *
     * @return $this
     */
    public function initTotals()
    {
        $parent = $this->getParentBlock();
        if (!$parent instanceof Totals) {
            return $this;
        }

        // The parent already read its source (order, invoice or credit memo) in _initTotals().
        foreach ($this->paymentFee->getTotals($parent->getSource()) as $total) {
            $parent->addTotalBefore(new DataObject($total), 'grand_total');
        }

        return $this;
    }
}
