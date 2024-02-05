<?php
/**
 * NOTICE OF LICENSE
 *
 * This source file is subject to the MIT License
 * It is available through the world-wide-web at this URL:
 * https://tldrlegal.com/license/mit-license
 * If you are unable to obtain it through the world-wide-web, please email
 * to support@buckaroo.nl, so we can send you a copy immediately.
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
use Buckaroo\Magento2\Model\Method\AbstractMethod;
use Buckaroo\Magento2\Model\Method\BuckarooAdapter;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Invoice;
use Buckaroo\Magento2\Logging\Log;
use Buckaroo\Magento2\Model\ConfigProvider\Account;
use Buckaroo\Magento2\Helper\PaymentGroupTransaction;
use Magento\Sales\Model\Order\Email\Sender\InvoiceSender;
use Magento\Sales\Model\Order\Payment\Transaction;

/**
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
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
        /** @var Order\Payment $payment */
        $payment = $order->getPayment();

        $this->addTransactionData($payment);

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

    /**
     * @return Order\Payment
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function addTransactionData($payment, $transactionKey = false, $datas = false)
    {
        $transactionKey = $transactionKey ?: $payment->getAdditionalInformation(
            BuckarooAdapter::BUCKAROO_ORIGINAL_TRANSACTION_KEY_KEY
        );

        if (strlen($transactionKey) <= 0) {
            throw new \Buckaroo\Magento2\Exception(__('There was no transaction ID found'));
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
            BuckarooAdapter::BUCKAROO_ORIGINAL_TRANSACTION_KEY_KEY,
            $transactionKey
        );

        return $payment;
    }
}