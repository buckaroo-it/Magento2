<?php
/**
 * Copyright © 2015 Magento. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace TIG\Buckaroo\Model\Plugin;

class TotalsConverter
{
    /**
     * @var \Magento\Quote\Api\Data\TotalSegmentExtensionFactory
     */
    protected $totalSegmentExtensionFactory;

    /**
     * @var string
     */
    protected $code;

    /**
     * @param \Magento\Quote\Api\Data\TotalSegmentExtensionFactory $totalSegmentExtensionFactory
     */
    public function __construct(
        \Magento\Quote\Api\Data\TotalSegmentExtensionFactory $totalSegmentExtensionFactory
    ) {
        $this->totalSegmentExtensionFactory = $totalSegmentExtensionFactory;
        $this->code = 'buckaroo_fee';
    }

    /**
     * @param \Magento\Quote\Model\Cart\TotalsConverter  $subject
     * @param \Closure                                   $proceed
     * @param \Magento\Quote\Model\Quote\Address\Total[] $addressTotals
     * @return \Magento\Quote\Api\Data\TotalSegmentInterface[]
     */
    public function aroundProcess(
        \Magento\Quote\Model\Cart\TotalsConverter $subject,
        \Closure $proceed,
        array $addressTotals = []
    ) {
        /**
         * @var \Magento\Quote\Api\Data\TotalSegmentInterface[] $totals
         */
        $totalSegments = $proceed($addressTotals);
        if (!isset($addressTotals[$this->code])) {
            return $totalSegments;
        }

        $total = $addressTotals[$this->code];
        /**
         * @var \Magento\Quote\Api\Data\TotalSegmentExtensionInterface $totalSegmentExtension
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
