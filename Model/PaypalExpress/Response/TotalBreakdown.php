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
declare(strict_types=1);

namespace Buckaroo\Magento2\Model\PaypalExpress\Response;

use Buckaroo\Magento2\Api\Data\PaypalExpress\BreakdownItemInterface;
use Buckaroo\Magento2\Api\Data\PaypalExpress\BreakdownItemInterfaceFactory;
use Buckaroo\Magento2\Api\Data\PaypalExpress\TotalBreakdownInterface;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Address\Total;

class TotalBreakdown implements TotalBreakdownInterface
{
    /**
     * @var BreakdownItemInterfaceFactory
     */
    protected BreakdownItemInterfaceFactory $breakdownItemFactory;

    /**
     * @var Quote
     */
    protected Quote $quote;

    /**
     * @var CartTotalRepository
     */
    protected CartTotalRepository $cartTotalRepository;

    /**
     * @param Quote $quote
     * @param BreakdownItemInterfaceFactory $breakdownItemFactory
     * @param CartTotalRepository $cartTotalRepository
     */
    public function __construct(
        Quote $quote,
        BreakdownItemInterfaceFactory $breakdownItemFactory,
        CartTotalRepository $cartTotalRepository
    ) {
        $this->breakdownItemFactory = $breakdownItemFactory;
        $this->quote = $quote;
        $this->cartTotalRepository = $cartTotalRepository;
    }

    /**
     * Get subtotal
     *
     * @return BreakdownItemInterface
     */
    public function getItemTotal(): BreakdownItemInterface
    {
        return $this->breakdownItemFactory->create(
            [
                "total" => number_format($this->quote->getGrandTotal(), 2) - $this->getTotalsOfType('shipping') - $this->getTotalsOfType('tax'),
                "currencyCode" => $this->quote->getQuoteCurrencyCode()
            ]
        );
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

    /**
     * Get shipping price
     *
     * @return BreakdownItemInterface
     */
    public function getShipping(): BreakdownItemInterface
    {
        return $this->breakdownItemFactory->create(
            [
                "total" => $this->getTotalsOfType('shipping'),
                "currencyCode" => $this->quote->getQuoteCurrencyCode()
            ]
        );
    }

    /**
     * Get taxes
     *
     * @return BreakdownItemInterface
     */
    public function getTaxTotal(): BreakdownItemInterface
    {
        return $this->breakdownItemFactory->create(
            [
                "total" =>  $this->getTotalsOfType('tax'),
                "currencyCode" => $this->quote->getQuoteCurrencyCode()
            ]
        );
    }

    /**
     * Get total from quote of type
     *
     * @param string $type
     *
     * @return float
     */
    protected function getTotalsOfType(string $type)
    {
        $totals = $this->cartTotalRepository->get($this->quote->getId())->getTotalSegments();

        if (!isset($totals[$type])) {
            return 0;
        }

        return round($totals[$type]->getValue(), 2);
    }
}
