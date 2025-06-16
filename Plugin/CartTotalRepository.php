<?php
/**
 * Copyright © 2015 Magento. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Buckaroo\Magento2\Plugin;

use Closure;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Api\Data\TotalsExtensionFactory;
use Magento\Quote\Api\Data\TotalsExtensionInterface;
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
     * @param Closure $proceed
     * @param int $cartId
     * @return TotalsInterface
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     * @throws NoSuchEntityException
     */
    public function aroundGet(TotalRepository $subject, Closure $proceed, $cartId)
    {
        /**
         * @var TotalsInterface $totals
         */
        $totals = $proceed($cartId);

        /**
         * @var Quote  $quote
         */
        $quote = $this->quoteRepository->getActive($cartId);

        /**
         * @var TotalsExtensionInterface $extensionAttributes
         */
        $extensionAttributes = $totals->getExtensionAttributes() ?: $this->totalsExtensionFactory->create();

        // Try to get values from quote extension attributes first, fallback to direct properties
        $quoteExtensionAttributes = $quote->getExtensionAttributes();

        /**
         * @noinspection PhpUndefinedMethodInspection
         */
        $buckarooFee = $quoteExtensionAttributes && $quoteExtensionAttributes->getBuckarooFee() !== null
            ? $quoteExtensionAttributes->getBuckarooFee()
            : $quote->getBuckarooFee();

        /**
         * @noinspection PhpUndefinedMethodInspection
         */
        $baseBuckarooFee = $quoteExtensionAttributes && $quoteExtensionAttributes->getBaseBuckarooFee() !== null
            ? $quoteExtensionAttributes->getBaseBuckarooFee()
            : $quote->getBaseBuckarooFee();

        /**
         * @noinspection PhpUndefinedMethodInspection
         */
        $extensionAttributes->setBuckarooFee($buckarooFee);
        /**
         * @noinspection PhpUndefinedMethodInspection
         */
        $extensionAttributes->setBaseBuckarooFee($baseBuckarooFee);

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
