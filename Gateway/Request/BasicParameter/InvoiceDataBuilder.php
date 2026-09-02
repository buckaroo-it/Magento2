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

namespace Buckaroo\Magento2\Gateway\Request\BasicParameter;

use Magento\Payment\Gateway\Request\BuilderInterface;
use Magento\Sales\Model\Order;
use Buckaroo\Magento2\Gateway\Helper\SubjectReader;

class InvoiceDataBuilder implements BuilderInterface
{
    /**
     * @var Order
     */
    private $order;

    /**
     * @var bool
     */
    private $isCustomInvoiceId = false;

    /**
     * @var string
     */
    private $invoiceId;

    /**
     * @inheritdoc
     */
    public function build(array $buildSubject): array
    {
        $paymentDO = SubjectReader::readPayment($buildSubject);
        $this->setOrder($paymentDO->getOrder()->getOrder());

        return [
            'invoice' => $this->getInvoiceId(),
            'order'   => $this->getOrder()->getIncrementId()
        ];
    }

    /**
     * Get order
     *
     * @return Order
     */
    public function getOrder(): Order
    {
        return $this->order;
    }

    /**
     * Set order
     *
     * @param Order $order
     *
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

        if (empty($this->invoiceId)
            || (!$this->isCustomInvoiceId && ($this->invoiceId != $order->getIncrementId()))
        ) {
            $this->setInvoiceId($order->getIncrementId(), false);
        }

        return $this->invoiceId;
    }

    /**
     * Set invoice id
     *
     * @param string $invoiceId
     * @param bool   $isCustomInvoiceId
     *
     * @return $this
     */
    public function setInvoiceId(string $invoiceId, bool $isCustomInvoiceId = true): InvoiceDataBuilder
    {
        $this->invoiceId = $invoiceId;
        $this->isCustomInvoiceId = $isCustomInvoiceId;

        return $this;
    }
}
