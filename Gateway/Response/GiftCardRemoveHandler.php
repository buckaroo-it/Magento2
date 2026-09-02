<?php
/**
 * Buckaroo Magento 2 payment module (https://www.buckaroo.eu/)
 *
 * Copyright (c) Buckaroo B.V.
 * See LICENSE for license details.
 *
 * Support: support@buckaroo.nl
 *
 * @copyright Copyright (c) Buckaroo B.V.
 * @license   MIT
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
    protected $messageManager;

    /**
     * @var GroupTransactionRepository
     */
    protected $groupTransactionRepository;

    /**
     * @param MessageManager             $messageManager
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
     *
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
     * @param float            $amount
     *
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
