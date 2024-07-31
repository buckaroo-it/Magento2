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
use Magento\Quote\Model\QuoteIdMaskFactory;
use Buckaroo\Magento2\Api\Data\Ideal\QuoteCreateResponseInterface;
use Buckaroo\Magento2\Api\Data\Ideal\TotalBreakdownInterfaceFactory;
use Magento\Quote\Model\ResourceModel\Quote\QuoteIdMask as QuoteIdMaskResource;

class QuoteCreate implements QuoteCreateResponseInterface
{
    /**
     * @var \Buckaroo\Magento2\Api\Data\Ideal\TotalBreakdownInterfaceFactory
     */
    protected $totalBreakdownFactory;

    /**
     * @var \Magento\Quote\Model\Quote;
     */
    protected $quote;

    /**
     * @var \Magento\Quote\Model\QuoteIdMaskFactory
     */
    protected $quoteIdMaskFactory;

    /**
     * @var \Magento\Quote\Model\ResourceModel\Quote\QuoteIdMask
     */
    protected $quoteIdMaskResource;

    public function __construct(
        Quote $quote,
        TotalBreakdownInterfaceFactory $totalBreakdownFactory,
        QuoteIdMaskFactory $quoteIdMaskFactory,
        QuoteIdMaskResource $quoteIdMaskResource
    ) {
        $this->totalBreakdownFactory = $totalBreakdownFactory;
        $this->quote = $quote;
        $this->quoteIdMaskFactory = $quoteIdMaskFactory;
        $this->quoteIdMaskResource = $quoteIdMaskResource;
    }

    /** @inheritDoc */
    public function getBreakdown()
    {
        $totalBreakdown = $this->totalBreakdownFactory->create(["quote" => $this->quote]);
        return $totalBreakdown;
    }

    /** @inheritDoc */
    public function getCurrencyCode()
    {
        return $this->quote->getQuoteCurrencyCode();
    }

    /** @inheritDoc */
    public function getValue()
    {
        return number_format($this->quote->getGrandTotal(), 2);
    }

    /** @inheritDoc */
    public function getCartId()
    {
        $quoteIdMask = $this->quoteIdMaskFactory->create();
        $this->quoteIdMaskResource->load($quoteIdMask, $this->quote->getId(), 'quote_id');
        if (empty($quoteIdMask->getMaskedId())) {
            $quoteIdMask->setQuoteId((int)$this->quote->getId());
            $this->quoteIdMaskResource->save($quoteIdMask);
        }
        return $quoteIdMask->getMaskedId();
    }
}
