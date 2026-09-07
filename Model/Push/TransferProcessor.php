<?php

namespace Buckaroo\Magento2\Model\Push;

use Buckaroo\Magento2\Model\BuckarooStatusCode;
use Buckaroo\Magento2\Model\Config\Source\InvoiceHandlingOptions;
use Buckaroo\Magento2\Model\ConfigProvider\Method\PayPerEmail;
use Buckaroo\Magento2\Model\ConfigProvider\Method\SepaDirectDebit;
use Buckaroo\Magento2\Model\ConfigProvider\Method\Transfer;
use Magento\Framework\Exception\LocalizedException;
use Magento\Sales\Model\Order;

class TransferProcessor extends DefaultProcessor
{
    /**
     * Get the payment details (amount, description) for the push.
     *
     * @param string $message
     * @return array
     * @throws LocalizedException
     */
    protected function getPaymentDetails($message)
    {
        // Set amount
        $amount = $this->order->getTotalDue();
        $amountCurrency = $this->order->getOrderCurrencyCode();
        if (!empty($this->pushRequest->getAmount())) {
            $amount = floatval($this->pushRequest->getAmount());
            $amountCurrency = $this->getPaymentCurrencyCode();
        }

        $this->dontSaveOrderUponSuccessPush = false;

        if ($this->canPushInvoice()) {
            $description = 'Payment status : <strong>' . $message . "</strong><br/>";
            if ($this->pushRequest->hasPostData('transaction_method', 'transfer')) {
                $description .= 'Amount of ' . $this->formatCommentAmount($amount, $amountCurrency) . ' has been paid';
            }
        } else {
            $description = 'Authorization status : <strong>' . $message . "</strong><br/>";
            $description .= 'Total amount of ' . $this->formatCommentAmount($amount, $amountCurrency)
                . ' has been authorized. Please create an invoice to capture the authorized amount.';
        }

        return [
            'amount'      => $amount,
            'description' => $description
        ];
    }

    /**
     * Create invoice only in case of full or last remained amount
     *
     * @param array $paymentDetails
     *
     * @throws LocalizedException
     *
     * @return bool
     */
    protected function invoiceShouldBeSaved(array &$paymentDetails): bool
    {
        $amount = $paymentDetails['amount'];

        $this->logger->addDebug(sprintf(
            '[PUSH - Transfer] | [Webapi] | [%s:%s] - Update totals by amount from request | order: %s',
            __METHOD__,
            __LINE__,
            var_export([
                'orderId' => $this->order->getId(),
                'totalDue' => $this->order->getTotalDue(),
                'totalPaid' => $this->order->getTotalPaid(),
                'amount' => $amount,
            ], true)
        ));

        if ($this->settlesOrderWithInvoiceOnPayment((float)$amount)) {
            return true;
        }

        $saveInvoice = true;

        if (($paymentDetails['amount'] < $this->order->getTotalDue())
            || (($paymentDetails['amount'] == $this->order->getTotalDue()) && ($this->order->getTotalPaid() > 0))
        ) {
            if ($amount < $this->order->getTotalDue()) {
                $paymentDetails['state'] = Order::STATE_NEW;
                $paymentDetails['newStatus'] = $this->orderStatusFactory->get(
                    BuckarooStatusCode::PENDING_PROCESSING,
                    $this->order
                );
                $saveInvoice = false;
            }

            $this->order->setTotalDue($this->order->getTotalDue() - $amount);
            $this->order->setBaseTotalDue($this->order->getBaseTotalDue() - $amount);

            $totalPaid = $this->order->getTotalPaid() + $amount;
            $this->order->setTotalPaid(
                $totalPaid > $this->order->getGrandTotal() ? $this->order->getGrandTotal() : $totalPaid
            );

            $baseTotalPaid = $this->order->getBaseTotalPaid() + $amount;
            $this->order->setBaseTotalPaid(
                $baseTotalPaid > $this->order->getBaseGrandTotal() ?
                    $this->order->getBaseGrandTotal() : $baseTotalPaid
            );

            $this->orderRequestService->saveAndReloadOrder();
        }

        return $saveInvoice;
    }

    /**
     * Whether this payment settles the order while invoices are created on payment.
     *
     * Magento only creates an invoice for a capture it considers final, and it decides that by
     * comparing the captured amount against the order's base total due. Deducting the instalment
     * from the running totals first makes the payment that settles the order read as a partial
     * capture, so Magento flags the payment as fraudulent and creates no invoice at all. When the
     * Invoice Handling setting asks for an invoice on payment, the totals are therefore left to
     * registerCaptureNotification(); on shipment the running totals are still updated here,
     * because no invoice is created to account for the payment.
     *
     * @param float $amount
     *
     * @throws LocalizedException
     *
     * @return bool
     */
    private function settlesOrderWithInvoiceOnPayment(float $amount): bool
    {
        return $amount >= (float)$this->order->getTotalDue()
            && $this->detectInvoiceHandlingMode() == InvoiceHandlingOptions::PAYMENT;
    }

    /**
     * Bank transfer has no authorize/capture flow, so a transfer push is never a capture.
     *
     * @return bool
     */
    protected function isCaptureTransaction(): bool
    {
        return false;
    }

    /**
     * @inheritdoc
     */
    protected function canProcessPendingPush(): bool
    {
        return true;
    }

    /**
     * Get the transfer payment details from the push request.
     *
     * @return array
     */
    protected function getTransferDetails(): array
    {
        return [
            'transfer_amount' => $this->pushRequest->getAmount(),
            'transfer_paymentreference' => $this->pushRequest->getServiceTransferPaymentreference(),
            'transfer_accountholdername' => $this->pushRequest->getServiceTransferAccountholdername(),
            'transfer_iban' => $this->pushRequest->getServiceTransferIban(),
            'transfer_bic' => $this->pushRequest->getServiceTransferBic(),
        ];
    }

    /**
     * @inheritdoc
     */
    protected function getSpecificPaymentDetails(): array
    {
        return [
            'customer_account_name' => $this->pushRequest->getServiceTransferCustomeraccountname(),
            'customer_bic' => $this->pushRequest->getServiceTransferCustomerbic(),
            'customer_iban' => $this->pushRequest->getServiceTransferCustomeriban()
        ];
    }

    /**
     * Add the push status message to the order status history.
     *
     * @return void
     */
    protected function setOrderStatusMessage(): void
    {
        if (!empty($this->pushRequest->getStatusMessage())) {
            $this->order->addCommentToStatusHistory($this->pushRequest->getStatusMessage());
        }
    }
}
