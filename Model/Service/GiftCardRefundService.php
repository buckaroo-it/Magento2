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

namespace Buckaroo\Magento2\Model\Service;

use Buckaroo\Magento2\Api\GiftCardRefundServiceInterface;
use Buckaroo\Magento2\Logging\BuckarooLoggerInterface;
use Magento\Framework\ObjectManagerInterface;
use Magento\Sales\Model\Order;

/**
 * Production-ready gift card refund service
 * Automatically detects and handles both Adobe Commerce and Magento Open Source environments
 * 
 * Note: PHPStan errors for Adobe Commerce classes are ignored in phpstan.neon
 * as these classes may not exist in Magento Open Source installations.
 * The service uses runtime detection to handle both scenarios gracefully.
 * 
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class GiftCardRefundService implements GiftCardRefundServiceInterface
{
    private $giftCardRepo;
    private $historyFactory;
    private BuckarooLoggerInterface $logger;
    private ObjectManagerInterface $objectManager;
    private bool $isAdobeCommerceAvailable;

    public function __construct(
        BuckarooLoggerInterface $logger,
        ObjectManagerInterface $objectManager
    ) {
        $this->logger = $logger;
        $this->objectManager = $objectManager;
        $this->isAdobeCommerceAvailable = $this->detectAdobeCommerce();
        $this->initializeDependencies();
    }

    /**
     * Detect if Adobe Commerce is available
     */
    private function detectAdobeCommerce(): bool
    {
        return class_exists(\Magento\GiftCardAccount\Api\GiftCardAccountRepositoryInterface::class) &&
               class_exists(\Magento\GiftCardAccount\Model\HistoryFactory::class);
    }

    /**
     * Initialize Adobe Commerce dependencies if available
     */
    private function initializeDependencies(): void
    {
        if (!$this->isAdobeCommerceAvailable) {
            $this->logger->addDebug('[GiftCardRefundService] Adobe Commerce not detected - gift card refunds will be skipped');
            return;
        }

        try {
            // @phpstan-ignore-next-line (Adobe Commerce classes may not exist in Open Source)
            $this->giftCardRepo = $this->objectManager->get(\Magento\GiftCardAccount\Api\GiftCardAccountRepositoryInterface::class);
            // @phpstan-ignore-next-line (Adobe Commerce classes may not exist in Open Source)
            $this->historyFactory = $this->objectManager->get(\Magento\GiftCardAccount\Model\HistoryFactory::class);
            $this->logger->addDebug('[GiftCardRefundService] Adobe Commerce gift card services initialized successfully');
        } catch (\Exception $e) {
            $this->logger->addError('[GiftCardRefundService] Failed to initialize Adobe Commerce dependencies: ' . $e->getMessage());
            $this->giftCardRepo = null;
            $this->historyFactory = null;
            $this->isAdobeCommerceAvailable = false;
        }
    }

    /**
     * @inheritdoc
     */
    public function refund(Order $order): void
    {
        try {
            $this->logger->addDebug('[GiftCardRefundService] Processing refund for order #' . $order->getIncrementId());

            // Check if Adobe Commerce features are available
            if (!$this->isAdobeCommerceAvailable) {
                $this->logger->addInfo('[GiftCardRefundService] Magento Open Source detected - gift card refunds not supported, skipping for order #' . $order->getIncrementId());
                return;
            }

            // Validate dependencies are properly initialized
            if (!$this->giftCardRepo || !$this->historyFactory) {
                $this->logger->addWarning('[GiftCardRefundService] Gift card services not available, skipping refund for order #' . $order->getIncrementId());
                return;
            }

            // Get gift card data from order
            $giftCards = $order->getGiftCards();
            if (empty($giftCards)) {
                $this->logger->addDebug('[GiftCardRefundService] No gift cards found for order #' . $order->getIncrementId());
                return;
            }

            // Parse gift card data
            $data = json_decode($giftCards, true);
            if (!is_array($data)) {
                $this->logger->addWarning('[GiftCardRefundService] Invalid gift card data format for order #' . $order->getIncrementId());
                return;
            }

            $this->logger->addInfo('[GiftCardRefundService] Processing ' . count($data) . ' gift card refunds for order #' . $order->getIncrementId());

            // Process each gift card
            $successCount = 0;
            foreach ($data as $card) {
                if ($this->refundCard($order, $card)) {
                    $successCount++;
                }
            }

            $this->logger->addInfo('[GiftCardRefundService] Successfully processed ' . $successCount . '/' . count($data) . ' gift card refunds for order #' . $order->getIncrementId());

        } catch (\Throwable $e) {
            $this->logger->addError('[GiftCardRefundService] Unexpected error processing gift card refunds for order #' . $order->getIncrementId() . ': ' . $e->getMessage());
        }
    }

    /**
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     * @SuppressWarnings(PHPMD.ExcessiveMethodLength)
     */
    private function refundCard(Order $order, array $card): bool
    {
        try {
            $id = $card['i'] ?? null;
            $amount = (float)($card['a'] ?? 0);

            if (!$id || $amount <= 0) {
                $this->logger->addDebug(sprintf(
                    '[GiftCardRefundService] Skipping invalid card: ID=%s, Amount=%.2f',
                    var_export($id, true),
                    $amount
                ));
                return false;
            }

            try {
                $account = $this->giftCardRepo->get($id);
            } catch (\Exception $e) {
                $this->logger->addDebug(sprintf(
                    '[GiftCardRefundService] Failed to load gift card #%s: %s',
                    $id,
                    $e->getMessage()
                ));
                return false;
            }

            $currentBalance = $account->getBalance();
            $newBalance = $currentBalance + $amount;

            if ($newBalance < 0) {
                $this->logger->addDebug(sprintf(
                    '[GiftCardRefundService] Invalid new balance %.2f for gift card #%s',
                    $newBalance,
                    $id
                ));
                return false;
            }

            $account->setBalance($newBalance);

            try {
                $this->giftCardRepo->save($account);
            } catch (\Exception $e) {
                $this->logger->addDebug(sprintf(
                    '[GiftCardRefundService] Failed to save gift card #%s: %s',
                    $id,
                    $e->getMessage()
                ));
                return false;
            }

            try {
                $history = $this->historyFactory->create();

                $history->setGiftcardAccount($account);

                // Use dynamic constant access to avoid PHPStan errors
                $actionCreated = defined('\Magento\GiftCardAccount\Model\History::ACTION_CREATED') 
                    ? constant('\Magento\GiftCardAccount\Model\History::ACTION_CREATED') 
                    : 1;
                    
                $history->setGiftcardAccountId($id)
                    ->setAction($actionCreated)
                    ->setBalanceAmount($amount)
                    ->setUpdatedBalance($newBalance)
                    ->setAdditionalInfo('Refunded from cancelled order #' . $order->getIncrementId());

                if ($history->getGiftcardAccount() === null || $history->getGiftcardAccountId() !== $id) {
                    throw new \Exception('Gift card account not properly assigned to history record');
                }

                $history->save();

                $this->logger->addDebug(sprintf(
                    '[GiftCardRefundService] History record created successfully for gift card #%s',
                    $id
                ));
            } catch (\Exception $e) {
                $this->logger->addDebug(sprintf(
                    '[GiftCardRefundService] Failed to save history for gift card #%s: %s (History is non-critical, continuing)',
                    $id,
                    $e->getMessage()
                ));
            }

            $this->logger->addDebug(sprintf(
                '[GiftCardRefundService] Successfully refunded %.2f to gift card #%s (balance: %.2f)',
                $amount,
                $id,
                $newBalance
            ));

            $order->addCommentToStatusHistory(sprintf(
                'Refunded %.2f to gift card #%s. New balance: %.2f',
                $amount,
                $id,
                $newBalance
            ));

            return true;
        } catch (\Throwable $e) {
            $this->logger->addDebug(sprintf(
                '[GiftCardRefundService] Error refunding gift card: %s',
                $e->getMessage()
            ));
            return false;
        }
    }
}
