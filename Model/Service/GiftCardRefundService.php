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

            $account = $this->giftCardRepo->get($id);

            $this->logger->addDebug(sprintf(
                '[GiftCardRefundService] Loaded gift card #%s | Current balance: %.2f | Refund amount: %.2f',
                $id,
                $account->getBalance(),
                $amount
            ));

            $newBalance = $account->getBalance() + $amount;
            $account->setBalance($newBalance);
            $this->giftCardRepo->save($account);

            // Log history entry (optional but useful for tracking)
            $history = $this->historyFactory->create();
            $history->setGiftcardAccountId($id)
                ->setAction(History::ACTION_CREATED)
                ->setBalanceAmount($amount)
                ->setUpdatedBalance($newBalance)
                ->setAdditionalInfo(__('Refunded from cancelled order #%1', $order->getIncrementId()));
            $history->save();

            $this->logger->addDebug(sprintf(
                '[GiftCardRefundService] After save: gift card #%s balance is now %.2f',
                $id,
                $newBalance
            ));

            $order->addCommentToStatusHistory(sprintf(
                'Refunded %.2f to gift card #%s.',
                $amount,
                $id
            ));

        } catch (\Throwable $e) {
            $this->logger->addDebug(sprintf(
                '[GiftCardRefundService] Error refunding gift card: %s',
                $e->getMessage()
            ));
        }
    }
}