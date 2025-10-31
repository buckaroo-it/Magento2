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

namespace Buckaroo\Magento2\Service;

use Buckaroo\Magento2\Helper\PaymentGroupTransaction;
use Magento\Sales\Model\Order;

class PayReminderService
{
    /**
     * @var float
     */
    private $payRemainder;

    /**
     * @var string
     */
    private $serviceAction;

    /**
     * @var float
     */
    private $alreadyPaid;

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
     * @param  Order $order
     * @return bool
     */
    public function isPayRemainder(Order $order): bool
    {
        $alreadyPaid = $this->getAlreadyPaid($order->getIncrementId());
        if ($alreadyPaid > 0) {
            return true;
        }
        return false;
    }

    /**
     * Get the amount already paid by partial payment method (giftcard, voucher)
     *
     * @param  string|null $incrementId
     * @return float
     */
    public function getAlreadyPaid(?string $incrementId = null): float
    {
        if (empty($this->alreadyPaid)) {
            $this->setAlreadyPaid($this->paymentGroupTransaction->getAlreadyPaid($incrementId));
        }

        return $this->alreadyPaid;
    }

    /**
     * Set the amount already paid
     *
     * @param  float $alreadyPaid
     * @return $this
     */
    public function setAlreadyPaid(float $alreadyPaid): PayReminderService
    {
        $this->alreadyPaid = $alreadyPaid;
        return $this;
    }

    /**
     * If we have already paid some value we do a pay reminder request
     *
     * @param  Order $order
     * @return float
     */
    public function getPayRemainder(Order $order): float
    {
        if (empty($this->payRemainder)) {
            $alreadyPaid = $this->getAlreadyPaid($order->getIncrementId());

            if ($alreadyPaid > 0) {
                $this->setPayRemainder($this->getPayRemainderAmount((float)$order->getGrandTotal(), $alreadyPaid));
            }
        }

        return $this->payRemainder;
    }

    /**
     * Set the amount that should be paid
     *
     * @param mixed $payRemainder
     */
    public function setPayRemainder($payRemainder): PayReminderService
    {
        $this->payRemainder = $payRemainder;

        return $this;
    }

    /**
     * Get pay remainder amount
     *
     * @param  float $total
     * @param  float $alreadyPaid
     * @return float
     */
    private function getPayRemainderAmount(float $total, float $alreadyPaid): float
    {
        return $total - $alreadyPaid;
    }

    /**
     * Get original transaction key by order
     *
     * @param  Order       $order
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
     * @param  string $incrementId
     * @param  string $serviceAction
     * @param  string $newServiceAction
     * @return string
     */
    public function getServiceAction(
        string $incrementId,
        string $serviceAction = 'pay',
        string $newServiceAction = 'payRemainder'
    ): string {
        if (empty($this->serviceAction)) {
            $alreadyPaid = $this->getAlreadyPaid($incrementId);

            if ($alreadyPaid > 0) {
                $serviceAction = $newServiceAction;
            }

            $this->setServiceAction($serviceAction);
        }

        return $this->serviceAction;
    }

    /**
     * Set service action
     *
     * @param string $serviceAction
     */
    public function setServiceAction(string $serviceAction): void
    {
        $this->serviceAction = $serviceAction;
    }
}
