<?php

/**
 * Copyright © 2015 Magento. All rights reserved.
 * See COPYING.txt for license details.
 */

namespace Buckaroo\Magento2\Plugin;

use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Api\Data\TotalsExtensionFactory;
use Magento\Quote\Api\Data\TotalsInterface;
use Magento\Quote\Model\Cart\CartTotalRepository as TotalRepository;
use Magento\Quote\Model\Quote;

class CartTotalRepository
{
    /**
     * @var CartRepositoryInterface
     */
    protected $quoteRepository;

    /**
     * @var TotalsExtensionFactory
     */
    protected $totalsExtensionFactory;

    /**
     * @param CartRepositoryInterface     $quoteRepository
     * @param TotalsExtensionFactory $totalsExtensionFactory
     */
    public function __construct(
        CartRepositoryInterface $quoteRepository,
        TotalsExtensionFactory $totalsExtensionFactory
    ) {
        $this->quoteRepository = $quoteRepository;
        $this->totalsExtensionFactory = $totalsExtensionFactory;
    }

    /**
     * @param TotalRepository $subject
     * @param \Closure $proceed
     * @param int $cartId
     * @return TotalsInterface
     * @throws NoSuchEntityException
     */
    public function aroundGet(TotalRepository $subject, \Closure $proceed, $cartId)
    {
        /**
         * @var TotalsInterface $totals
         */
        $totals = $proceed($cartId);

        /**
         * @var Quote $quote
         */
        $quote = $this->quoteRepository->getActive($cartId);

        /**
         * @var \Magento\Quote\Api\Data\TotalsExtensionInterface $extensionAttributes
         */
        $extensionAttributes = $totals->getExtensionAttributes() ?: $this->totalsExtensionFactory->create();

        /**
         * @noinspection PhpUndefinedMethodInspection
         */
        $extensionAttributes->setBuckarooFee($quote->getBuckarooFee());
        /**
         * @noinspection PhpUndefinedMethodInspection
         */
        $extensionAttributes->setBaseBuckarooFee($quote->getBaseBuckarooFee());

        /**
         * @noinspection PhpUndefinedMethodInspection
         */
        $extensionAttributes->setBuckarooFeeTaxAmount($quote->getBuckarooFeeTaxAmount());
        /**
         * @noinspection PhpUndefinedMethodInspection
         */
        $extensionAttributes->setBuckarooFeeBaseTaxAmount($quote->getBuckarooFeeBaseTaxAmount());

        /**
         * @noinspection PhpUndefinedMethodInspection
         */
        $extensionAttributes->setBuckarooFeeInclTax($quote->getBuckarooFeeInclTax());
        /**
         * @noinspection PhpUndefinedMethodInspection
         */
        $extensionAttributes->setBaseBuckarooFeeInclTax($quote->getBaseBuckarooFeeInclTax());

        $totals->setExtensionAttributes($extensionAttributes);

        return $totals;
    }
}
