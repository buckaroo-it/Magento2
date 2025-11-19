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

namespace Buckaroo\Magento2\Model\Method;

/**
 * Buckaroo Remainder Adapter with Partial Refund Support
 *
 * This class extends the BuckarooAdapter to enable partial refunds for all
 * payment methods that use remainder/partial payment functionality.
 *
 * This replaces the need for individual payment method classes and provides
 * a centralized solution for enabling partial refunds across all remainder
 * payment methods (MBWay, Belfius, Blik, EPS, KBC, Payconiq, Voucher,
 * Multibanco, Knaken, etc.).
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
