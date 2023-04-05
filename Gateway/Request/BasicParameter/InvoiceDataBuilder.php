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

namespace Buckaroo\Magento2\Gateway\Request\BasicParameter;

use Magento\Payment\Gateway\Data\PaymentDataObjectInterface;
use Magento\Payment\Gateway\Request\BuilderInterface;
use Magento\Sales\Model\Order;

class InvoiceDataBuilder implements BuilderInterface
{
    /**
     * @var Order
     */
    private Order $order;

    /**
     * @var bool
     */
    private bool $isCustomInvoiceId = false;

    /**
     * @var string
     */
    private string $invoiceId;

    /**
     * @inheritdoc
     */
    public function build(array $buildSubject): array
    {
        if (!isset($buildSubject['payment'])
            || !$buildSubject['payment'] instanceof PaymentDataObjectInterface
        ) {
            throw new \InvalidArgumentException('Payment data object should be provided');
        }

        $payment = $buildSubject['payment'];
        $this->setOrder($payment->getOrder()->getOrder());

        return [
            'invoice' => $this->getInvoiceId(),
            'order' => $this->getOrder()->getIncrementId()
        ];
    }

    /**
     * Get order
     *
     * @return Order
     */
    public function getOrder()
    {
        return $this->order;
    }

    /**
     * Set order
     *
     * @param Order $order
     * @return $this
     */
    public function setOrder(Order $order): InvoiceDataBuilder
    {
        $this->order = $order;

        return $this;
    }

    /**
     * Get invoice id
     *
     * @return string
     */
    public function getInvoiceId(): string
    {
        $order = $this->getOrder();

        if (empty($this->invoiceId) || (!$this->isCustomInvoiceId && ($this->invoiceId != $order->getIncrementId()))
        ) {
            $this->setInvoiceId($order->getIncrementId(), false);
        }

        return $this->invoiceId;
    }

    /**
     * Set invoice id
     *
     * @param string $invoiceId
     * @param bool $isCustomInvoiceId
     * @return $this
     */
    public function setInvoiceId(string $invoiceId, bool $isCustomInvoiceId = true): InvoiceDataBuilder
    {
        $this->invoiceId = $invoiceId;
        $this->isCustomInvoiceId = $isCustomInvoiceId;

        return $this;
    }
}
