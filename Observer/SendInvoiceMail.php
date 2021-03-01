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
use Magento\Sales\Model\Order\Email\Sender\InvoiceSender;
use Magento\Sales\Model\Order\Invoice;
use Buckaroo\Magento2\Model\ConfigProvider\Account;
use Buckaroo\Magento2\Logging\Log;

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

    /**
     * @param Account       $accountConfig
     * @param InvoiceSender $invoiceSender
     */
    public function __construct(
        Account $accountConfig,
        InvoiceSender $invoiceSender,
        Log $logging
    ) {
        $this->accountConfig = $accountConfig;
        $this->invoiceSender = $invoiceSender;
        $this->logging = $logging;
    }

    /**
     * @param Observer $observer
     */
    public function execute(Observer $observer)
    {
        $this->logging->addDebug(__METHOD__ . '|1|');

        /** @var Invoice $invoice */
        $invoice = $observer->getEvent()->getInvoice();
        $payment = $invoice->getOrder()->getPayment();

        if (strpos($payment->getMethod(), 'buckaroo_magento2') === false) {
            return;
        }

        $sendInvoiceEmail = $this->accountConfig->getInvoiceEmail($invoice->getStore());
        $canCapture = $payment->getMethodInstance()->canCapture();

        if (!$invoice->getEmailSent() && $invoice->getIsPaid() && $canCapture && $sendInvoiceEmail) {
            $invoice->save();
            $this->logging->addDebug(__METHOD__ . '|10|sendinvoiceemail');
            $this->invoiceSender->send($invoice, true);
        }
    }
}
