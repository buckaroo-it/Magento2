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

namespace Buckaroo\Magento2\Model\Push;

use Magento\Sales\Model\Order;
use Magento\GiftCardAccount\Model\GiftcardAccountRepository;
use Buckaroo\Magento2\Logging\BuckarooLoggerInterface;

class GiftCardRefundService
{
    private $giftCardRepo;
    private $logger;

    public function __construct(
        GiftcardAccountRepository $giftCardRepo,
        BuckarooLoggerInterface $logger
    ) {
        $this->giftCardRepo = $giftCardRepo;
        $this->logger = $logger;
    }

    public function refund(Order $order): void
    {
        $giftCards = $order->getGiftCards();
        if (empty($giftCards)) {
            return;
        }

        $data = json_decode($giftCards, true);
        if (!is_array($data)) {
            $this->logger->error("Invalid gift card data for order {$order->getIncrementId()}");
            return;
        }

        foreach ($data as $card) {
            $this->refundCard($order, $card);
        }
    }

    private function refundCard(Order $order, array $card): void
    {
        try {
            $id = $card['gift_card_account_id'] ?? null;
            $amount = (float)($card['amount'] ?? 0);
            if (!$id || $amount <= 0) {
                return;
            }

            $account = $this->giftCardRepo->getById($id);
            $account->setBalance($account->getBalance() + $amount);
            $this->giftCardRepo->save($account);

            $order->addCommentToStatusHistory("Refunded {$amount} to gift card #{$id}");

        } catch (\Throwable $e) {
            $this->logger->error("Failed to refund gift card: " . $e->getMessage());
        }
    }
}