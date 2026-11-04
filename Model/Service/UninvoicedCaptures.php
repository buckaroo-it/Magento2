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

namespace Buckaroo\Magento2\Model\Service;

use Buckaroo\Magento2\Model\Config\Source\InvoiceHandlingOptions;
use Buckaroo\Magento2\Model\Method\BuckarooAdapter;
use Magento\Framework\Exception\LocalizedException;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Payment;

/**
 * Money Buckaroo has captured for an order that no invoice accounts for yet.
 *
 * With "create invoice after shipment" a capture can arrive before the order ships - a capture
 * made in Buckaroo Plaza, or a capture whose invoice was never saved. The push records it here,
 * and the invoices created later draw on it instead of capturing the same money a second time.
 *
 * Kept in the order currency, the currency Buckaroo captures and invoices are totalled in.
 */
class UninvoicedCaptures
{
    /**
     * Payment additional information key: ['amount' => float, 'transaction_key' => string]
     */
    public const ADDITIONAL_INFORMATION_KEY = 'buckaroo_uninvoiced_captures';

    /**
     * Invoice data key: the part of the invoice still to be captured, when the rest is covered.
     */
    public const INVOICE_CAPTURE_REMAINDER = 'buckaroo_capture_remainder';

    /**
     * Record a capture that no invoice accounts for.
     *
     * @param Payment $payment
     * @param string $transactionKey
     * @param float $amount Order currency
     *
     * @return void
     * @throws LocalizedException
     */
    public function record(Payment $payment, string $transactionKey, float $amount): void
    {
        if ($amount <= 0) {
            return;
        }

        $current = $this->read($payment);

        $payment->setAdditionalInformation(self::ADDITIONAL_INFORMATION_KEY, [
            'amount' => round($current['amount'] + $amount, 2),
            'transaction_key' => $transactionKey !== '' ? $transactionKey : $current['transaction_key'],
        ]);
    }

    /**
     * The captured amount no invoice accounts for yet.
     *
     * @param Order $order
     *
     * @return float Order currency
     */
    public function getAmount(Order $order): float
    {
        return $this->resolve($order)['amount'];
    }

    /**
     * Transaction key of the most recent capture recorded here.
     *
     * @param Order $order
     *
     * @return string
     */
    public function getTransactionKey(Order $order): string
    {
        return $this->resolve($order)['transaction_key'];
    }

    /**
     * Mark part of the recorded amount as invoiced.
     *
     * @param Order $order
     * @param float $amount Order currency
     *
     * @return void
     */
    public function consume(Order $order, float $amount): void
    {
        $payment = $this->paymentOf($order);
        if ($payment === null) {
            return;
        }

        $current = $this->resolve($order);

        $payment->setAdditionalInformation(self::ADDITIONAL_INFORMATION_KEY, [
            'amount' => max(0.0, round($current['amount'] - $amount, 2)),
            'transaction_key' => $current['transaction_key'],
        ]);
    }

    /**
     * The stored value, to put back with restore() when an invoice is not saved after all.
     *
     * @param Order $order
     *
     * @return mixed
     */
    public function snapshot(Order $order)
    {
        $payment = $this->paymentOf($order);

        return $payment ? $payment->getAdditionalInformation(self::ADDITIONAL_INFORMATION_KEY) : null;
    }

    /**
     * Put back a value taken with snapshot().
     *
     * @param Order $order
     * @param mixed $snapshot
     *
     * @return void
     */
    public function restore(Order $order, $snapshot): void
    {
        $payment = $this->paymentOf($order);
        if ($payment === null) {
            return;
        }

        if ($snapshot === null) {
            $payment->unsAdditionalInformation(self::ADDITIONAL_INFORMATION_KEY);
            return;
        }

        $payment->setAdditionalInformation(self::ADDITIONAL_INFORMATION_KEY, $snapshot);
    }

    /**
     * Read the stored value, falling back to what an older plugin version left on the order.
     *
     * @param Order $order
     *
     * @return array{amount: float, transaction_key: string}
     * @throws LocalizedException
     */
    private function resolve(Order $order): array
    {
        $payment = $this->paymentOf($order);
        if ($payment === null) {
            return ['amount' => 0.0, 'transaction_key' => ''];
        }

        if ($payment->getAdditionalInformation(self::ADDITIONAL_INFORMATION_KEY) !== null) {
            return $this->read($payment);
        }

        $legacy = $this->readLegacy($order, $payment);
        if ($legacy['amount'] > 0) {
            // Stored now: the fields it is derived from no longer hold it once an invoice is paid.
            $payment->setAdditionalInformation(self::ADDITIONAL_INFORMATION_KEY, $legacy);
        }

        return $legacy;
    }

    /**
     * Read the stored value.
     *
     * @param Payment $payment
     *
     * @return array{amount: float, transaction_key: string}
     */
    private function read(Payment $payment): array
    {
        $stored = $payment->getAdditionalInformation(self::ADDITIONAL_INFORMATION_KEY);

        return [
            'amount' => is_array($stored) ? round((float)($stored['amount'] ?? 0), 2) : 0.0,
            'transaction_key' => is_array($stored) ? (string)($stored['transaction_key'] ?? '') : '',
        ];
    }

    /**
     * A capture recorded before this record existed.
     *
     * Older versions put it on the payment's paid amount only, leaving the order's paid total at
     * zero. Magento rewrites the payment's paid amount when the first invoice is paid, so this is
     * only readable while no invoice has paid anything.
     *
     * @param Order   $order
     * @param Payment $payment
     *
     * @return array{amount: float, transaction_key: string}
     */
    private function readLegacy(Order $order, Payment $payment): array
    {
        $none = ['amount' => 0.0, 'transaction_key' => ''];

        $transactionKey = (string)$payment->getAdditionalInformation(
            BuckarooAdapter::BUCKAROO_CAPTURE_TRANSACTION_KEY
        );

        if ($transactionKey === ''
            || !$payment->getAdditionalInformation(BuckarooAdapter::BUCKAROO_ALREADY_CAPTURED)
            || $payment->getAdditionalInformation(InvoiceHandlingOptions::INVOICE_HANDLING)
                != InvoiceHandlingOptions::SHIPMENT
            || round((float)$order->getTotalPaid(), 2) > 0
        ) {
            return $none;
        }

        $amount = round((float)$payment->getAmountPaid(), 2);

        return $amount > 0 ? ['amount' => $amount, 'transaction_key' => $transactionKey] : $none;
    }

    /**
     * The order's payment, as the model that carries additional information.
     *
     * @param Order $order
     *
     * @return Payment|null
     */
    private function paymentOf(Order $order): ?Payment
    {
        $payment = $order->getPayment();

        return $payment instanceof Payment ? $payment : null;
    }
}
