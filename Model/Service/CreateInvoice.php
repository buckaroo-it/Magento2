<?php

namespace Buckaroo\Magento2\Model\Service;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Invoice;
use Buckaroo\Magento2\Logging\Log;
use Buckaroo\Magento2\Model\ConfigProvider\Account;
use Buckaroo\Magento2\Helper\PaymentGroupTransaction;
use Magento\Sales\Model\Order\Email\Sender\InvoiceSender;

class CreateInvoice
{
    /**
     * @var Log
     */
    protected Log $logger;

    /**
     * @var Account
     */
    private Account $configAccount;

    /**
     * @var PaymentGroupTransaction
     */
    private PaymentGroupTransaction $groupTransaction;

    /**
     * @var InvoiceSender
     */
    private InvoiceSender $invoiceSender;

    /**
     * @param Account $configAccount
     * @param Log $logger
     * @param PaymentGroupTransaction $groupTransaction
     * @param InvoiceSender $invoiceSender
     */
    public function __construct(
        Account $configAccount,
        Log $logger,
        PaymentGroupTransaction $groupTransaction,
        InvoiceSender $invoiceSender
    ) {
        $this->logger = $logger;
        $this->groupTransaction = $groupTransaction;
        $this->invoiceSender = $invoiceSender;
        $this->configAccount = $configAccount;
    }

    /**
     * Create invoice after shipment for all buckaroo payment methods
     *
     * @param Order $order
     * @return bool
     * @throws \Exception
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     */
    public function createInvoiceGeneralSetting(Order $order): bool
    {
        $payment = $order->getPayment();
        $this->logger->addDebug(__METHOD__ . '|1| - Save Invoice');

        if (!$order->canInvoice() || $order->hasInvoices()) {
            $this->logger->addDebug(__METHOD__ . '|2| - Order can not be invoiced');

            return false;
        }

        //Fix for suspected fraud when the order currency does not match with the payment's currency
        $amount = ($payment->isSameCurrency()
            && $payment->isCaptureFinal($order->getGrandTotal())) ?
            $order->getGrandTotal() : $order->getBaseTotalDue();
        $payment->registerCaptureNotification($amount);
        $payment->save();

        $transactionKey = (string)$payment->getAdditionalInformation(
            BuckarooAdapter::BUCKAROO_ORIGINAL_TRANSACTION_KEY_KEY
        );

        if (strlen($transactionKey) <= 0) {
            return true;
        }

        /** @var Invoice $invoice */
        foreach ($order->getInvoiceCollection() as $invoice) {
            $invoice->setTransactionId($transactionKey)->save();

            if ($this->groupTransaction->isGroupTransaction($order->getIncrementId())) {
                $this->logger->addDebug(__METHOD__ . '|3| - Set invoice state PAID group transaction');
                $invoice->setState(Invoice::STATE_PAID);
            }

            if (!$invoice->getEmailSent() && $this->configAccount->getInvoiceEmail($order->getStore())) {
                $this->logger->addDebug(__METHOD__ . '|4| - Send Invoice Email');
                $this->invoiceSender->send($invoice, true);
            }
        }

        $order->setIsInProcess(true);
        $order->save();

        return true;
    }
}