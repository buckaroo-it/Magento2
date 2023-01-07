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

namespace Buckaroo\Magento2\Block\Adminhtml\Sales;

class Totals extends \Magento\Framework\View\Element\Template
{
    /**
     * @var \Buckaroo\Magento2\Helper\PaymentFee
     */
    protected $helper = null;

    /**
     * @var \Magento\Framework\Pricing\PriceCurrencyInterface
     */
    protected $_currency;

    /**
     * @param \Magento\Framework\View\Element\Template\Context $context
     * @param \Buckaroo\Magento2\Helper\PaymentFee $helper
     * @param \Magento\Framework\Pricing\PriceCurrencyInterface $currency
     * @param array $data
     */
    public function __construct(
        \Magento\Framework\View\Element\Template\Context  $context,
        \Buckaroo\Magento2\Helper\PaymentFee              $helper,
        \Magento\Framework\Pricing\PriceCurrencyInterface $currency,
        array                                             $data = []
    ) {
        $this->helper = $helper;
        $this->_currency = $currency;
        parent::__construct($context, $data);
    }

    /**
     * Initialize buckaroo fee totals for order/invoice/creditmemo
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
        $totals = $this->getTotalsForCreditmemo($source);
        foreach ($totals as $total) {
            /**
             * @noinspection PhpUndefinedMethodInspection
             */
            $this->getParentBlock()->addTotalBefore(new \Magento\Framework\DataObject($total), 'grand_total');
        }
        return $this;
    }

    public function getTotals()
    {
        $parent = $this->getParentBlock();
        /**
         * @noinspection PhpUndefinedMethodInspection
         */
        $source = $parent->getSource();

        return $this->getTotalsForCreditmemo($source);
    }

    /**
     * Get currency symbol for current locale and currency code
     *
     * @return string
     */
    public function getCurrentCurrencySymbol()
    {
        return $this->_currency->getCurrency()->getCurrencySymbol();
    }

    /**
     * For credit memo display the fees from invoice/order, check if invoice has that fee and set selected
     *
     * @param mixed $source
     *
     * @return array
     */
    protected function getTotalsForCreditmemo($source)
    {
        if ($source instanceof \Magento\Sales\Model\Order\Creditmemo) {
            $creditTotals = $this->helper->getTotals($source);
            $order = $source->getOrder();
            $invoice = $source->getInvoice();
            $salesModel = ($invoice != null ? $invoice : $order);
            $saleTotals = $this->helper->getTotals($salesModel);

            $saleTotals = array_map(function ($saleTotal) use ($creditTotals) {
                if (in_array($saleTotal['code'], ['buckaroo_fee', 'buckaroo_fee_excl'])) {
                    $saleTotal['block_name'] = "buckaroo_fee";
                    $saleTotal['is_selected'] = $this->isCreditmemoTotalSelected($creditTotals, $saleTotal);
                }
                return $saleTotal;
            }, $saleTotals);

            return array_merge(
                $this->getTotalsByCode($creditTotals, 'buckaroo_already_paid'),
                $this->getTotalsExceptCode($saleTotals, 'buckaroo_already_paid')
            );
        }
        return $this->helper->getTotals($source);
    }

    private function getTotalsByCode($totals, $code)
    {
        return array_filter($totals, function ($total) use ($code) {
            return $total['code'] === $code;
        });
    }

    private function getTotalsExceptCode($totals, $code)
    {
        return array_filter($totals, function ($total) use ($code) {
            return $total['code'] !== $code;
        });
    }

    /**
     * Check if fee is in creditmemo
     *
     * @param array $creditTotals
     * @param array $saleTotal
     *
     * @return boolean
     */
    private function isCreditmemoTotalSelected($creditTotals, $saleTotal)
    {
        foreach ($creditTotals as $creditTotal) {
            if (isset($creditTotal['code']) && $creditTotal['code'] === $saleTotal['code']
            ) {
                return true;
            }
        }
        return false;
    }
}
