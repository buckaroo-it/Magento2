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

namespace Buckaroo\Magento2\Service;

use Buckaroo\Magento2\Helper\PaymentGroupTransaction;
use Magento\Sales\Model\Order;

/**
 * Shared instance: all memoized state is keyed by order increment id so that
 * requests touching multiple orders (PayLink batches, mass refunds, API flows)
 * never leak one order's remainder or service action into another.
 */
class PayReminderService
{
    /**
     * @var float[] keyed by order increment id
     */
    private $payRemainder = [];

    /**
     * @var string[] keyed by order increment id
     */
    private $serviceAction = [];

    /**
     * @var float[] keyed by order increment id
     */
    private $alreadyPaid = [];

    /**
     * @var PaymentGroupTransaction
     */
    private $paymentGroupTransaction;

    /**
     * Constructor
     *
     * @param PaymentGroupTransaction $paymentGroupTransaction
     */
    public function __construct(
        PaymentGroupTransaction $paymentGroupTransaction
    ) {
        $this->paymentGroupTransaction = $paymentGroupTransaction;
    }

    /**
     * Check if is a pay remainder order
     *
     * @param Order $order
     *
     * @return bool
     */
    public function isPayRemainder(Order $order): bool
    {
        return $this->getAlreadyPaid($order->getIncrementId()) > 0;
    }

    /**
     * Get the amount already paid by partial payment method (giftcard, voucher)
     *
     * @param string|null $incrementId
     *
     * @return float
     */
    public function getAlreadyPaid(?string $incrementId = null): float
    {
        $key = (string)$incrementId;

        if (!isset($this->alreadyPaid[$key])) {
            $this->setAlreadyPaid($this->paymentGroupTransaction->getAlreadyPaid($incrementId), $incrementId);
        }

        return $this->alreadyPaid[$key];
    }

    /**
     * Set the amount already paid for the given order
     *
     * @param float $alreadyPaid
     * @param string|null $incrementId
     *
     * @return $this
     */
    public function setAlreadyPaid(float $alreadyPaid, ?string $incrementId = null): PayReminderService
    {
        $this->alreadyPaid[(string)$incrementId] = $alreadyPaid;
        return $this;
    }

    /**
     * If we have already paid some value we do a pay reminder request
     *
     * @param Order $order
     *
     * @return float
     */
    public function getPayRemainder(Order $order): float
    {
        $incrementId = (string)$order->getIncrementId();

        if (!isset($this->payRemainder[$incrementId])) {
            $alreadyPaid = $this->getAlreadyPaid($incrementId);

            $remainder = $alreadyPaid > 0
                ? $this->getPayRemainderAmount((float)$order->getGrandTotal(), $alreadyPaid)
                : 0.0;

            $this->setPayRemainder($remainder, $incrementId);
        }

        return $this->payRemainder[$incrementId];
    }

    /**
     * Set the amount that should be paid for the given order
     *
     * @param mixed $payRemainder
     * @param string|null $incrementId
     *
     * @return $this
     */
    public function setPayRemainder($payRemainder, ?string $incrementId = null): PayReminderService
    {
        $this->payRemainder[(string)$incrementId] = (float)$payRemainder;

        return $this;
    }

    /**
     * Get pay remainder amount
     *
     * @param float $total
     * @param float $alreadyPaid
     *
     * @return float
     */
    private function getPayRemainderAmount(float $total, float $alreadyPaid): float
    {
        return $total - $alreadyPaid;
    }

    /**
     * Get original transaction key by order
     *
     * @param Order $order
     *
     * @return string|null
     */
    public function getOriginalTransactionKey(Order $order): ?string
    {
        $alreadyPaid = $this->getAlreadyPaid($order->getIncrementId());

        if ($alreadyPaid > 0) {
            return $this->paymentGroupTransaction->getGroupTransactionOriginalTransactionKey($order->getIncrementId());
        }

        return null;
    }

    /**
     * Get payRemainder service action if already paid is positive
     *
     * @param string $incrementId
     * @param string $serviceAction
     * @param string $newServiceAction
     *
     * @return string
     */
    public function getServiceAction(
        string $incrementId,
        string $serviceAction = 'pay',
        string $newServiceAction = 'payRemainder'
    ): string {
        if (!isset($this->serviceAction[$incrementId])) {
            $alreadyPaid = $this->getAlreadyPaid($incrementId);

            if ($alreadyPaid > 0) {
                $serviceAction = $newServiceAction;
            }

            $this->setServiceAction($serviceAction, $incrementId);
        }

        return $this->serviceAction[$incrementId];
    }

    /**
     * Set service action for the given order
     *
     * @param string $serviceAction
     * @param string|null $incrementId
     */
    public function setServiceAction(string $serviceAction, ?string $incrementId = null): void
    {
        $this->serviceAction[(string)$incrementId] = $serviceAction;
    }
}
