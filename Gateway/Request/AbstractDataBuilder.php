<?php

declare(strict_types=1);

namespace Buckaroo\Magento2\Gateway\Request;

use Magento\Payment\Gateway\Data\PaymentDataObjectInterface;
use Magento\Payment\Gateway\Request\BuilderInterface;
use Magento\Sales\Model\Order;
use Magento\Payment\Model\InfoInterface;

/**
 * @SuppressWarnings(PHPMD.NumberOfChildren)
 */
abstract class AbstractDataBuilder implements BuilderInterface
{
    protected Order $order;
    protected InfoInterface $payment;

    public function initialize(array $buildSubject): array
    {
        if (!isset($buildSubject['payment'])
            || !$buildSubject['payment'] instanceof PaymentDataObjectInterface
        ) {
            throw new \InvalidArgumentException('Payment data object should be provided');
        }

        $this->setPayment($buildSubject['payment']->getPayment());
        $this->setOrder($buildSubject['payment']->getOrder()->getOrder());

        return ['payment' => $this->getPayment(), 'order' => $this->getOrder()];
    }

    /**
     * @return Order
     */
    public function getOrder()
    {
        return $this->order;
    }

    /**
     * @param $order
     * @return $this
     */
    public function setOrder($order)
    {
        $this->order = $order;

        return $this;
    }

    /**
     * @return InfoInterface
     */
    public function getPayment()
    {
        return $this->payment;
    }

    /**
     * @param $payment
     * @return $this
     */
    public function setPayment($payment)
    {
        $this->payment = $payment;

        return $this;
    }
}
