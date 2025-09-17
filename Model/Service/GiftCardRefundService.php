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
use Magento\Sales\Model\Order;

/**
 * Gift card refund service that works for both Magento Open Source and Adobe Commerce
 * Uses Adobe Commerce functionality when available, gracefully handles when not available
 *
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class GiftCardRefundService implements GiftCardRefundServiceInterface
{
    private BuckarooLoggerInterface $logger;
    private ?bool $isAdobeCommerceAvailable = null;
    
    // Optional dependencies that may not exist in Magento Open Source
    private $giftCardRepo = null;
    private $historyFactory = null;

    public function __construct(
        BuckarooLoggerInterface $logger
    ) {
        $this->logger = $logger;
        $this->initializeAdobeCommerceDependencies();
    }

    public function refund(Order $order): void
    {
        $this->logger->addDebug('[GiftCardRefundService] Processing refund for order #' . $order->getIncrementId());

        $giftCards = $order->getGiftCards();
        if (empty($giftCards)) {
            $this->logger->addDebug('[GiftCardRefundService] No gift cards found for order #' . $order->getIncrementId());
            return;
        }

        if (!$this->isAdobeCommerceAvailable()) {
            $this->logger->addDebug(
                '[GiftCardRefundService] Gift card refund is not available in Magento Open Source. ' .
                'Order #' . $order->getIncrementId() . ' contains gift cards that cannot be automatically refunded.'
            );

            $order->addCommentToStatusHistory(
                'Order contains gift cards. Automatic gift card refund is only available in Adobe Commerce. ' .
                'Please process gift card refunds manually if needed.'
            );
            return;
        }

        $data = json_decode($giftCards, true);
        if (!is_array($data)) {
            $this->logger->addDebug("Invalid gift card data for order {$order->getIncrementId()}");
            return;
        }

        $this->logger->addDebug('[GiftCardRefundService] Found ' . count($data) . ' gift cards to refund');

        foreach ($data as $card) {
            $this->refundCard($order, $card);
        }
    }

    /**
     * Initialize Adobe Commerce dependencies if available
     */
    private function initializeAdobeCommerceDependencies(): void
    {
        if ($this->isAdobeCommerceAvailable()) {
            try {
                $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
                $this->giftCardRepo = $objectManager->get('Magento\GiftCardAccount\Api\GiftCardAccountRepositoryInterface');
                $this->historyFactory = $objectManager->get('Magento\GiftCardAccount\Model\HistoryFactory');
            } catch (\Throwable $e) {
                $this->logger->addDebug('[GiftCardRefundService] Failed to initialize Adobe Commerce dependencies: ' . $e->getMessage());
                $this->isAdobeCommerceAvailable = false;
            }
        }
    }

    /**
     * Check if Adobe Commerce gift card functionality is available
     */
    private function isAdobeCommerceAvailable(): bool
    {
        if ($this->isAdobeCommerceAvailable === null) {
            $this->isAdobeCommerceAvailable =
                interface_exists('Magento\GiftCardAccount\Api\GiftCardAccountRepositoryInterface') &&
                class_exists('Magento\GiftCardAccount\Model\HistoryFactory') &&
                class_exists('Magento\GiftCardAccount\Model\History');
        }

        return $this->isAdobeCommerceAvailable;
    }

    /**
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     * @SuppressWarnings(PHPMD.ExcessiveMethodLength)
     */
    private function refundCard(Order $order, array $card): void
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
                return;
            }

            try {
                $account = $this->giftCardRepo->get($id);
            } catch (\Exception $e) {
                $this->logger->addDebug(sprintf(
                    '[GiftCardRefundService] Failed to load gift card #%s: %s',
                    $id,
                    $e->getMessage()
                ));
                return;
            }

            $currentBalance = $account->getBalance();
            $newBalance = $currentBalance + $amount;

            if ($newBalance < 0) {
                $this->logger->addDebug(sprintf(
                    '[GiftCardRefundService] Invalid new balance %.2f for gift card #%s',
                    $newBalance,
                    $id
                ));
                return;
            }

            $account->setBalance($newBalance);

            try {
                $this->giftCardRepo->save($account);
            } catch (\Exception $e) {
                $this->logger->addError(sprintf(
                    '[GiftCardRefundService] Failed to save gift card #%s: %s',
                    $id,
                    $e->getMessage()
                ));
                return;
            }

            try {
                $history = $this->historyFactory->create();
                $history->setGiftcardAccount($account);
                $history->setGiftcardAccountId($id)
                    ->setAction(constant('Magento\GiftCardAccount\Model\History::ACTION_CREATED'))
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
                $this->logger->addWarning(sprintf(
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

        } catch (\Throwable $e) {
            $this->logger->addError(sprintf(
                '[GiftCardRefundService] Error refunding gift card: %s',
                $e->getMessage()
            ));
        }
    }
}
