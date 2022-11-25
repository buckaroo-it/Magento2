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

namespace Buckaroo\Magento2\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Sales\Model\Order\Email\Sender\InvoiceSender;
use Magento\Sales\Model\Order\Invoice;
use Buckaroo\Magento2\Model\ConfigProvider\Account;
use Buckaroo\Magento2\Logging\Log;
use Buckaroo\Magento2\Helper\Data;
use Buckaroo\Magento2\Model\ConfigProvider\Method\Afterpay20;

class SendInvoiceMail implements ObserverInterface
{
    /** @var Account */
    private $accountConfig;

    /** @var InvoiceSender */
    private $invoiceSender;

    /**
     * @var Log $logging
     */
    public $logging;

    public $helper;

    /**
     * @param Account       $accountConfig
     * @param InvoiceSender $invoiceSender
     */
    public function __construct(
        Account $accountConfig,
        InvoiceSender $invoiceSender,
        Log $logging,
        Data $helper
    ) {
        $this->accountConfig = $accountConfig;
        $this->invoiceSender = $invoiceSender;
        $this->logging = $logging;
        $this->helper = $helper;
    }

    /**
     * @param Observer $observer
     * @throws LocalizedException
     *
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     */
    public function execute(Observer $observer)
    {
        $this->logging->addDebug(__METHOD__ . '|1|');

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
            $this->logging->addDebug(__METHOD__ . '|10|sendinvoiceemail');
            $orderBaseShippingAmount = $order->getBaseShippingAmount();
            $this->invoiceSender->send($invoice, true);
            if (($orderBaseShippingAmount > 0) && ($order->getBaseShippingAmount() == 0)) {
                $this->logging->addDebug(__METHOD__ . '|15|');
                $invoice->getOrder()->setBaseShippingAmount($orderBaseShippingAmount);
            }
        }
        if ($invoice->getIsPaid() && $canCapture
            && ($payment->getMethod() == Afterpay20::CODE)
            && !$this->helper->areEqualAmounts($order->getBaseTotalPaid(), $order->getTotalPaid())
            && ($order->getBaseCurrencyCode() == $order->getOrderCurrencyCode())
        ) {
            $this->logging->addDebug(__METHOD__ . '|25|');
            $order->setBaseTotalPaid($order->getTotalPaid());
        }
    }
}
