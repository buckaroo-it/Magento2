<?php

/**
 * NOTICE OF LICENSE
 *
 * This source file is subject to the MIT License
 * It is available through the world-wide-web at this URL:
 * https://tldrlegal.com/license/mit-license
 * If you are unable to obtain it through the world-wide-web, please send an email
 * to support@buckaroo.nl so we can send you a copy immediately.
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

namespace Buckaroo\Magento2\Model\Service;

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
    protected $logger;
    protected $registry;
    private $configAccount;
    private $groupTransaction;
    private $invoiceSender;
    private $helper;
    private $invoiceService;
    private $transactionFactory;

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
        $this->logger               = $logger;
        $this->groupTransaction     = $groupTransaction;
        $this->invoiceSender        = $invoiceSender;
        $this->configAccount        = $configAccount;
        $this->invoiceService       = $invoiceService;
        $this->helper               = $helper;
        $this->transactionFactory   = $transactionFactory;
        $this->registry             = $registry;
    }

    /**
     * Create invoice for the given order with invoice items.
     *
     * @param  Order              $order
     * @param  array              $invoiceItems
     * @throws LocalizedException
     * @return bool
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

        // Prepare invoice with the provided items.
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
        $order->setCustomerNoteNotify(!empty($data['send_email']));
        $order->setIsInProcess(true);

        $transactionSave = $this->transactionFactory->create()
            ->addObject($invoice)
            ->addObject($order);

        $transactionSave->save();
        $this->registry->unregister('current_invoice');

        $payment = $order->getPayment();
        $transactionKey = (string)$payment->getAdditionalInformation(
            AbstractMethod::BUCKAROO_ORIGINAL_TRANSACTION_KEY_KEY
        );

        if (strlen($transactionKey) <= 0) {
            return true;
        }

        // Use a separate variable for each invoice to avoid variable shadowing.
        foreach ($order->getInvoiceCollection() as $inv) {
            $inv->setTransactionId($transactionKey)->save();

            if ($this->groupTransaction->isGroupTransaction($order->getIncrementId())) {
                $this->logger->addDebug(__METHOD__ . '|3| - Set invoice state PAID for group transaction');
                $inv->setState(Invoice::STATE_PAID);
            }

            if (!$inv->getEmailSent() && $this->configAccount->getInvoiceEmail($order->getStore())) {
                $this->logger->addDebug(__METHOD__ . '|4| - Send Invoice Email');
                $this->invoiceSender->send($inv, true);
            }
        }

        return true;
    }

    /**
     * Get invoice items for the order.
     *
     * @param  Order $order
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
     * Add transaction data to the payment.
     *
     * @param  mixed        $payment
     * @param  string|false $transactionKey
     * @param  mixed        $datas
     * @throws \Exception
     * @return mixed
     */
    public function addTransactionData($payment, $transactionKey = false, $datas = false)
    {
        $transactionKey = $transactionKey ?: $payment->getAdditionalInformation(
            AbstractMethod::BUCKAROO_ORIGINAL_TRANSACTION_KEY_KEY
        );

        if (strlen($transactionKey) <= 0) {
            throw new \Exception('There was no transaction ID found');
        }

        if (!$datas) {
            $rawDetails = $payment->getAdditionalInformation(Transaction::RAW_DETAILS);
            $rawInfo = $rawDetails[$transactionKey] ?? [];
        } else {
            $rawInfo = $this->helper->getTransactionAdditionalInfo($datas);
        }

        $payment->setTransactionAdditionalInfo(Transaction::RAW_DETAILS, $rawInfo);
        $payment->setTransactionId($transactionKey . '-capture');
        $payment->setParentTransactionId($transactionKey);
        $payment->setAdditionalInformation(
            AbstractMethod::BUCKAROO_ORIGINAL_TRANSACTION_KEY_KEY,
            $transactionKey
        );

        return $payment;
    }
}
