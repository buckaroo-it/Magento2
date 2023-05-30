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
     * @param Context $context
     * @param array $data
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
     *
     * @return void
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
