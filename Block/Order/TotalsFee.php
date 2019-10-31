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

namespace TIG\Buckaroo\Block\Order;

use Magento\Framework\DataObject;
use Magento\Framework\Registry;
use Magento\Framework\View\Element\Template\Context;
use Magento\Sales\Block\Order\Totals;
use TIG\Buckaroo\Helper\PaymentFee;

class TotalsFee extends Totals
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
     * {@inheritdoc}
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
