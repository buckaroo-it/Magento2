<?php

namespace Buckaroo\Magento2\Model\Service;
use Buckaroo\Magento2\Helper\Data;
use Buckaroo\Magento2\Model\Method\AbstractMethod;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Invoice;
use Buckaroo\Magento2\Logging\Log;
use Buckaroo\Magento2\Model\ConfigProvider\Account;
use Buckaroo\Magento2\Helper\PaymentGroupTransaction;
use Magento\Sales\Model\Order\Email\Sender\InvoiceSender;
use Magento\Sales\Model\Order\Payment\Transaction;

class CreateInvoice
{
    private Order $order;

    private Order\Payment $payment;
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
     * @var Data
     */
    private Data $helper;

    /**
     * @param Account $configAccount
     * @param Log $logger
     * @param PaymentGroupTransaction $groupTransaction
     * @param InvoiceSender $invoiceSender
     * @param Data $helper
     */
    public function __construct(
        Account $configAccount,
        Log $logger,
        PaymentGroupTransaction $groupTransaction,
        InvoiceSender $invoiceSender,
        Data $helper
    ) {
        $this->logger = $logger;
        $this->groupTransaction = $groupTransaction;
        $this->invoiceSender = $invoiceSender;
        $this->configAccount = $configAccount;
        $this->helper = $helper;
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
        $this->order = $order;
        $this->payment = $order->getPayment();

        $this->addTransactionData();

        $this->logger->addDebug(__METHOD__ . '|1| - Save Invoice');

        if (!$order->canInvoice() || $order->hasInvoices()) {
            $this->logger->addDebug(__METHOD__ . '|2| - Order can not be invoiced');

            return false;
        }

        //Fix for suspected fraud when the order currency does not match with the payment's currency
        $amount = ($this->payment->isSameCurrency()
            && $this->payment->isCaptureFinal($this->order->getGrandTotal())) ?
            $this->order->getGrandTotal() : $this->order->getBaseTotalDue();
        $this->payment->registerCaptureNotification($amount);
        $this->payment->save();

        $transactionKey = (string)$this->payment->getAdditionalInformation(
            AbstractMethod::BUCKAROO_ORIGINAL_TRANSACTION_KEY_KEY
        );

        if (strlen($transactionKey) <= 0) {
            return true;
        }

        /** @var Invoice $invoice */
        foreach ($this->order->getInvoiceCollection() as $invoice) {
            $invoice->setTransactionId($transactionKey)->save();

            if ($this->groupTransaction->isGroupTransaction($this->order->getIncrementId())) {
                $this->logger->addDebug(__METHOD__ . '|3| - Set invoice state PAID group transaction');
                $invoice->setState(Invoice::STATE_PAID);
            }

            if (!$invoice->getEmailSent() && $this->configAccount->getInvoiceEmail($this->order->getStore())) {
                $this->logger->addDebug(__METHOD__ . '|4| - Send Invoice Email');
                $this->invoiceSender->send($invoice, true);
            }
        }

        $this->order->setIsInProcess(true);
        $this->order->save();

        return true;
    }

    /**
     * @return Order\Payment
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function addTransactionData($transactionKey = false, $datas = false)
    {
        $transactionKey = $transactionKey ?: $this->payment->getAdditionalInformation(
            AbstractMethod::BUCKAROO_ORIGINAL_TRANSACTION_KEY_KEY
        );

        if (strlen($transactionKey) <= 0) {
            throw new \Buckaroo\Magento2\Exception(__('There was no transaction ID found'));
        }

        /**
         * Save the transaction's response as additional info for the transaction.
         */
        if(!$datas)
        {
            $rawDetails = $this->payment->getAdditionalInformation(Transaction::RAW_DETAILS);
            $rawInfo = $rawDetails[$transactionKey] ?? [];
        } else {
            $rawInfo  = $this->helper->getTransactionAdditionalInfo($datas);
        }

        /**
         * @noinspection PhpUndefinedMethodInspection
         */
        $this->payment->setTransactionAdditionalInfo(Transaction::RAW_DETAILS, $rawInfo);

        /**
         * Save the payment's transaction key.
         */
        $this->payment->setTransactionId($transactionKey . '-capture');

        $this->payment->setParentTransactionId($transactionKey);
        $this->payment->setAdditionalInformation(
            AbstractMethod::BUCKAROO_ORIGINAL_TRANSACTION_KEY_KEY,
            $transactionKey
        );

        return $this->payment;
    }
}