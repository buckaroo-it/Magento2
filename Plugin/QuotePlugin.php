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

use Buckaroo\Magento2\Helper\PaymentGroupTransaction;
use Closure;
use Magento\Quote\Model\Quote;

class QuotePlugin
{
    /**
     * @var PaymentGroupTransaction
     */
    protected $groupTransaction;

    /**
     * Constructor.
     *
     * @param PaymentGroupTransaction $groupTransaction
     */
    public function __construct(
        PaymentGroupTransaction $groupTransaction
    ) {
        $this->groupTransaction = $groupTransaction;
    }

    /**
     * Around plugin for reserveOrderId method.
     *
     * @param Quote   $subject
     * @param Closure $proceed
     *
     * @return Quote
     */
    public function aroundReserveOrderId(Quote $subject, Closure $proceed)
    {
        $reservedOrderId = $subject->getReservedOrderId();

        // Preserve group transaction order IDs
        if ($this->groupTransaction->isGroupTransaction($reservedOrderId)) {
            return $subject;
        }

        // Preserve second chance order IDs (contain hyphen suffix like "000000230-1")
        // These are set when customer clicks second chance email link
        if ($reservedOrderId && strpos($reservedOrderId, '-') !== false) {
            return $subject;
        }

        return $proceed();
    }
}
