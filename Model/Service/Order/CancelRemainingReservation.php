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

namespace Buckaroo\Magento2\Model\Service\Order;

use Buckaroo\Magento2\Logging\BuckarooLoggerInterface;
use Buckaroo\Magento2\Model\ConfigProvider\Method\Klarna;
use Buckaroo\Magento2\Model\ConfigProvider\Method\Klarnakp;
use Magento\Payment\Gateway\Command\CommandManagerInterface;
use Magento\Payment\Gateway\Data\PaymentDataObjectFactory;
use Magento\Sales\Model\Order;

/**
 * Cancel the remaining Klarna reservation at Buckaroo.
 *
 * After a partial capture Magento closes the authorization transaction, so the standard
 * payment void is skipped during order cancellation. Buckaroo still expects a
 * CancelReservation request to release uncaptured order lines.
 */
class CancelRemainingReservation
{
    /**
     * @var CommandManagerInterface
     */
    private CommandManagerInterface $klarnaCommandManager;

    /**
     * @var CommandManagerInterface
     */
    private CommandManagerInterface $klarnaKpCommandManager;

    /**
     * @var PaymentDataObjectFactory
     */
    private PaymentDataObjectFactory $paymentDataObjectFactory;

    /**
     * @var BuckarooLoggerInterface
     */
    private BuckarooLoggerInterface $logger;

    /**
     * @param CommandManagerInterface  $klarnaCommandManager
     * @param CommandManagerInterface  $klarnaKpCommandManager
     * @param PaymentDataObjectFactory $paymentDataObjectFactory
     * @param BuckarooLoggerInterface  $logger
     */
    public function __construct(
        CommandManagerInterface $klarnaCommandManager,
        CommandManagerInterface $klarnaKpCommandManager,
        PaymentDataObjectFactory $paymentDataObjectFactory,
        BuckarooLoggerInterface $logger
    ) {
        $this->klarnaCommandManager       = $klarnaCommandManager;
        $this->klarnaKpCommandManager     = $klarnaKpCommandManager;
        $this->paymentDataObjectFactory   = $paymentDataObjectFactory;
        $this->logger                     = $logger;
    }

    /**
     * Execute CancelReservation for a Klarna or Klarna KP order.
     *
     * @param Order $order
     * @return bool
     */
    public function execute(Order $order): bool
    {
        $payment = $order->getPayment();
        if ($payment === null) {
            return false;
        }

        $methodCode = (string)$payment->getMethod();
        $commandManager = $this->resolveCommandManager($methodCode);

        if ($commandManager === null) {
            return false;
        }

        if ($payment->getAdditionalInformation('voided_by_buckaroo')) {
            $this->logger->addDebug(sprintf(
                '[KLARNA] CancelRemainingReservation skipped for order %s: reservation already voided.',
                $order->getIncrementId()
            ));
            return false;
        }

        if (!$this->hasReservationReference($order, $payment, $methodCode)) {
            $this->logger->addDebug(sprintf(
                '[KLARNA] CancelRemainingReservation skipped for order %s: no reservation reference found.',
                $order->getIncrementId()
            ));
            return false;
        }

        $this->logger->addDebug(sprintf(
            '[KLARNA] Executing CancelReservation for order %s (method: %s, hasInvoices: %s)',
            $order->getIncrementId(),
            $methodCode,
            $order->hasInvoices() ? 'yes' : 'no'
        ));

        try {
            $commandSubject = [
                'payment' => $this->paymentDataObjectFactory->create($payment),
            ];

            $commandManager->executeByCode('cancel', $payment, $commandSubject);

            $order->addCommentToStatusHistory(
                __('Buckaroo: remaining Klarna reservation cancelled at payment provider.')
            );

            $this->logger->addDebug(sprintf(
                '[KLARNA] CancelReservation succeeded for order %s',
                $order->getIncrementId()
            ));

            return true;
        } catch (\Exception $e) {
            $this->logger->addError(sprintf(
                '[KLARNA] CancelReservation failed for order %s: %s',
                $order->getIncrementId(),
                $e->getMessage()
            ));

            $order->addCommentToStatusHistory(
                __('Buckaroo: failed to cancel remaining Klarna reservation. %1', $e->getMessage())
            );

            return false;
        }
    }

    /**
     * @param string $methodCode
     * @return CommandManagerInterface|null
     */
    private function resolveCommandManager(string $methodCode): ?CommandManagerInterface
    {
        if ($methodCode === Klarna::CODE) {
            return $this->klarnaCommandManager;
        }

        if ($methodCode === Klarnakp::CODE) {
            return $this->klarnaKpCommandManager;
        }

        return null;
    }

    /**
     * @param Order $order
     * @param \Magento\Sales\Model\Order\Payment $payment
     * @param string $methodCode
     * @return bool
     */
    private function hasReservationReference(Order $order, $payment, string $methodCode): bool
    {
        if ($methodCode === Klarna::CODE) {
            return !empty($order->getBuckarooDatarequestKey())
                || !empty($payment->getAdditionalInformation('buckaroo_datarequest_key'));
        }

        if ($methodCode === Klarnakp::CODE) {
            return !empty($order->getBuckarooReservationNumber())
                || !empty($payment->getAdditionalInformation('buckaroo_reservation_number'));
        }

        return false;
    }
}
