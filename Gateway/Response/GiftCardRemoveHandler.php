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

namespace Buckaroo\Magento2\Gateway\Response;

use Buckaroo\Magento2\Gateway\Helper\SubjectReader;
use Buckaroo\Magento2\Model\Giftcard\RemoveException;
use Buckaroo\Magento2\Model\GroupTransaction;
use Buckaroo\Magento2\Model\GroupTransactionRepository;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Message\ManagerInterface as MessageManager;
use Magento\Payment\Gateway\Response\HandlerInterface;

class GiftCardRemoveHandler implements HandlerInterface
{
    /**
     * @var MessageManager
     */
    protected MessageManager $messageManager;

    /**
     * @var GroupTransactionRepository
     */
    protected GroupTransactionRepository $groupTransactionRepository;

    /**
     * @param MessageManager $messageManager
     * @param GroupTransactionRepository $groupTransactionRepository
     */
    public function __construct(
        MessageManager $messageManager,
        GroupTransactionRepository $groupTransactionRepository
    ) {
        $this->messageManager = $messageManager;
        $this->groupTransactionRepository = $groupTransactionRepository;
    }

    /**
     * @inheritdoc
     * @throws RemoveException
     * @throws CouldNotSaveException
     */
    public function handle(array $handlingSubject, array $response): void
    {
        $transactionResponse = SubjectReader::readTransactionResponse($response);

        if (isset($handlingSubject['giftcardTransaction'])
            && $handlingSubject['giftcardTransaction'] instanceof GroupTransaction
            && $transactionResponse->getStatusCode() == 190) {
            $this->updateGiftcardTransactionAmount(
                $handlingSubject['giftcardTransaction'],
                (float)$transactionResponse->getAmount()
            );
        }

        if ($transactionResponse->getStatusCode() == 690) {
            throw new RemoveException(
                'Giftcard was already removed'
            );
        }
    }

    /**
     * Update gift card transaction with the refunded amount
     *
     * @param GroupTransaction $giftcardTransaction
     * @param float $amount
     * @return void
     * @throws CouldNotSaveException
     */
    protected function updateGiftcardTransactionAmount(
        GroupTransaction $giftcardTransaction,
        float $amount
    ): void {
        $giftcardTransaction->setRefundedAmount(
            $giftcardTransaction->getRefundedAmount() + $amount
        );
        $this->groupTransactionRepository->save($giftcardTransaction);
    }
}
