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

namespace Buckaroo\Magento2\Block\Order;

use Buckaroo\Magento2\Helper\PaymentFee;
use Magento\Framework\DataObject;
use Magento\Framework\View\Element\AbstractBlock;
use Magento\Framework\View\Element\Context;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Creditmemo;
use Magento\Sales\Model\Order\Invoice;

class TotalsEmail extends AbstractBlock
{
    /**
     * @var PaymentFee|null
     */
    protected $helper = null;

    /**
     * @param PaymentFee $helper
     * @param Context    $context
     * @param array      $data
     */
    public function __construct(
        PaymentFee $helper,
        Context $context,
        array $data = []
    ) {
        $this->helper = $helper;

        parent::__construct($context, $data);
    }

    /**
     * Add Buckaroo fee totals
     */
    public function initTotals()
    {
        $order = $this->getParentBlock()->getOrder();
        $this->addBuckarooFeeTotals($order);
    }

    /**
     * Add Buckaroo fee totals
     *
     * @param Order|Invoice|Creditmemo $order
     */
    public function addBuckarooFeeTotals($order)
    {
        $orderTotalsBlock = $this->getParentBlock();
        $totals = $this->helper->getTotals($order);

        foreach ($totals as $total) {
            $orderTotalsBlock->addTotalBefore(new DataObject($total), 'grand_total');
        }
    }
}
