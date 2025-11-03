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

use Buckaroo\Magento2\Api\Data\Ideal\QuoteCreateResponseInterface;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\QuoteIdMaskFactory;
use Magento\Quote\Model\ResourceModel\Quote\QuoteIdMask as QuoteIdMaskResource;

class QuoteCreate implements QuoteCreateResponseInterface
{
    /**
     * @var Quote
     */
    protected $quote;

    /**
     * @var QuoteIdMaskFactory
     */
    protected $quoteIdMaskFactory;

    /**
     * @var QuoteIdMaskResource
     */
    protected $quoteIdMaskResource;

    /**
     * @param Quote               $quote
     * @param QuoteIdMaskFactory  $quoteIdMaskFactory
     * @param QuoteIdMaskResource $quoteIdMaskResource
     */
    public function __construct(
        Quote $quote,
        QuoteIdMaskFactory $quoteIdMaskFactory,
        QuoteIdMaskResource $quoteIdMaskResource
    ) {
        $this->quote = $quote;
        $this->quoteIdMaskFactory = $quoteIdMaskFactory;
        $this->quoteIdMaskResource = $quoteIdMaskResource;
    }

    /**
     * @inheritdoc
     */
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

    /**
     * @inheritdoc
     */
    public function getCurrencyCode(): string
    {
        return $this->quote->getQuoteCurrencyCode();
    }

    /**
     * @inheritdoc
     */
    public function getValue(): string
    {
        return number_format($this->quote->getGrandTotal(), 2);
    }
}
