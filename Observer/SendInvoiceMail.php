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
namespace TIG\Buckaroo\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Sales\Model\Order\Email\Sender\InvoiceSender;
use Magento\Sales\Model\Order\Invoice;
use TIG\Buckaroo\Model\ConfigProvider\Account;

class SendInvoiceMail implements ObserverInterface
{
    /** @var Account */
    private $accountConfig;

    /** @var InvoiceSender */
    private $invoiceSender;

    /**
     * @param Account       $accountConfig
     * @param InvoiceSender $invoiceSender
     */
    public function __construct(
        Account $accountConfig,
        InvoiceSender $invoiceSender
    ) {
        $this->accountConfig = $accountConfig;
        $this->invoiceSender = $invoiceSender;
    }

    /**
     * @param Observer $observer
     */
    public function execute(Observer $observer)
    {
        /** @var Invoice $invoice */
        $invoice = $observer->getEvent()->getInvoice();
        $payment = $invoice->getOrder()->getPayment();

        if (strpos($payment->getMethod(), 'tig_buckaroo') === false) {
            return;
        }

        $sendInvoiceEmail = $this->accountConfig->getInvoiceEmail($invoice->getStore());
        $canCapture = $payment->getMethodInstance()->canCapture();

        if (!$invoice->getEmailSent() && $invoice->getIsPaid() && $canCapture && $sendInvoiceEmail) {
            $invoice->save();
            $this->invoiceSender->send($invoice, true);
        }
    }
}
