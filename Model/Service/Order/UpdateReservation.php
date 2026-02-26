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
use Magento\Framework\Exception\LocalizedException;
use Magento\Payment\Gateway\Command\CommandManagerInterface;
use Magento\Payment\Gateway\Data\PaymentDataObjectFactory;
use Magento\Sales\Model\Order;

/**
 * Service to update a Klarna MOR reservation before capture.
 * Can be used to update order lines or shipping address without increasing the total amount.
 */
class UpdateReservation
{
    /**
     * @var CommandManagerInterface
     */
    private CommandManagerInterface $commandManager;

    /**
     * @var PaymentDataObjectFactory
     */
    private PaymentDataObjectFactory $paymentDataObjectFactory;

    /**
     * @var BuckarooLoggerInterface
     */
    private BuckarooLoggerInterface $logger;

    /**
     * @param CommandManagerInterface  $commandManager
     * @param PaymentDataObjectFactory $paymentDataObjectFactory
     * @param BuckarooLoggerInterface  $logger
     */
    public function __construct(
        CommandManagerInterface $commandManager,
        PaymentDataObjectFactory $paymentDataObjectFactory,
        BuckarooLoggerInterface $logger
    ) {
        $this->commandManager           = $commandManager;
        $this->paymentDataObjectFactory = $paymentDataObjectFactory;
        $this->logger                   = $logger;
    }

    /**
     * Execute UpdateReservation data request for a Klarna MOR order.
     *
     * @param Order $order
     * @param array $updateData Additional update parameters (e.g. order lines, shipping address)
     *
     * @throws LocalizedException
     *
     * @return bool
     */
    public function execute(Order $order, array $updateData = []): bool
    {
        $payment = $order->getPayment();

        if ($payment->getMethod() !== Klarna::CODE) {
            return false;
        }

        $dataRequestKey = $order->getBuckarooDatarequestKey()
            ?? $payment->getAdditionalInformation('buckaroo_datarequest_key');

        if (!$dataRequestKey) {
            throw new LocalizedException(__(
                'Cannot update Klarna MOR reservation for order %1: DataRequest key is missing.',
                $order->getIncrementId()
            ));
        }

        $this->logger->addDebug(sprintf(
            '[KLARNA_MOR] Executing UpdateReservation for order %s, DataRequestKey: %s',
            $order->getIncrementId(),
            $dataRequestKey
        ));

        try {
            $commandSubject = [
                'payment'     => $this->paymentDataObjectFactory->create($payment),
                'amount'      => $order->getGrandTotal(),
                'update_data' => $updateData,
            ];

            $this->commandManager->executeByCode('update_reservation', $payment, $commandSubject);

            $this->logger->addDebug(sprintf(
                '[KLARNA_MOR] UpdateReservation succeeded for order %s',
                $order->getIncrementId()
            ));

            return true;
        } catch (\Exception $e) {
            $this->logger->addError(sprintf(
                '[KLARNA_MOR] UpdateReservation failed for order %s: %s',
                $order->getIncrementId(),
                $e->getMessage()
            ));
            throw new LocalizedException(__($e->getMessage()), $e);
        }
    }
}
