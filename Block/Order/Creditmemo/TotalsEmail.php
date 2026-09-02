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

namespace Buckaroo\Magento2\Block\Order\Creditmemo;

class TotalsEmail extends \Buckaroo\Magento2\Block\Order\TotalsEmail
{
    /**
     * @inheritdoc
     */
    public function initTotals()
    {
        $creditmemo = $this->getParentBlock()->getCreditmemo();
        $this->addBuckarooFeeTotals($creditmemo);
    }
}
