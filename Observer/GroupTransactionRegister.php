<?php
/**
 * Buckaroo Magento 2 payment module (https://www.buckaroo.eu/)
 *
 * Copyright (c) Buckaroo B.V.
 * See LICENSE for license details.
 *
 * Support: support@buckaroo.nl
 *
 * @copyright Copyright (c) Buckaroo B.V.
 * @license   MIT
 */

namespace Buckaroo\Magento2\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Sales\Model\Order\Email\Sender\InvoiceSender;
use Magento\Sales\Model\Order\Invoice;
use Buckaroo\Magento2\Model\ConfigProvider\Account;
use Buckaroo\Magento2\Logging\BuckarooLoggerInterface;
use Buckaroo\Magento2\Helper\Data;
use Buckaroo\Magento2\Helper\PaymentGroupTransaction;

class GroupTransactionRegister implements ObserverInterface
{
    /**
     * @var Account
     */
    private $accountConfig;

    /**
     * @var InvoiceSender
     */
    private $invoiceSender;

    /**
     * @var BuckarooLoggerInterface
     */
    private $logger;

    /**
     * @var Data
     */
    private $helper;

    /**
     * @var PaymentGroupTransaction
     */
    private $groupTransaction;

    /**
     * @param Account                 $accountConfig
     * @param InvoiceSender           $invoiceSender
     * @param PaymentGroupTransaction $groupTransaction
     * @param BuckarooLoggerInterface $logger
     * @param Data                    $helper
     */
    public function __construct(
        Account $accountConfig,
        InvoiceSender $invoiceSender,
        PaymentGroupTransaction $groupTransaction,
        BuckarooLoggerInterface $logger,
        Data $helper
    ) {
        $this->accountConfig = $accountConfig;
        $this->invoiceSender = $invoiceSender;
        $this->groupTransaction = $groupTransaction;
        $this->logger = $logger;
        $this->helper = $helper;
    }

    /**
     * Set total paid by a group transaction for sales_order_invoice_pay event
     *
     * @param Observer $observer
     */
    public function execute(Observer $observer)
    {
        /** @var Invoice $invoice */
        $invoice = $observer->getEvent()->getInvoice();
        $payment = $invoice->getOrder()->getPayment();

        if (strpos($payment->getMethod(), 'buckaroo_magento2') === false) {
            return;
        }

        $order = $invoice->getOrder();

        $items = $this->groupTransaction->getGroupTransactionItems($order->getIncrementId());
        foreach ($items as $item) {
            $this->logger->addDebug(sprintf(
                '[GROUP_TRANSACTION] | [Observer] | [%s:%s] - Set Order Total Paid | orderTotalPaid: %s',
                __METHOD__,
                __LINE__,
                var_export([$order->getTotalPaid(), $item['amount']], true)
            ));
            $totalPaid = $order->getTotalPaid() + $item['amount'];
            $baseTotalPaid = $order->getBaseTotalPaid() + $item['amount'];
            if (($totalPaid < $order->getGrandTotal())
                || ($this->helper->areEqualAmounts($totalPaid, $order->getGrandTotal()))
            ) {
                $order->setTotalPaid($totalPaid);
            }
            if (($baseTotalPaid < $order->getBaseGrandTotal())
                || ($this->helper->areEqualAmounts($baseTotalPaid, $order->getBaseGrandTotal()))
            ) {
                $order->setBaseTotalPaid($baseTotalPaid);
            }
        }
    }
}
