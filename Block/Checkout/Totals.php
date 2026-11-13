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

namespace Buckaroo\Magento2\Block\Checkout;

use Buckaroo\Magento2\Helper\PaymentFee;
use Magento\Customer\Model\Session;
use Magento\Framework\View\Element\Template\Context;
use Magento\Sales\Model\Config;

class Totals extends \Magento\Checkout\Block\Total\DefaultTotal
{
    /**
     * Template file path
     *
     * @var string
     */
    protected $_template = 'checkout/totals.phtml';

    /**
     * Buckaroo fee helper
     *
     * @var PaymentFee
     */
    protected $helper;

    /**
     * @param Context                         $context
     * @param Session                         $customerSession
     * @param \Magento\Checkout\Model\Session $checkoutSession
     * @param Config                          $salesConfig
     * @param PaymentFee                      $helper
     * @param array                           $layoutProcessors
     * @param array                           $data
     */
    public function __construct(
        Context $context,
        Session $customerSession,
        \Magento\Checkout\Model\Session $checkoutSession,
        Config $salesConfig,
        PaymentFee $helper,
        array $layoutProcessors = [],
        array $data = []
    ) {
        $this->helper = $helper;
        parent::__construct($context, $customerSession, $checkoutSession, $salesConfig, $layoutProcessors, $data);
        $this->_isScopePrivate = true;
    }

    /**
     * Return information for showing
     *
     * @return array
     */
    public function getValues()
    {
        $values = [];
        /**
         * @phpstan-ignore-next-line
         */
        $total = $this->getTotal();
        $totals = $this->helper->getTotals($total);
        foreach ($totals as $total) {
            $label = (string)$total['label'];
            $values[$label] = $total['value'];
        }
        return $values;
    }
}
