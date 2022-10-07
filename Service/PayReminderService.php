<?php

namespace Buckaroo\Magento2\Service;

use Buckaroo\Magento2\Helper\PaymentGroupTransaction;
use Magento\Sales\Model\Order;

class PayReminderService
{
    private float $payRemainder;
    private string $serviceAction;
    private float $alreadyPaid;
    private Order $order;

    private PaymentGroupTransaction $paymentGroupTransaction;

    /**
     * Constructor
     *
     * @param PaymentGroupTransaction $paymentGroupTransaction
     * @param Order|null $order
     */
    public function __construct(
        PaymentGroupTransaction $paymentGroupTransaction,
        Order                   $order = null
    )
    {
        if($order instanceof Order) $this->setOrder($order);
        $this->paymentGroupTransaction = $paymentGroupTransaction;
    }

    /**
     * @param $incrementId
     * @return float
     */
    public function getAlreadyPaid($incrementId = null): float
    {
        if (empty($this->alreadyPaid)) {
            if (empty($incrementId) && isset($this->order) && $this->order instanceof Order) {
                $incrementId = $this->order->getIncrementId();
            }
            $this->setAlreadyPaid($this->paymentGroupTransaction->getAlreadyPaid($incrementId));
        }

        return $this->alreadyPaid;
    }

    /**
     * @param float $alreadyPaid
     * @return $this
     */
    public function setAlreadyPaid(float $alreadyPaid): PayReminderService
    {
        $this->alreadyPaid = $alreadyPaid;
        return $this;
    }

    /**
     * @param Order $order
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
     * If we have already paid some value we do a pay reminder request
     *
     * @param Order $order
     * @return float
     */
    public function getPayRemainder(Order $order): float
    {
        if (empty($this->payRemainder)) {
            $alreadyPaid = $this->getAlreadyPaid($order->getIncrementId());

            if ($alreadyPaid > 0) {
                $this->setPayRemainder($this->getPayRemainderAmount($order->getGrandTotal(), $alreadyPaid));
            }
        }

        return (float)$this->payRemainder;
    }

    /**
     * @param mixed $payRemainder
     */
    public function setPayRemainder($payRemainder): PayReminderService
    {
        $this->payRemainder = $payRemainder;

        return $this;
    }

    private function getPayRemainderAmount($total, $alreadyPaid)
    {
        return $total - $alreadyPaid;
    }


    /**
     * @param Order $order
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
     * @param string $incrementId
     * @param string $serviceAction
     * @param string $newServiceAction
     * @return string
     */
    public function getServiceAction(string $incrementId, string $serviceAction = 'pay', string $newServiceAction = 'payRemainder'): string
    {
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
     * @param string $serviceAction
     */
    public function setServiceAction(string $serviceAction): void
    {
        $this->serviceAction = $serviceAction;
    }

    /**
     * @return Order
     */
    public function getOrder(): Order
    {
        return $this->order;
    }

    /**
     * @param Order $order
     */
    public function setOrder(Order $order): void
    {
        $this->order = $order;
    }
}
