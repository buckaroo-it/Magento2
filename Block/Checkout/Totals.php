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

namespace TIG\Buckaroo\Block\Checkout;

class Totals extends \Magento\Checkout\Block\Total\DefaultTotal
{
    // @codingStandardsIgnoreStart
    /**
     * Template file path
     *
     * @var string
     */
    protected $_template = 'checkout/totals.phtml';
    // @codingStandardsIgnoreEnd

    /**
     * Buckaroo fee helper
     *
     * @var \TIG\Buckaroo\Helper\PaymentFee
     */
    protected $helper;

    /**
     * @param \Magento\Framework\View\Element\Template\Context $context
     * @param \Magento\Customer\Model\Session                  $customerSession
     * @param \Magento\Checkout\Model\Session                  $checkoutSession
     * @param \Magento\Sales\Model\Config                      $salesConfig
     * @param \TIG\Buckaroo\Helper\PaymentFee                  $helper
     * @param array                                            $layoutProcessors
     * @param array                                            $data
     */
    public function __construct(
        \Magento\Framework\View\Element\Template\Context $context,
        \Magento\Customer\Model\Session $customerSession,
        \Magento\Checkout\Model\Session $checkoutSession,
        \Magento\Sales\Model\Config $salesConfig,
        \TIG\Buckaroo\Helper\PaymentFee $helper,
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
         * @noinspection PhpUndefinedMethodInspection
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
