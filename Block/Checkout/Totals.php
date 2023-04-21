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

namespace Buckaroo\Magento2\Block\Checkout;

use Buckaroo\Magento2\Helper\PaymentFee;
use Magento\Checkout\Block\Total\DefaultTotal;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\View\Element\Template\Context;
use Magento\Sales\Model\Config;

class Totals extends DefaultTotal
{
    /**
     * Template file path.
     *
     * @var string
     */
    protected $_template = 'checkout/totals.phtml';

    /**
     * Buckaroo fee helper.
     *
     * @var PaymentFee
     */
    protected $helper;

    /**
     * @param Context $context
     * @param CustomerSession $customerSession
     * @param CheckoutSession $checkoutSession
     * @param Config $salesConfig
     * @param PaymentFee $helper
     * @param array $layoutProcessors
     * @param array $data
     */
    public function __construct(
        Context $context,
        CustomerSession $customerSession,
        CheckoutSession $checkoutSession,
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
     * Return information for showing.
     *
     * @return array
     */
    public function getValues()
    {
        $values = [];
        /**
         * @noinspection PhpUndefinedMethodInspection
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
