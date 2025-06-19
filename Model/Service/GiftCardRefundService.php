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
use Magento\GiftCardAccount\Api\GiftCardManagementInterface;
use Magento\Sales\Model\Order;

class GiftCardRefundService
{
    private $giftCardRepo;
    private GiftCardManagementInterface $giftCardManagement;

    private $logger;

    public function __construct(
        GiftCardAccountRepositoryInterface  $giftCardRepo,
        GiftCardManagementInterface $giftCardManagement,
        BuckarooLoggerInterface $logger
    ) {
        $this->giftCardRepo = $giftCardRepo;
        $this->giftCardManagement = $giftCardManagement;
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
            $this->logger->error("Invalid gift card data for order {$order->getIncrementId()}");
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
            $id = $card['i'] ?? null;             // correct: gift_card_account_id
            $amount = (float)($card['a'] ?? 0);   // correct: refund amount

            if (!$id || $amount <= 0) {
                $this->logger->addDebug(sprintf(
                    '[GiftCardRefundService] Skipping invalid card: ID=%s, Amount=%.2f',
                    var_export($id, true),
                    $amount
                ));
                return;
            }

            $account = $this->giftCardRepo->getById($id);

            $this->logger->addDebug(sprintf(
                '[GiftCardRefundService] Loaded gift card #%s | Current balance: %.2f | Refund amount: %.2f',
                $id,
                $account->getBalance(),
                $amount
            ));

            $this->giftCardManagement->refund($account->getCode(), $amount);

            $reloaded = $this->giftCardRepo->getById($id);
            $this->logger->addDebug(sprintf(
                '[GiftCardRefundService] After save: gift card #%s balance is now %.2f',
                $id,
                $reloaded->getBalance()
            ));

            $order->addCommentToStatusHistory(sprintf(
                'Refunded %.2f to gift card #%s.',
                $amount,
                $id
            ));

        } catch (\Throwable $e) {
            $this->logger->error(sprintf(
                '[GiftCardRefundService] Error refunding gift card: %s',
                $e->getMessage()
            ));
        }
    }
}