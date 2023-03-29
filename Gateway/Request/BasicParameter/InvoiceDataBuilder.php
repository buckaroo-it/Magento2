<?php

namespace Buckaroo\Magento2\Gateway\Request\BasicParameter;

use Magento\Payment\Gateway\Data\PaymentDataObjectInterface;
use Magento\Payment\Gateway\Request\BuilderInterface;
use Magento\Sales\Model\Order;

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


    public function build(array $buildSubject)
    {
        if (
            !isset($buildSubject['payment'])
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
     * @return int
     */
    public function getInvoiceId()
    {
        $order = $this->getOrder();

        if (
            empty($this->invoiceId) || (!$this->isCustomInvoiceId && ($this->invoiceId != $order->getIncrementId()))
        ) {
            $this->setInvoiceId($order->getIncrementId(), false);
        }

        return $this->invoiceId;
    }

    /**
     * @param string $invoiceId
     *
     * @return $this
     */
    public function setInvoiceId($invoiceId, $isCustomInvoiceId = true)
    {
        $this->invoiceId = $invoiceId;
        $this->isCustomInvoiceId = $isCustomInvoiceId;

        return $this;
    }

    /**
     * @return Order
     */
    public function getOrder()
    {
        return $this->order;
    }

    /**
     * @inheritdoc
     */
    public function setOrder($order)
    {
        $this->order = $order;

        return $this;
    }
}
