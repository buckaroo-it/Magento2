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
declare(strict_types=1);

namespace Buckaroo\Magento2\Observer;

use Buckaroo\Magento2\Model\Config\Source\InvoiceHandlingOptions;
use Buckaroo\Magento2\Model\ConfigProvider\Account;
use Buckaroo\Magento2\Model\Service\CreateInvoice;
use Buckaroo\Magento2\Service\CheckPaymentType;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Sales\Api\Data\OrderPaymentInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Invoice;
use Magento\Sales\Model\Order\Payment\State\CommandInterface;
use Magento\Sales\Model\Order\Payment\Transaction;

class SetTransactionOnInvoiceObserver implements ObserverInterface
{
    /**
     * @var CheckPaymentType
     */
    public $checkPaymentType;

    /**
     * @var CommandInterface
     */
    protected $stateCommand;

    /**
     * @var Account
     */
    private $configAccount;

    /**
     * @var CreateInvoice
     */
    private $createInvoiceService;

    /**
     * @param CommandInterface $stateCommand
     * @param Account $configAccount
     * @param CheckPaymentType $checkPaymentType
     * @param CreateInvoice $createInvoiceService
     */
    public function __construct(
        CommandInterface $stateCommand,
        Account $configAccount,
        CheckPaymentType $checkPaymentType,
        CreateInvoice $createInvoiceService
    ) {
        $this->stateCommand = $stateCommand;
        $this->configAccount = $configAccount;
        $this->checkPaymentType = $checkPaymentType;
        $this->createInvoiceService = $createInvoiceService;
    }

    /**
     * Set transaction id on invoiced for invoice after shippment
     *
     * @param Observer $observer
     *
     * @throws LocalizedException
     *
     * @return $this
     */
    public function execute(Observer $observer)
    {
        /* @var $invoice Invoice */
        $invoice = $observer->getEvent()->getInvoice();

        /* @var $order Order */
        $order = $observer->getEvent()->getOrder();
        $payment = $order->getPayment();

        // Magento's payment state commands treat $amount as a BASE amount (they format it
        // with the base currency and compare it to base_grand_total for capture finality)
        $amount = $invoice->getBaseGrandTotal();
        $paymentMethod = $payment->getMethod();
        if ($this->checkPaymentType->isBuckarooMethod($paymentMethod) &&
            $this->isInvoiceCreatedAfterShipment($payment) &&
            empty($invoice->getTransactionId()) &&
            empty($payment->getTransactionId())
        ) {

            $this->createInvoiceService->addTransactionData($payment);

            $message = $this->stateCommand->execute($payment, $amount, $order);
            $transaction = $payment->addTransaction(
                Transaction::TYPE_CAPTURE,
                $invoice,
                true
            );
            $message = $payment->prependMessage($message);
            $payment->addTransactionCommentsToOrder($transaction, $message);
        }

        return $this;
    }

    /**
     * Is the invoice for the current order is created after shipment
     *
     * @param OrderPaymentInterface $payment
     *
     * @return bool
     */
    private function isInvoiceCreatedAfterShipment(OrderPaymentInterface $payment): bool
    {
        $invoiceHandling = $payment->getAdditionalInformation(
            InvoiceHandlingOptions::INVOICE_HANDLING
        );

        if ($invoiceHandling == InvoiceHandlingOptions::SHIPMENT) {
            return true;
        }

        if ($invoiceHandling !== null && $invoiceHandling !== '') {
            return false;
        }

        return $this->configAccount->getInvoiceHandling() == InvoiceHandlingOptions::SHIPMENT;
    }
}
