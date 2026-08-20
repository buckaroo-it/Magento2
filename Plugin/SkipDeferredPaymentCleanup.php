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
use Buckaroo\Magento2\Model\ConfigProvider\Method\PayPerEmail;
use Buckaroo\Magento2\Model\ConfigProvider\Method\Transfer;
use Buckaroo\Magento2\Model\MagentoOrderCleanupScope;
use Buckaroo\Magento2\Model\Service\Order as OrderService;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\OrderManagementInterface;
use Magento\Sales\Api\OrderRepositoryInterface;

/**
 * Keeps Magento's expired-order cleanup cron away from payment methods whose expiry Buckaroo owns.
 *
 * Bank Transfer and Pay Per Email orders wait in a pending state for as long as the shopper has to
 * pay, which is days. Magento cleans up pending orders after sales/orders/delete_pending_after,
 * eight hours by default, and would cancel every one of them long before the due date. Their expiry
 * belongs to buckaroo_magento2_cancel_cron instead, which applies the configured due date.
 *
 * Whether the due date has passed is that cron's decision, not this one's. All this asks is whether
 * the cron is going to take the order at all: it needs the method's expiry to be configured, and the
 * order to still be inside the window the cron looks at. An order outside it would otherwise wait
 * for a payment forever, so it is handed back to Magento's cleanup, which is what had it before.
 *
 * Only cancellations from that cron are skipped. An administrator cancelling by hand, a push, a
 * giftcard reversal and Buckaroo's own expiry cron all proceed untouched.
 */
class SkipDeferredPaymentCleanup
{
    /**
     * @var MagentoOrderCleanupScope
     */
    private MagentoOrderCleanupScope $cleanupScope;

    /**
     * @var OrderRepositoryInterface
     */
    private OrderRepositoryInterface $orderRepository;

    /**
     * @var Transfer
     */
    private Transfer $transferConfig;

    /**
     * @var PayPerEmail
     */
    private PayPerEmail $payPerEmailConfig;

    /**
     * @var BuckarooLoggerInterface
     */
    private BuckarooLoggerInterface $logger;

    /**
     * @param MagentoOrderCleanupScope $cleanupScope
     * @param OrderRepositoryInterface $orderRepository
     * @param Transfer                 $transferConfig
     * @param PayPerEmail              $payPerEmailConfig
     * @param BuckarooLoggerInterface  $logger
     */
    public function __construct(
        MagentoOrderCleanupScope $cleanupScope,
        OrderRepositoryInterface $orderRepository,
        Transfer $transferConfig,
        PayPerEmail $payPerEmailConfig,
        BuckarooLoggerInterface $logger
    ) {
        $this->cleanupScope = $cleanupScope;
        $this->orderRepository = $orderRepository;
        $this->transferConfig = $transferConfig;
        $this->payPerEmailConfig = $payPerEmailConfig;
        $this->logger = $logger;
    }

    /**
     * Leave the order alone when the cleanup cron is what asked for the cancellation.
     *
     * @param OrderManagementInterface $subject
     * @param callable                 $proceed
     * @param int                      $id
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     *
     * @return bool
     */
    public function aroundCancel(OrderManagementInterface $subject, callable $proceed, $id)
    {
        if (!$this->cleanupScope->isRunning()) {
            return $proceed($id);
        }

        $order = $this->loadOrder((int)$id);

        if ($order === null || !$this->hasBuckarooManagedExpiry($order)) {
            return $proceed($id);
        }

        $this->logger->addDebug(sprintf(
            '[CANCEL_ORDER] | [Plugin] | [%s:%s] - Skipped Magento expired-order cleanup; expiry is'
            . ' handled by buckaroo_magento2_cancel_cron | order: %s | method: %s',
            __METHOD__,
            __LINE__,
            $order->getIncrementId(),
            $order->getPayment()->getMethod()
        ));

        return false;
    }

    /**
     * Read the order the cancellation is about.
     *
     * @param int $orderId
     *
     * @return OrderInterface|null
     */
    private function loadOrder(int $orderId): ?OrderInterface
    {
        try {
            return $this->orderRepository->get($orderId);
        } catch (\Exception $exception) {
            // Nothing to protect if the order cannot be read; let Magento decide what to do.
            return null;
        }
    }

    /**
     * Whether buckaroo_magento2_cancel_cron is the owner of this order's expiry.
     *
     * Ownership is only claimed when that cron would really act on the order, mirroring the guards
     * in Model\Service\Order. Claiming it while the method's expiry is switched off would leave the
     * order with nothing to clean it up at all, which is worse than Magento cancelling it early.
     *
     * @param OrderInterface $order
     *
     * @return bool
     */
    private function hasBuckarooManagedExpiry(OrderInterface $order): bool
    {
        $dueDays = $this->resolveDueDays($order);

        if ($dueDays === null) {
            return false;
        }

        return !$this->isBeyondBuckarooExpiryWindow($order, $dueDays);
    }

    /**
     * The configured number of days this order may wait, or null when Buckaroo will not expire it.
     *
     * @param OrderInterface $order
     *
     * @return float|null
     */
    private function resolveDueDays(OrderInterface $order): ?float
    {
        $payment = $order->getPayment();

        if ($payment === null) {
            return null;
        }

        $storeId = $order->getStoreId();

        $dueDays = match ($payment->getMethod()) {
            Transfer::CODE => (float)$this->transferConfig->getDueDate($storeId),
            PayPerEmail::CODE => $this->payPerEmailConfig->getEnabledCronCancelPPE($storeId)
                ? (float)$this->payPerEmailConfig->getExpireDays($storeId)
                : 0.0,
            default => 0.0,
        };

        return $dueDays > 0 ? $dueDays : null;
    }

    /**
     * Whether the order has aged out of the window Buckaroo's expiry cron acts on.
     *
     * Past that window the cron ignores the order for good, so continuing to shield it would leave
     * it waiting for a payment forever. Magento's cleanup is what it had before, so it gets it back.
     *
     * @param OrderInterface $order
     * @param float          $dueDays
     *
     * @return bool
     */
    private function isBeyondBuckarooExpiryWindow(OrderInterface $order, float $dueDays): bool
    {
        $createdAt = (string)$order->getCreatedAt();

        if ($createdAt === '') {
            // Without a creation date the age is unknown; leave the order to Buckaroo's cron.
            return false;
        }

        $windowDays = (int)ceil($dueDays) + OrderService::EXPIRY_WINDOW_GRACE_DAYS;

        try {
            // created_at is stored in UTC and Magento's bootstrap puts PHP in UTC too.
            $windowEnd = (new \DateTimeImmutable($createdAt))->modify(sprintf('+%d day', $windowDays));
        } catch (\Exception $exception) {
            return false;
        }

        return new \DateTimeImmutable() > $windowEnd;
    }
}
