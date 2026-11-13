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

namespace Buckaroo\Magento2\Block\Order\Invoice;

use Buckaroo\Magento2\Helper\PaymentFee;
use Magento\Framework\DataObject;
use Magento\Framework\Registry;
use Magento\Framework\View\Element\Template\Context;
use Magento\Sales\Block\Order\Invoice\Totals as InvoiceTotals;

class Totals extends InvoiceTotals
{
    /**
     * @var PaymentFee
     */
    protected $helper = null;

    /**
     * @param Context    $context
     * @param Registry   $registry
     * @param PaymentFee $helper
     * @param array      $data
     */
    public function __construct(
        Context $context,
        Registry $registry,
        PaymentFee $helper,
        array $data = []
    ) {
        $this->helper = $helper;
        parent::__construct($context, $registry, $data);
    }

    /**
     * @inheritdoc
     */
    public function getTotals($area = null)
    {
        $this->addBuckarooFeeTotals();

        return parent::getTotals($area);
    }

    /**
     * Initialize buckaroo fee totals for order/invoice/creditmemo
     */
    private function addBuckarooFeeTotals()
    {
        $source = $this->getSource();
        $totals = $this->helper->getTotals($source);

        foreach ($totals as $total) {
            $this->addTotalBefore(new DataObject($total), 'grand_total');
        }
    }
}
