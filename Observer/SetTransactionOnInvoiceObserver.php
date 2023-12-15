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

namespace Buckaroo\Magento2\Observer;

use Buckaroo\Magento2\Model\Config\Source\InvoiceHandlingOptions;
use Buckaroo\Magento2\Model\ConfigProvider\Account;
use Buckaroo\Magento2\Model\Service\CreateInvoice;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Invoice;
use Magento\Sales\Model\Order\Payment\State\CommandInterface;
use Magento\Sales\Model\Order\Payment\Transaction;

class SetTransactionOnInvoiceObserver implements ObserverInterface
{
    /**
     * @var CommandInterface
     */
    protected CommandInterface $stateCommand;

    /**
     * @var Account
     */
    private Account $configAccount;

    /**
     * @var CreateInvoice
     */
    private CreateInvoice $createInvoiceService;

    public function __construct(
        CommandInterface $stateCommand,
        Account $configAccount,
        CreateInvoice $createInvoiceService
    ) {
        $this->stateCommand = $stateCommand;
        $this->configAccount = $configAccount;
        $this->createInvoiceService = $createInvoiceService;
    }

    /**
     * Set transaction id on invoiced for invoice after shippment
     *
     * @param Observer $observer
     * @return $this
     * @throws LocalizedException
     */
    public function execute(Observer $observer)
    {
        /* @var $invoice Invoice */
        $invoice = $observer->getEvent()->getInvoice();

        /* @var $order Order */
        $order = $observer->getEvent()->getOrder();
        $payment = $order->getPayment();

        $amount = $invoice->getGrandTotal();
        $paymentMethod = $payment->getMethod();

        if (strpos($paymentMethod, 'buckaroo_magento2_') !== false &&
            $this->configAccount->getInvoiceHandling() == InvoiceHandlingOptions::SHIPMENT &&
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
}