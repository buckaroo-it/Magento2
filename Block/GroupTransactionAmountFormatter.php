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

namespace Buckaroo\Magento2\Block;

/**
 * Formats group transaction (giftcard, voucher, remainder) amounts for the payment info blocks
 */
trait GroupTransactionAmountFormatter
{
    /**
     * Format a group transaction amount in its own currency (the order currency), without converting it
     *
     * @param float|string $amount
     * @param string|null  $currency
     *
     * @return string
     */
    public function formatGroupTransactionAmount($amount, ?string $currency = null): string
    {
        $amount = (float)$amount;
        $order = $this->getInfo()->getOrder();
        $orderCurrency = $order ? (string)$order->getOrderCurrencyCode() : '';

        if ($order && $orderCurrency !== ''
            && ($currency === null || $currency === '' || strtoupper($currency) === strtoupper($orderCurrency))
        ) {
            return $order->getOrderCurrency()->formatPrecision($amount, 2, [], false);
        }

        return trim(strtoupper((string)($currency ?: $orderCurrency)) . ' ' . number_format($amount, 2));
    }
}
