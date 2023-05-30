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

use Magento\Quote\Model\Quote;

class TotalsCollector
{
    /**
     * Reset quote reward point amount
     *
     * @param Quote\TotalsCollector $subject
     * @param Quote $quote
     * @return void
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
