<?php
/**
 * Buckaroo Magento 2 payment module (https://www.buckaroo.eu/)
 *
 * Copyright (c) Buckaroo B.V.
 * See LICENSE for license details.
 *
 * Support: support@buckaroo.nl
 *
 * @copyright Copyright (c) Buckaroo B.V.
 * @license   MIT
 */
declare(strict_types=1);

namespace Buckaroo\Magento2\Plugin;

use Magento\Quote\Model\Quote;

class TotalsCollector
{
    /**
     * Reset quote reward point amount
     *
     * @param Quote\TotalsCollector $subject
     * @param Quote                 $quote
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function beforeCollect(
        Quote\TotalsCollector $subject,
        Quote $quote
    ) {
        /**
         * @noinspection PhpUndefinedMethodInspection
         */
        $quote->setBuckarooFee(0);
        /**
         * @noinspection PhpUndefinedMethodInspection
         */
        $quote->setBaseBuckarooFee(0);
        /**
         * @noinspection PhpUndefinedMethodInspection
         */
        $quote->setBuckarooFeeTaxAmount(0);
        /**
         * @noinspection PhpUndefinedMethodInspection
         */
        $quote->setBuckarooFeeBaseTaxAmount(0);
        /**
         * @noinspection PhpUndefinedMethodInspection
         */
        $quote->setBuckarooFeeInclTax(0);
        /**
         * @noinspection PhpUndefinedMethodInspection
         */
        $quote->setBaseBuckarooFeeInclTax(0);
    }
}
