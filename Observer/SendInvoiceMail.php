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
use Magento\Sales\Model\Order\Email\Sender\InvoiceSender;
use Magento\Sales\Model\Order\Invoice;

class SendInvoiceMail implements ObserverInterface
{
    /**
     * @var BuckarooLoggerInterface $logger
     */
    public BuckarooLoggerInterface $logger;
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
     * @param Account $accountConfig
     * @param InvoiceSender $invoiceSender
     * @param BuckarooLoggerInterface $logger
     * @param Data $helper
     */
    public function __construct(
        Account $accountConfig,
        InvoiceSender $invoiceSender,
        BuckarooLoggerInterface $logger,
        Data $helper
    ) {
        $this->accountConfig = $accountConfig;
        $this->invoiceSender = $invoiceSender;
        $this->logger = $logger;
        $this->helper = $helper;
    }

    /**
     * Send email on creating invoice on sales_order_invoice_pay event
     *
     * @param Observer $observer
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
            $invoice->save();
            $this->logger->addDebug(
                '[SEND_EMAIL] | [Observer] | ['.__METHOD__.':'.__LINE__.'] - Send email on creating invoice'
            );
            $orderBaseSubtotal = $order->getBaseSubtotal();
            $orderBaseTaxAmount = $order->getBaseTaxAmount();
            $orderBaseShippingAmount = $order->getBaseShippingAmount();
            $this->invoiceSender->send($invoice, true);
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
}
