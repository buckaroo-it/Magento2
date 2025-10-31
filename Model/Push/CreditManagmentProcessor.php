<?php

namespace Buckaroo\Magento2\Model\Push;

use Buckaroo\Magento2\Api\Data\PushRequestInterface;
use Buckaroo\Magento2\Model\Method\BuckarooAdapter;
use Magento\Framework\Exception\LocalizedException;

class CreditManagmentProcessor extends DefaultProcessor
{
    /**
     * @inheritdoc
     *
     * @throws \Exception
     */
    public function processPush(PushRequestInterface $pushRequest): bool
    {
        $this->initializeFields($pushRequest);
        $invoiceKey = $this->pushRequest->getInvoicekey();
        $savedInvoiceKey = $this->order->getPayment()->getAdditionalInformation('buckaroo_cm3_invoice_key');

        if ($invoiceKey != $savedInvoiceKey) {
            return false;
        }

        if ($this->updateCm3InvoiceStatus()) {
            $this->sendCm3ConfirmationMail();
            return true;
        }
        return false;
    }

    /**
     * Update the Credit Management invoice status based on push request data and save invoice if required.
     *
     * @throws LocalizedException
     *
     * @return bool
     */
    private function updateCm3InvoiceStatus(): bool
    {
        $isPaid = filter_var(strtolower($this->pushRequest->getIspaid()), FILTER_VALIDATE_BOOLEAN);
        $canInvoice = ($this->order->canInvoice() && !$this->order->hasInvoices());

        $amount = floatval($this->pushRequest->getAmountDebit());
        $amount = $this->order->getBaseCurrency()->formatTxt($amount);
        $statusMessage = 'Payment push status : Creditmanagement invoice with a total amount of '
            . $amount . ' has been paid';

        if (!$isPaid && !$canInvoice) {
            $statusMessage = 'Payment push status : Creditmanagement invoice has been (partially) refunded';
        }

        if (!$isPaid && $canInvoice) {
            $statusMessage = 'Payment push status : Waiting for consumer';
        }

        if ($isPaid && $canInvoice) {
            $originalKey = BuckarooAdapter::BUCKAROO_ORIGINAL_TRANSACTION_KEY_KEY;
            $this->pushRequest->setTransactions($this->order->getPayment()->getAdditionalInformation($originalKey));
            $this->pushRequest->setAmount($this->pushRequest->getAmountDebit());

            if (!$this->saveInvoice()) {
                return false;
            }
        }

        $this->orderRequestService->updateOrderStatus(
            $this->order->getState(),
            $this->order->getStatus(),
            $statusMessage
        );

        return true;
    }

    /**
     * Sends the CM3 confirmation email if the CM3 status code is 10 and the order email has not been sent.
     *
     * @throws LocalizedException
     */
    private function sendCm3ConfirmationMail(): void
    {
        $store = $this->order->getStore();
        $cm3StatusCode = 0;

        if (!empty($this->pushRequest->getInvoicestatuscode())) {
            $cm3StatusCode = $this->pushRequest->getInvoicestatuscode();
        }

        $paymentMethod = $this->order->getPayment()->getMethodInstance();
        $configOrderMail = $this->configAccount->getOrderConfirmationEmail($store)
            || $paymentMethod->getConfigData('order_email', $store);

        if (!$this->order->getEmailSent() && $cm3StatusCode == 10 && $configOrderMail) {
            $this->orderRequestService->sendOrderEmail($this->order);
        }
    }
}
