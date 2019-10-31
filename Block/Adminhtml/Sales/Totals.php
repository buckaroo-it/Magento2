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
namespace TIG\Buckaroo\Block\Adminhtml\Sales;

class Totals extends \Magento\Framework\View\Element\Template
{
    /**
     * @var \TIG\Buckaroo\Helper\PaymentFee
     */
    protected $helper = null;

    /**
     * @param \Magento\Framework\View\Element\Template\Context $context
     * @param \TIG\Buckaroo\Helper\PaymentFee                  $helper
     * @param array                                            $data
     */
    public function __construct(
        \Magento\Framework\View\Element\Template\Context $context,
        \TIG\Buckaroo\Helper\PaymentFee $helper,
        array $data = []
    ) {
        $this->helper = $helper;
        parent::__construct($context, $data);
    }

    /**
     * Initialize gbuckaroo fee totals for order/invoice/creditmemo
     *
     * @return $this
     */
    public function initTotals()
    {
        $parent = $this->getParentBlock();
        /**
        * @noinspection PhpUndefinedMethodInspection
        */
        $source = $parent->getSource();
        $totals = $this->helper->getTotals($source);
        foreach ($totals as $total) {
            /**
            * @noinspection PhpUndefinedMethodInspection
            */
            $this->getParentBlock()->addTotalBefore(new \Magento\Framework\DataObject($total), 'grand_total');
        }
        return $this;
    }
}
