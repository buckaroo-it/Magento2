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

use Magento\Quote\Api\Data\TotalSegmentExtensionFactory;
use Magento\Quote\Api\Data\TotalSegmentExtensionInterface;
use Magento\Quote\Api\Data\TotalSegmentInterface;
use Magento\Quote\Model\Quote\Address\Total;

class TotalsConverter
{
    /**
     * @var TotalSegmentExtensionFactory
     */
    protected TotalSegmentExtensionFactory $totalSegmentExtensionFactory;

    /**
     * @var string
     */
    protected string $code;

    /**
     * @param TotalSegmentExtensionFactory $totalSegmentExtensionFactory
     */
    public function __construct(
        TotalSegmentExtensionFactory $totalSegmentExtensionFactory
    ) {
        $this->totalSegmentExtensionFactory = $totalSegmentExtensionFactory;
        $this->code = 'buckaroo_fee';
    }

    /**
     * Set Buckaroo fee on totals
     *
     * @param \Magento\Quote\Model\Cart\TotalsConverter $subject
     * @param \Closure $proceed
     * @param Total[] $addressTotals
     * @return TotalSegmentInterface[]
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function aroundProcess(
        \Magento\Quote\Model\Cart\TotalsConverter $subject,
        \Closure $proceed,
        array $addressTotals = []
    ): array {
        /**
         * @var TotalSegmentInterface[] $totals
         */
        $totalSegments = $proceed($addressTotals);
        if (!isset($addressTotals[$this->code])) {
            return $totalSegments;
        }

        $total = $addressTotals[$this->code];
        /**
         * @var TotalSegmentExtensionInterface $totalSegmentExtension
         */
        /**
         * @noinspection PhpUndefinedMethodInspection
         */
        $totalSegmentExtension = $this->totalSegmentExtensionFactory->create();

        /**
         * @noinspection PhpUndefinedMethodInspection
         */
        $totalSegmentExtension->setBuckarooFee($total->getBuckarooFee());
        /**
         * @noinspection PhpUndefinedMethodInspection
         */
        $totalSegmentExtension->setBaseBuckarooFee($total->getBaseBuckarooFee());

        /**
         * @noinspection PhpUndefinedMethodInspection
         */
        $totalSegmentExtension->setBuckarooFeeTaxAmount($total->getBuckarooFeeTaxAmount());
        /**
         * @noinspection PhpUndefinedMethodInspection
         */
        $totalSegmentExtension->setBuckarooFeeBaseTaxAmount($total->getBuckarooFeeBaseTaxAmount());

        /**
         * @noinspection PhpUndefinedMethodInspection
         */
        $totalSegmentExtension->setBuckarooFeeInclTax($total->getBuckarooFeeInclTax());
        /**
         * @noinspection PhpUndefinedMethodInspection
         */
        $totalSegmentExtension->setBaseBuckarooFeeInclTax($total->getBaseBuckarooFeeInclTax());

        /**
         * @noinspection PhpUndefinedMethodInspection
         */
        $totalSegments[$this->code]->setExtensionAttributes($totalSegmentExtension);

        return $totalSegments;
    }
}
