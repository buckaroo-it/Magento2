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

namespace Buckaroo\Magento2\Model\Total\Quote\Tax;

use Magento\Framework\Phrase;
use Magento\Quote\Api\Data\ShippingAssignmentInterface;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Address\Total;
use Magento\Quote\Model\Quote\Address\Total\AbstractTotal;
use Magento\Tax\Model\Sales\Total\Quote\CommonTaxCollector;

class BuckarooFeeAfterTax extends AbstractTotal
{
    /**
     * Set tax code
     */
    public function __construct()
    {
        $this->setCode('tax_buckaroo_fee');
    }

    /**
     * Collect buckaroo fee tax totals
     *
     * @param Quote $quote
     * @param ShippingAssignmentInterface $shippingAssignment
     * @param Total $total
     *
     * @return $this
     */
    public function collect(
        Quote $quote,
        ShippingAssignmentInterface $shippingAssignment,
        Total $total
    ) {
        /**
         * @noinspection PhpUndefinedMethodInspection
         */
        $total->setBuckarooFeeInclTax(0);
        /**
         * @noinspection PhpUndefinedMethodInspection
         */
        $total->setBaseBuckarooFeeInclTax(0);
        /**
         * @noinspection PhpUndefinedMethodInspection
         */
        $total->setBuckarooFeeTaxAmount(0);
        /**
         * @noinspection PhpUndefinedMethodInspection
         */
        $total->setBuckarooFeeBaseTaxAmount(0);

        if (!$shippingAssignment->getItems()) {
            return $this;
        }

        /**
         * @noinspection PhpUndefinedMethodInspection
         */
        $extraTaxableDetails = $total->getExtraTaxableDetails();

        if (empty($extraTaxableDetails[BuckarooFee::QUOTE_TYPE])) {
            return $this;
        }
        $itemTaxDetails = $extraTaxableDetails[BuckarooFee::QUOTE_TYPE];

        if (empty($itemTaxDetails[CommonTaxCollector::ASSOCIATION_ITEM_CODE_FOR_QUOTE][0])) {
            return $this;
        }
        $buckarooFeeTaxDetails = $itemTaxDetails[CommonTaxCollector::ASSOCIATION_ITEM_CODE_FOR_QUOTE][0];

        $buckarooFeeBaseTaxAmount = $buckarooFeeTaxDetails['base_row_tax'];
        $buckarooFeeTaxAmount = $buckarooFeeTaxDetails['row_tax'];
        $buckarooFeeInclTax = $buckarooFeeTaxDetails['price_incl_tax'];
        $buckarooFeeBaseInclTax = $buckarooFeeTaxDetails['base_price_incl_tax'];

        /**
         * @noinspection PhpUndefinedMethodInspection
         */
        $total->setBuckarooFeeInclTax($buckarooFeeInclTax);
        /**
         * @noinspection PhpUndefinedMethodInspection
         */
        $total->setBaseBuckarooFeeInclTax($buckarooFeeBaseInclTax);

        /**
         * @noinspection PhpUndefinedMethodInspection
         */
        $total->setBuckarooFeeBaseTaxAmount($buckarooFeeBaseTaxAmount);
        /**
         * @noinspection PhpUndefinedMethodInspection
         */
        $total->setBuckarooFeeTaxAmount($buckarooFeeTaxAmount);

        /**
         * @noinspection PhpUndefinedMethodInspection
         */
        $quote->setBuckarooFeeInclTax($buckarooFeeInclTax);
        /**
         * @noinspection PhpUndefinedMethodInspection
         */
        $quote->setBaseBuckarooFeeInclTax($buckarooFeeBaseInclTax);

        /**
         * @noinspection PhpUndefinedMethodInspection
         */
        $quote->setBuckarooFeeBaseTaxAmount($buckarooFeeBaseTaxAmount);
        /**
         * @noinspection PhpUndefinedMethodInspection
         */
        $quote->setBuckarooFeeTaxAmount($buckarooFeeTaxAmount);

        return $this;
    }

    /**
     * Assign buckaroo fee tax totals and labels to address object
     *
     * @param Quote $quote
     * @param Total $total
     *
     * @return array
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function fetch(Quote $quote, Total $total): array
    {
        /**
         * @noinspection PhpUndefinedMethodInspection
         */
        return [
            'code'                         => 'buckaroo_fee',
            'title'                        => $this->getLabel(),
            'buckaroo_fee'                 => $total->getBuckarooFee(),
            'base_buckaroo_fee'            => $total->getBaseBuckarooFee(),
            'buckaroo_fee_incl_tax'        => $total->getBuckarooFeeInclTax(),
            'base_buckaroo_fee_incl_tax'   => $total->getBaseBuckarooFeeInclTax(),
            'buckaroo_fee_tax_amount'      => $total->getBuckarooFeeTaxAmount(),
            'buckaroo_fee_base_tax_amount' => $total->getBuckarooFeeBaseTaxAmount(),
        ];
    }

    /**
     * Get Buckaroo label
     *
     * @return Phrase
     */
    public function getLabel(): Phrase
    {
        return __('Payment Fee');
    }
}
