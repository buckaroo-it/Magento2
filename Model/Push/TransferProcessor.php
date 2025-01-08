<?php

namespace Buckaroo\Magento2\Model\Push;

use Buckaroo\Magento2\Model\BuckarooStatusCode;
use Buckaroo\Magento2\Model\ConfigProvider\Method\PayPerEmail;
use Buckaroo\Magento2\Model\ConfigProvider\Method\SepaDirectDebit;
use Buckaroo\Magento2\Model\ConfigProvider\Method\Transfer;
use Magento\Framework\Exception\LocalizedException;
use Magento\Sales\Model\Order;

class TransferProcessor extends DefaultProcessor
{
    protected function getPaymentDetails($message)
    {
        // Set amount
        $amount = $this->order->getTotalDue();
        if (!empty($this->pushRequest->getAmount())) {
            $amount = floatval($this->pushRequest->getAmount());
        }

        /**
         * force state eventhough this can lead to a transition of the order
         * like new -> processing
         */
        $forceState = false;
        $this->dontSaveOrderUponSuccessPush = false;

        if ($this->canPushInvoice()) {
            $description = 'Payment status : <strong>' . $message . "</strong><br/>";
            if ($this->pushRequest->hasPostData('transaction_method', 'transfer')) {
                $description .= 'Amount of ' . $this->order->getBaseCurrency()->formatTxt($amount) . ' has been paid';
            }
        } else {
            $description = 'Authorization status : <strong>' . $message . "</strong><br/>";
            $description .= 'Total amount of ' . $this->order->getBaseCurrency()->formatTxt($amount)
                . ' has been authorized. Please create an invoice to capture the authorized amount.';
            $forceState = true;
        }

        return [
            'amount'      => $amount,
            'description' => $description,
            'forceState'  => $forceState
        ];
    }

    /**
     * Create invoice only in case of full or last remained amount
     *
     * @param array $paymentDetails
     * @return bool
     * @throws LocalizedException
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

        $saveInvoice = true;

        if (($paymentDetails['amount'] < $this->order->getTotalDue())
            || (($paymentDetails['amount'] == $this->order->getTotalDue()) && ($this->order->getTotalPaid() > 0))
        ) {
            $paymentDetails['forceState'] = true;
            if ($amount < $this->order->getTotalDue()) {
                $paymentDetails['state'] = Order::STATE_NEW;
                $paymentDetails['newStatus'] = $this->orderStatusFactory->get(
                    BuckarooStatusCode::PENDING_PROCESSING,
                    $this->order
                );
                $saveInvoice = false;
            }

            $this->order->setTotalDue($this->order->getTotalDue() - $amount);
            $this->order->setBaseTotalDue($this->order->getTotalDue() - $amount);

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

            $this->orderRequestService->updateTotalOnOrder($this->order);
        }

        return $saveInvoice;
    }

    /**
     * @inheritdoc
     */
    protected function canProcessPendingPush(): bool
    {
        return true;
    }

    /**
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
     * @return void
     */
    protected function setOrderStatusMessage(): void
    {
        if (!empty($this->pushRequest->getStatusmessage())) {
            $this->order->addStatusHistoryComment($this->pushRequest->getStatusmessage());
        }
    }
}
