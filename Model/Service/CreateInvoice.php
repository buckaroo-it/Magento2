<?php

namespace Buckaroo\Magento2\Model\Service;

use Buckaroo\Magento2\Exception;
use Buckaroo\Magento2\Helper\Data;
use Buckaroo\Magento2\Helper\PaymentGroupTransaction;
use Buckaroo\Magento2\Logging\Log;
use Buckaroo\Magento2\Model\ConfigProvider\Account;
use Buckaroo\Magento2\Model\Method\AbstractMethod;
use Magento\Framework\DB\TransactionFactory;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Registry;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Email\Sender\InvoiceSender;
use Magento\Sales\Model\Order\Invoice;
use Magento\Sales\Model\Order\Payment\Transaction;
use Magento\Sales\Model\Service\InvoiceService;

class CreateInvoice
{
    /**
     * @var Log
     */
    protected Log $logger;

    /**
     * @var Registry
     */
    protected $registry;

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
     * @var InvoiceService
     */
    private InvoiceService $invoiceService;

    /**
     * @var TransactionFactory
     */
    private TransactionFactory $transactionFactory;

    /**
     * @param Account $configAccount
     * @param Log $logger
     * @param PaymentGroupTransaction $groupTransaction
     * @param InvoiceSender $invoiceSender
     * @param InvoiceService $invoiceService
     * @param TransactionFactory $transactionFactory
     * @param Registry $registry
     * @param Data $helper
     */
    public function __construct(
        Account $configAccount,
        Log $logger,
        PaymentGroupTransaction $groupTransaction,
        InvoiceSender $invoiceSender,
        InvoiceService $invoiceService,
        TransactionFactory $transactionFactory,
        Registry $registry,
        Data $helper
    ) {
        $this->logger = $logger;
        $this->groupTransaction = $groupTransaction;
        $this->invoiceSender = $invoiceSender;
        $this->configAccount = $configAccount;
        $this->invoiceService = $invoiceService;
        $this->helper = $helper;
        $this->transactionFactory = $transactionFactory;
        $this->registry = $registry;
    }

    /**
     * Create invoice after shipment for all buckaroo payment methods
     *
     * @param Order $order
     * @param array $invoiceItems
     * @return bool
     * @throws LocalizedException
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     */
    public function createInvoiceGeneralSetting(Order $order, array $invoiceItems): bool
    {
        if (!$order->canInvoice()) {
            return true;
        }

        $data['capture_case'] = 'offline';
        if (empty($invoiceItems)) {
            $invoiceItems = $this->getInvoiceItems($order);
        }

        $data['capture_case'] = 'offline';

        $invoice = $this->invoiceService->prepareInvoice($order, $invoiceItems);

        if (!$invoice->getTotalQty()) {
            throw new LocalizedException(
                __("The invoice can't be created without products. Add products and try again.")
            );
        }

        $this->registry->register('current_invoice', $invoice);
        if (!empty($data['capture_case'])) {
            $invoice->setRequestedCaptureCase($data['capture_case']);
        }

        $invoice->register();

        $invoice->getOrder()->setCustomerNoteNotify(!empty($data['send_email']));
        $invoice->getOrder()->setIsInProcess(true);

        $transactionSave = $this->transactionFactory->create()
            ->addObject($invoice)
            ->addObject($invoice->getOrder());

        $transactionSave->save();

        $this->registry->unregister('current_invoice');

        $payment = $invoice->getOrder()->getPayment();

        $transactionKey = (string)$payment->getAdditionalInformation(
            AbstractMethod::BUCKAROO_ORIGINAL_TRANSACTION_KEY_KEY
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


        return true;
    }

    /**
     * Get Order Items that are not invoiced
     *
     * @param Order $order
     * @return array
     */
    public function getInvoiceItems(Order $order): array
    {
        $invoiceItems = [];

        foreach ($order->getAllItems() as $item) {
            if ($item->getQtyToInvoice() > 0 && !$item->getLockedDoInvoice()) {
                $invoiceItems[$item->getItemId()] = $item->getQtyToInvoice();
            }
        }

        return $invoiceItems;
    }

    /**
     * @return Order\Payment
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function addTransactionData($payment, $transactionKey = false, $datas = false)
    {
        $transactionKey = $transactionKey ?: $payment->getAdditionalInformation(
            AbstractMethod::BUCKAROO_ORIGINAL_TRANSACTION_KEY_KEY
        );

        if (strlen($transactionKey) <= 0) {
            throw new Exception(__('There was no transaction ID found'));
        }

        /**
         * Save the transaction's response as additional info for the transaction.
         */
        if (!$datas) {
            $rawDetails = $payment->getAdditionalInformation(Transaction::RAW_DETAILS);
            $rawInfo = $rawDetails[$transactionKey] ?? [];
        } else {
            $rawInfo = $this->helper->getTransactionAdditionalInfo($datas);
        }

        /**
         * @noinspection PhpUndefinedMethodInspection
         */
        $payment->setTransactionAdditionalInfo(Transaction::RAW_DETAILS, $rawInfo);

        /**
         * Save the payment's transaction key.
         */
        $payment->setTransactionId($transactionKey . '-capture');

        $payment->setParentTransactionId($transactionKey);
        $payment->setAdditionalInformation(
            AbstractMethod::BUCKAROO_ORIGINAL_TRANSACTION_KEY_KEY,
            $transactionKey
        );

        return $payment;
    }
}
