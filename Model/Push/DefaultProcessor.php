<?php

namespace Buckaroo\Magento2\Model\Push;

use Buckaroo\Magento2\Api\PushProcessorInterface;
use Buckaroo\Magento2\Api\PushRequestInterface;

class DefaultProcessor implements PushProcessorInterface
{
    /**
     * @var PushRequestInterface
     */
    public PushRequestInterface $pushRequest;

    public function processSucceded()
    {
        // TODO: Implement processSucceded() method.
    }

    public function processFailed()
    {
        // TODO: Implement processFailed() method.
    }

    public function processPush(PushRequestInterface $pushRequest): void
    {
        $this->pushRequest = $pushRequest;
    }

    /**
     * Get the order increment ID based on the invoice number or order number.
     *
     * @return string|false
     */
    protected function getOrderIncrementId()
    {
        $brqOrderId = false;

        if (!empty($this->pushRequest->getInvoiceNumber()) && strlen($this->pushRequest->getInvoiceNumber()) > 0) {
            $brqOrderId = $this->pushRequest->getInvoiceNumber();
        }

        if (!empty($this->pushRequest->getOrderNumber()) && strlen($this->pushRequest->getOrderNumber()) > 0) {
            $brqOrderId = $this->pushRequest->getOrderNumber();
        }

        return $brqOrderId;
    }
}