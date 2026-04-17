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

use Buckaroo\Magento2\Api\Data\BreakdownItemInterface;
use Buckaroo\Magento2\Api\Data\BreakdownItemInterfaceFactory;
use Buckaroo\Magento2\Api\Data\TotalBreakdownInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Quote\Api\CartTotalRepositoryInterface;
use Magento\Quote\Api\Data\TotalSegmentInterface;
use Magento\Quote\Model\Quote;

class TotalBreakdown implements TotalBreakdownInterface
{
    /**
     * @var BreakdownItemInterfaceFactory
     */
    protected $breakdownItemFactory;

    /**
     * @var Quote
     */
    protected $quote;

    /**
     * @var CartTotalRepositoryInterface
     */
    protected $cartTotalRepository;

    /**
     * Lazily populated cache of total segments keyed by segment code.
     * Prevents repeated CartTotalRepository::get() calls for the same request.
     *
     * @var TotalSegmentInterface[]|null
     */
    private ?array $segmentCache = null;

    /**
     * @param Quote                         $quote
     * @param BreakdownItemInterfaceFactory $breakdownItemFactory
     * @param CartTotalRepositoryInterface  $cartTotalRepository
     */
    public function __construct(
        Quote $quote,
        BreakdownItemInterfaceFactory $breakdownItemFactory,
        CartTotalRepositoryInterface $cartTotalRepository
    ) {
        $this->breakdownItemFactory = $breakdownItemFactory;
        $this->quote = $quote;
        $this->cartTotalRepository = $cartTotalRepository;
    }

    /**
     * Get subtotal (items + any fees, excluding shipping and tax).
     *
     * @return BreakdownItemInterface
     */
    public function getItemTotal(): BreakdownItemInterface
    {
        $grandCents    = $this->toCents($this->quote->getGrandTotal());
        $shippingCents = $this->toCents($this->getTotalsOfType('shipping'));
        $taxCents      = $this->toCents($this->getTotalsOfType('tax'));

        return $this->breakdownItemFactory->create(
            [
                'total'        => ($grandCents - $shippingCents - $taxCents) / 100.0,
                'currencyCode' => $this->quote->getQuoteCurrencyCode(),
            ]
        );
    }

    /**
     * Get shipping price
     *
     * @return BreakdownItemInterface
     * @throws NoSuchEntityException
     */
    public function getShipping(): BreakdownItemInterface
    {
        return $this->breakdownItemFactory->create(
            [
                'total'        => $this->getTotalsOfType('shipping'),
                'currencyCode' => $this->quote->getQuoteCurrencyCode(),
            ]
        );
    }

    /**
     * Get taxes
     *
     * @return BreakdownItemInterface
     * @throws NoSuchEntityException
     */
    public function getTaxTotal(): BreakdownItemInterface
    {
        return $this->breakdownItemFactory->create(
            [
                'total'        => $this->getTotalsOfType('tax'),
                'currencyCode' => $this->quote->getQuoteCurrencyCode(),
            ]
        );
    }

    /**
     * Get total segment value by type from a lazily loaded, cached segment map.
     *
     * @param string $type
     *
     * @return float
     * @throws NoSuchEntityException
     */
    protected function getTotalsOfType(string $type): float
    {
        if ($this->segmentCache === null) {
            $this->segmentCache = $this->cartTotalRepository
                ->get($this->quote->getId())
                ->getTotalSegments();
        }

        if (!isset($this->segmentCache[$type])) {
            return 0.0;
        }

        return round((float)$this->segmentCache[$type]->getValue(), 2);
    }

    /**
     * Convert a monetary float to an integer number of cents (round half-up).
     *
     * @param float|int|string $amount
     *
     * @return int
     */
    private function toCents($amount): int
    {
        return (int) round((float)$amount * 100);
    }
}
