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
use Magento\Payment\Gateway\Command\CommandException;
use Magento\Payment\Gateway\CommandInterface;
use Magento\Payment\Gateway\Data\PaymentDataObjectFactory;
use Magento\Sales\Model\Order\Payment;

class VoidCm3Payment implements ObserverInterface
{
    /**
     * @var PaymentDataObjectFactory
     */
    private $paymentDataObjectFactory;

    /**
     * @var CommandInterface
     */
    private $voidCommand;

    /**
     * @param PaymentDataObjectFactory $paymentDataObjectFactory
     * @param CommandInterface         $voidCommand
     */
    public function __construct(
        PaymentDataObjectFactory $paymentDataObjectFactory,
        CommandInterface $voidCommand
    ) {
        $this->paymentDataObjectFactory = $paymentDataObjectFactory;
        $this->voidCommand = $voidCommand;
    }

    /**
     * A CM3 payment doesn't always use the Authorize payment flow.
     *
     * Perform the payment void() call when in those cases so the necessary SOAP calls are been made.
     *
     * @param Observer $observer
     *
     * @throws CommandException
     */
    public function execute(Observer $observer)
    {
        /* @var $payment Payment */
        $payment = $observer->getPayment();

        if (strpos($payment->getMethod(), 'buckaroo_magento2') === false) {
            return;
        }

        $authTransaction = $payment->getAuthorizationTransaction();
        $invoiceKey = $payment->getAdditionalInformation('buckaroo_cm3_invoice_key');

        if ($authTransaction || strlen((string)$invoiceKey) <= 0) {
            return;
        }

        $this->voidCommand->execute(['payment' => $this->paymentDataObjectFactory->create($payment)]);
    }
}
