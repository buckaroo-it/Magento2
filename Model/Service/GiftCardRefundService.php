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

use Buckaroo\Magento2\Logging\BuckarooLoggerInterface;
use Magento\GiftCardAccount\Api\GiftCardAccountRepositoryInterface;
use Magento\GiftCardAccount\Model\HistoryFactory;
use Magento\GiftCardAccount\Model\History;
use Magento\Sales\Model\Order;

class GiftCardRefundService
{
    private GiftCardAccountRepositoryInterface $giftCardRepo;
    private HistoryFactory $historyFactory;
    private BuckarooLoggerInterface $logger;

    public function __construct(
        GiftCardAccountRepositoryInterface $giftCardRepo,
        HistoryFactory $historyFactory,
        BuckarooLoggerInterface $logger
    ) {
        $this->giftCardRepo = $giftCardRepo;
        $this->historyFactory = $historyFactory;
        $this->logger = $logger;
    }

    public function refund(Order $order): void
    {
        $this->logger->addDebug('[GiftCardRefundService] - started');

        $giftCards = $order->getGiftCards();
        if (empty($giftCards)) {
            return;
        }

        $data = json_decode($giftCards, true);
        if (!is_array($data)) {
            $this->logger->addDebug("Invalid gift card data for order {$order->getIncrementId()}");
            return;
        }

        $this->logger->addDebug('[GiftCardRefundService] Raw gift card JSON: ' . $giftCards);
        $this->logger->addDebug('[GiftCardRefundService] Parsed gift card data: ' . print_r($data, true));

        foreach ($data as $card) {
            $this->refundCard($order, $card);
        }
    }

    private function refundCard(Order $order, array $card): void
    {
        try {
            $id = $card['i'] ?? null;             // gift_card_account_id
            $amount = (float)($card['a'] ?? 0);   // refund amount

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
            $this->logger->addDebug(sprintf(
                '[GiftCardRefundService] Loaded gift card #%s | Current balance: %.2f | Refund amount: %.2f',
                $id,
                $currentBalance,
                $amount
            ));

            $newBalance = $currentBalance + $amount;
            $this->logger->addDebug(sprintf(
                '[GiftCardRefundService] newbalance: %.2f',
                $newBalance
            ));

            if ($newBalance < 0) {
                $this->logger->addDebug(sprintf(
                    '[GiftCardRefundService] Invalid new balance %.2f for gift card #%s',
                    $newBalance,
                    $id
                ));
                return;
            }

            $account->setBalance($newBalance);

            $this->logger->addDebug(sprintf(
                '[GiftCardRefundService] Gift card #%s prepared for save with balance %.2f',
                $id,
                $newBalance
            ));

            try {
                $this->giftCardRepo->save($account);
            } catch (\Exception $e) {
                $this->logger->addDebug(sprintf(
                    '[GiftCardRefundService] Failed to save gift card #%s: %s',
                    $id,
                    $e->getMessage()
                ));
                return;
            }

            // Log history entry with proper error handling
            try {
                $history = $this->historyFactory->create();
                $history->setGiftcardAccountId($id)
                    ->setAction(History::ACTION_CREATED)
                    ->setBalanceAmount($amount)
                    ->setUpdatedBalance($newBalance)
                    ->setAdditionalInfo('Refunded from cancelled order #' . $order->getIncrementId());
                $history->save();
            } catch (\Exception $e) {
                $this->logger->addDebug(sprintf(
                    '[GiftCardRefundService] Failed to save history for gift card #%s: %s',
                    $id,
                    $e->getMessage()
                ));
            }

            $this->logger->addDebug(sprintf(
                '[GiftCardRefundService] Successfully processed gift card #%s, balance updated to %.2f',
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
            $this->logger->addDebug(sprintf(
                '[GiftCardRefundService] Error refunding gift card: %s',
                $e->getMessage()
            ));
        }
    }
}
