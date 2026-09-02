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

namespace Buckaroo\Magento2\Model\Method;

/**
 * Buckaroo Remainder Adapter with Partial Refund Support
 *
 * This class extends the BuckarooAdapter to enable partial refunds for all
 * payment methods that use remainder/partial payment functionality.
 *
 * This replaces the need for individual payment method classes and provides
 * a centralized solution for enabling partial refunds across all remainder
 * payment methods (MBWay, Belfius, Blik, EPS, KBC, Voucher,
 * Multibanco, etc.).
 */
class BuckarooRemainderAdapter extends BuckarooAdapter
{
    /**
     * Enable partial refunds per invoice for remainder payment methods
     *
     * This method allows the "Qty to refund" field to be editable in the
     * admin credit memo creation form for all payment methods using this adapter.
     *
     * @return bool
     */
    public function canRefundPartialPerInvoice(): bool
    {
        return true;
    }
}
