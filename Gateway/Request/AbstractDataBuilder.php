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
    protected Order $order;

    /**
     * @var InfoInterface
     */
    protected InfoInterface $payment;

    /**
     * Initializes the builder with the provided build subject data.
     *
     * @param array $buildSubject
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
     * @return $this
     */
    public function setOrder(Order $order): AbstractDataBuilder
    {
        $this->order = $order;

        return $this;
    }
}
