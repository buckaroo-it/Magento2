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

namespace Buckaroo\Magento2\Model\PaypalExpress\Response;

use Magento\Quote\Model\Quote;
use Buckaroo\Magento2\Api\Data\PaypalExpress\TotalBreakdownInterface;
use Buckaroo\Magento2\Api\Data\PaypalExpress\BreakdownItemInterfaceFactory;

class TotalBreakdown implements TotalBreakdownInterface
{
    /**
     *  @var \Buckaroo\Magento2\Api\Data\PaypalExpress\BreakdownItemInterfaceFactory
     */
    protected $breakdownItemFactory;

    /**
     *  @var \Magento\Quote\Model\Quote
     */
    protected $quote;

    public function __construct(Quote $quote, BreakdownItemInterfaceFactory $breakdownItemFactory)
    {
        $this->breakdownItemFactory = $breakdownItemFactory;
        $this->quote = $quote;
    }
    /**
     * @return \Buckaroo\Magento2\Api\Data\PaypalExpress\BreakdownItemInterface
     */
    public function getItemTotal()
    {
        $total = $this->getTotalsOfType('subtotal');
        return $this->breakdownItemFactory->create(
            [
                "total" => $total != null ? $total->getValueExclTax() + $this->getBuckarooFeeExclTax() : 0,
                "currencyCode" => $this->quote->getQuoteCurrencyCode()
            ]
        );
    }
    /**
     * @return \Buckaroo\Magento2\Api\Data\PaypalExpress\BreakdownItemInterface
     */
    public function getShipping()
    {
        $totals = $this->quote->getShippingAddress()->getTotals();
        $total = isset($totals['shipping']) ? $totals['shipping'] : null;
        return $this->breakdownItemFactory->create(
            [
                "total" => $total !== null ? $total->getValue() : 0,
                "currencyCode" => $this->quote->getQuoteCurrencyCode()
            ]
        );
    }
    /**
     * @return \Buckaroo\Magento2\Api\Data\PaypalExpress\BreakdownItemInterface
     */
    public function getTaxTotal()
    {
        $total = $this->getTotalsOfType('tax');
        return $this->breakdownItemFactory->create(
            [
                "total" => $total !== null ? $total->getValue() : 0,
                "currencyCode" => $this->quote->getQuoteCurrencyCode()
            ]
        );
    }
    /**
     * Get total from quote of type
     *
     * @param string $type
     *
     * @return \Magento\Quote\Model\Quote\Address\Total|null
     */
    protected function getTotalsOfType(string $type)
    {
        $totals = $this->quote->getTotals();

        if (isset($totals[$type])) {
            return $totals[$type];
        }
    }
    /**
     * Get buckaroo fee without tax
     *
     * @return float
     */
    protected function getBuckarooFeeExclTax()
    {
        $fee = $this->getTotalsOfType('buckaroo_fee');
        if ($fee !== null) {
            return (float)$fee->getData('buckaroo_fee');
        }
        return 0;
    }
}
