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

namespace Buckaroo\Magento2\Gateway\Request;

use Magento\Payment\Gateway\Data\PaymentDataObjectInterface;
use Magento\Payment\Gateway\Request\BuilderInterface;
use Magento\Payment\Model\InfoInterface;
use Magento\Sales\Model\Order;

/**
 * @SuppressWarnings(PHPMD.NumberOfChildren)
 */
abstract class AbstractDataBuilder implements BuilderInterface
{
    /**
     * @var Order
     */
    protected $order;

    /**
     * @var InfoInterface
     */
    protected $payment;

    /**
     * Initializes the builder with the provided build subject data.
     *
     * @param array $buildSubject
     *
     * @return array
     */
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
     * Retrieves the payment
     *
     * @return InfoInterface
     */
    public function getPayment(): InfoInterface
    {
        return $this->payment;
    }

    /**
     * Sets the payment
     *
     * @param InfoInterface $payment
     *
     * @return $this
     */
    public function setPayment(InfoInterface $payment): AbstractDataBuilder
    {
        $this->payment = $payment;

        return $this;
    }

    /**
     * Retrieves the order associated with the payment.
     *
     * @return Order
     */
    public function getOrder(): Order
    {
        return $this->order;
    }

    /**
     * Sets the order associated with the payment.
     *
     * @param Order $order
     *
     * @return $this
     */
    public function setOrder(Order $order): AbstractDataBuilder
    {
        $this->order = $order;

        return $this;
    }
}
