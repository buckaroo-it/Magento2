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
