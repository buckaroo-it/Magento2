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

namespace Buckaroo\Magento2\Model\Ideal\Response;

use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Cart\CartTotalRepository;
use Buckaroo\Magento2\Api\Data\Ideal\TotalBreakdownInterface;
use Buckaroo\Magento2\Api\Data\Ideal\BreakdownItemInterfaceFactory;

class TotalBreakdown implements TotalBreakdownInterface
{

    /**
     *  @var \Buckaroo\Magento2\Api\Data\Ideal\BreakdownItemInterfaceFactory
     */
    protected $breakdownItemFactory;

    /**
     *  @var \Magento\Quote\Model\Quote
     */
    protected $quote;

    protected $cartTotalRepository;

    public function __construct(
        Quote $quote,
        BreakdownItemInterfaceFactory $breakdownItemFactory,
        CartTotalRepository $cartTotalRepository
    )
    {
        $this->breakdownItemFactory = $breakdownItemFactory;
        $this->quote = $quote;
        $this->cartTotalRepository = $cartTotalRepository;
    }
    /**
     * @return \Buckaroo\Magento2\Api\Data\Ideal\BreakdownItemInterface
     */
    public function getItemTotal()
    {
        return $this->breakdownItemFactory->create(
            [
                "total" => number_format($this->quote->getGrandTotal(), 2) - $this->getTotalsOfType('shipping') - $this->getTotalsOfType('tax'),
                "currencyCode" => $this->quote->getQuoteCurrencyCode()
            ]
        );
    }
    /**
     * @return \Buckaroo\Magento2\Api\Data\Ideal\BreakdownItemInterface
     */
    public function getShipping()
    {
        return $this->breakdownItemFactory->create(
            [
                "total" => $this->getTotalsOfType('shipping'),
                "currencyCode" => $this->quote->getQuoteCurrencyCode()
            ]
        );
    }
    /**
     * @return \Buckaroo\Magento2\Api\Data\Ideal\BreakdownItemInterface
     */
    public function getTaxTotal()
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
