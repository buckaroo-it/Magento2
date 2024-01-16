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

namespace Buckaroo\Magento2\Plugin;

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
    protected CartRepositoryInterface $quoteRepository;

    /**
     * @var TotalsExtensionFactory
     */
    protected TotalsExtensionFactory $totalsExtensionFactory;

    /**
     * @param CartRepositoryInterface $quoteRepository
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
     * Set Buckaroo fee before get the totals
     *
     * @param TotalRepository $subject
     * @param \Closure $proceed
     * @param int $cartId
     * @return TotalsInterface
     * @throws NoSuchEntityException
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function aroundGet(TotalRepository $subject, \Closure $proceed, int $cartId): TotalsInterface
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
         * @var TotalsExtensionInterface $extensionAttributes
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
