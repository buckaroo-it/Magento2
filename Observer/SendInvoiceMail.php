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

namespace Buckaroo\Magento2\Observer;

use Buckaroo\Magento2\Helper\Data;
use Buckaroo\Magento2\Logging\BuckarooLoggerInterface;
use Buckaroo\Magento2\Model\ConfigProvider\Account;
use Buckaroo\Magento2\Model\ConfigProvider\Method\Afterpay20;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Sales\Api\InvoiceRepositoryInterface;
use Magento\Sales\Model\Order\Email\Sender\InvoiceSender;
use Magento\Sales\Model\Order\Invoice;

class SendInvoiceMail implements ObserverInterface
{
    /**
     * @var BuckarooLoggerInterface $logger
     */
    public $logger;
    /**
     * @var Data
     */
    public $helper;
    /**
     * @var Account
     */
    private $accountConfig;
    /**
     * @var InvoiceSender
     */
    private $invoiceSender;

    /**
     * @var InvoiceRepositoryInterface
     */
    private $invoiceRepository;

    /**
     * @param Account $accountConfig
     * @param InvoiceSender $invoiceSender
     * @param BuckarooLoggerInterface $logger
     * @param Data $helper
     * @param InvoiceRepositoryInterface $invoiceRepository
     */
    public function __construct(
        Account $accountConfig,
        InvoiceSender $invoiceSender,
        BuckarooLoggerInterface $logger,
        Data $helper,
        InvoiceRepositoryInterface $invoiceRepository
    ) {
        $this->accountConfig = $accountConfig;
        $this->invoiceSender = $invoiceSender;
        $this->logger = $logger;
        $this->helper = $helper;
        $this->invoiceRepository = $invoiceRepository;
    }

    /**
     * Send email on creating invoice on sales_order_invoice_pay event
     *
     * @param Observer $observer
     *
     * @throws LocalizedException
     * @throws \Exception
     *
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     */
    public function execute(Observer $observer)
    {
        /** @var Invoice $invoice */
        $invoice = $observer->getEvent()->getInvoice();
        $payment = $invoice->getOrder()->getPayment();
        $order = $invoice->getOrder();

        if (strpos($payment->getMethod(), 'buckaroo_magento2') === false) {
            return;
        }

        $sendInvoiceEmail = $this->accountConfig->getInvoiceEmail($invoice->getStore());
        $canCapture = $payment->getMethodInstance()->canCapture();

        if (!$invoice->getEmailSent() && $invoice->getIsPaid() && $canCapture && $sendInvoiceEmail) {
            $this->invoiceRepository->save($invoice);
            $this->logger->addDebug(
                '[SEND_EMAIL] | [Observer] | ['.__METHOD__.':'.__LINE__.'] - Send email on creating invoice'
            );
            $orderBaseSubtotal = $order->getBaseSubtotal();
            $orderBaseTaxAmount = $order->getBaseTaxAmount();
            $orderBaseShippingAmount = $order->getBaseShippingAmount();
            $itemRowTotals = $this->captureItemRowTotals($invoice);

            $this->invoiceSender->send($invoice, true);

            $this->restoreItemRowTotals($invoice, $itemRowTotals);
            if (($orderBaseShippingAmount > 0) && ($order->getBaseShippingAmount() == 0)) {
                $invoice->getOrder()->setBaseShippingAmount($orderBaseShippingAmount);
            }
            $order->setBaseSubtotal($orderBaseSubtotal);
            $order->setBaseTaxAmount($orderBaseTaxAmount);
        }
        if ($invoice->getIsPaid() && $canCapture
            && ($payment->getMethod() == Afterpay20::CODE)
            && !$this->helper->areEqualAmounts($order->getBaseTotalPaid(), $order->getTotalPaid())
            && ($order->getBaseCurrencyCode() == $order->getOrderCurrencyCode())
        ) {
            $order->setBaseTotalPaid($order->getTotalPaid());
        }
    }

    /**
     * Remember the row totals of the order items this invoice covers.
     *
     * Magento's invoice email template passes the ORDER item to DefaultItems::getItemPrice(),
     * which overwrites row_total with price * invoiced qty. Wrong on a partial invoice, and the
     * next order save commits it, so later credit memos refund shorts.
     *
     * @param Invoice $invoice
     *
     * @return array<int, array{row_total: mixed, base_row_total: mixed}>
     */
    private function captureItemRowTotals(Invoice $invoice): array
    {
        $totals = [];

        foreach ($invoice->getAllItems() as $invoiceItem) {
            $orderItem = $invoiceItem->getOrderItem();
            if ($orderItem === null || !$orderItem->getId()) {
                continue;
            }

            $totals[(int)$orderItem->getId()] = [
                'row_total' => $orderItem->getRowTotal(),
                'base_row_total' => $orderItem->getBaseRowTotal(),
            ];
        }

        return $totals;
    }

    /**
     * Put back what the email template overwrote.
     *
     * @param Invoice $invoice
     * @param array   $totals
     *
     * @return void
     */
    private function restoreItemRowTotals(Invoice $invoice, array $totals): void
    {
        foreach ($invoice->getAllItems() as $invoiceItem) {
            $orderItem = $invoiceItem->getOrderItem();
            if ($orderItem === null || !isset($totals[(int)$orderItem->getId()])) {
                continue;
            }

            $original = $totals[(int)$orderItem->getId()];

            if ($this->helper->areEqualAmounts((float)$orderItem->getRowTotal(), (float)$original['row_total'])
                && $this->helper->areEqualAmounts(
                    (float)$orderItem->getBaseRowTotal(),
                    (float)$original['base_row_total']
                )
            ) {
                continue;
            }

            $this->logger->addDebug(sprintf(
                '[SEND_EMAIL] | [Observer] | [%s:%s] - Restoring row totals the invoice email '
                . 'overwrote on order item %s: %s -> %s',
                __METHOD__,
                __LINE__,
                (string)$orderItem->getId(),
                var_export($orderItem->getRowTotal(), true),
                var_export($original['row_total'], true)
            ));

            $orderItem->setRowTotal($original['row_total']);
            $orderItem->setBaseRowTotal($original['base_row_total']);
        }
    }
}
