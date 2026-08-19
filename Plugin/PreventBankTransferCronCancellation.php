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

namespace Buckaroo\Magento2\Plugin;

use Buckaroo\Magento2\Logging\BuckarooLoggerInterface;
use Buckaroo\Magento2\Model\ConfigProvider\Method\Transfer as TransferConfig;
use Magento\Sales\Model\Order;

/**
 * Prevent Magento's CleanExpiredOrders cron from canceling Bank Transfer orders
 * before their due date has passed.
 */
class PreventBankTransferCronCancellation
{
    /**
     * @var BuckarooLoggerInterface
     */
    private BuckarooLoggerInterface $logger;

    /**
     * @var TransferConfig
     */
    private TransferConfig $transferConfig;

    /**
     * @param BuckarooLoggerInterface $logger
     * @param TransferConfig          $transferConfig
     */
    public function __construct(
        BuckarooLoggerInterface $logger,
        TransferConfig $transferConfig
    ) {
        $this->logger         = $logger;
        $this->transferConfig = $transferConfig;
    }

    /**
     * @param Order    $subject
     * @param callable $proceed
     *
     * @return Order
     */
    public function aroundCancel(Order $subject, callable $proceed): Order
    {
        $payment = $subject->getPayment();

        if (!$payment
            || $payment->getMethod() !== 'buckaroo_magento2_transfer'
            || $subject->getState() !== Order::STATE_PENDING_PAYMENT
            || empty($payment->getAdditionalInformation('transfer_details'))
        ) {
            return $proceed();
        }

        $store     = $subject->getStore();
        $dueDays   = (int)$this->transferConfig->getDueDate($store);
        $createdAt = new \DateTime($subject->getCreatedAt());
        $dueDate   = (clone $createdAt)->modify("+{$dueDays} day");
        $now       = new \DateTime();

        if ($now <= $dueDate) {
            $this->logger->addDebug(sprintf(
                '[TRANSFER] | [Plugin] | [%s:%s] - Order %s protected from Magento cron cancellation. '
                . 'Due date: %s, now: %s. State/status unchanged.',
                __METHOD__,
                __LINE__,
                $subject->getIncrementId(),
                $dueDate->format('Y-m-d H:i:s'),
                $now->format('Y-m-d H:i:s')
            ));

            return $subject;
        }

        $this->logger->addDebug(sprintf(
            '[TRANSFER] | [Plugin] | [%s:%s] - Order %s due date (%s) has passed, allowing cancellation.',
            __METHOD__,
            __LINE__,
            $subject->getIncrementId(),
            $dueDate->format('Y-m-d H:i:s')
        ));

        return $proceed();
    }
}
