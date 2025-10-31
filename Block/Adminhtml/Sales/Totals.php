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

namespace Buckaroo\Magento2\Block\Adminhtml\Sales;

use Buckaroo\Magento2\Helper\PaymentFee;
use Magento\Framework\DataObject;
use Magento\Framework\Pricing\PriceCurrencyInterface;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use Magento\Sales\Model\Order\Creditmemo;

class Totals extends Template
{
    /**
     * @var PaymentFee
     */
    protected $helper = null;

    /**
     * @var PriceCurrencyInterface
     */
    protected $currency;

    /**
     * @param Context                $context
     * @param PaymentFee             $helper
     * @param PriceCurrencyInterface $currency
     * @param array                  $data
     */
    public function __construct(
        Context $context,
        PaymentFee $helper,
        PriceCurrencyInterface $currency,
        array $data = []
    ) {
        $this->helper = $helper;
        $this->currency = $currency;
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
        $source = $parent->getSource();
        $totals = $this->getTotalsForCreditmemo($source);
        foreach ($totals as $total) {
            $this->getParentBlock()->addTotalBefore(new DataObject($total), 'grand_total');
        }
        return $this;
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
        if ($source instanceof Creditmemo) {
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

    /**
     * Get creditmemo totals
     *
     * @return array
     */
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
     * Check if fee is in creditmemo
     *
     * @param array $creditTotals
     * @param array $saleTotal
     *
     * @return bool
     */
    private function isCreditmemoTotalSelected($creditTotals, $saleTotal)
    {
        foreach ($creditTotals as $creditTotal) {
            if (isset($creditTotal['code']) && $creditTotal['code'] === $saleTotal['code']) {
                return true;
            }
        }
        return false;
    }

    /**
     * Get specific totals by code
     *
     * @param  array  $totals
     * @param  string $code
     * @return array
     */
    private function getTotalsByCode($totals, $code)
    {
        return array_filter($totals, function ($total) use ($code) {
            return $total['code'] === $code;
        });
    }

    /**
     * Get all totals excluding the total with the code
     *
     * @param  array  $totals
     * @param  string $code
     * @return array
     */
    private function getTotalsExceptCode($totals, $code)
    {
        return array_filter($totals, function ($total) use ($code) {
            return $total['code'] !== $code;
        });
    }

    /**
     * Get currency symbol for current locale and currency code
     *
     * @return string
     */
    public function getCurrentCurrencySymbol()
    {
        return $this->currency->getCurrency()->getCurrencySymbol();
    }
}
