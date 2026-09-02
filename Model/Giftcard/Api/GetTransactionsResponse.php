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

namespace Buckaroo\Magento2\Model\Giftcard\Api;

use Buckaroo\Magento2\Api\Data\Giftcard\GetTransactionsResponseInterface;
use Buckaroo\Magento2\Api\Data\Giftcard\TransactionResponseInterface;
use Buckaroo\Magento2\Api\Data\Giftcard\TransactionResponseInterfaceFactory;
use Buckaroo\Magento2\Helper\PaymentGroupTransaction;
use Magento\Framework\DataObject;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\QuoteIdMaskFactory;

class GetTransactionsResponse extends DataObject implements GetTransactionsResponseInterface
{
    /**
     * @var QuoteIdMaskFactory
     */
    protected $quoteIdMaskFactory;

    /**
     * @var CartRepositoryInterface
     */
    protected $cartRepository;

    /**
     * @var PaymentGroupTransaction
     */
    protected $groupTransaction;

    /**
     * @var TransactionResponseInterfaceFactory
     */
    protected $trResponseFactory;

    /**
     * @var Quote
     */
    protected $quote;

    /**
     * @param QuoteIdMaskFactory                  $quoteIdMaskFactory
     * @param CartRepositoryInterface             $cartRepository
     * @param PaymentGroupTransaction             $groupTransaction
     * @param TransactionResponseInterfaceFactory $trResponseFactory
     * @param string|null                         $cartId
     *
     * @throws NoQuoteException
     */
    public function __construct(
        QuoteIdMaskFactory $quoteIdMaskFactory,
        CartRepositoryInterface $cartRepository,
        PaymentGroupTransaction $groupTransaction,
        TransactionResponseInterfaceFactory $trResponseFactory,
        ?string $cartId = null
    ) {
        $this->quoteIdMaskFactory = $quoteIdMaskFactory;
        $this->cartRepository = $cartRepository;
        $this->groupTransaction = $groupTransaction;
        $this->trResponseFactory = $trResponseFactory;
        $this->quote = $this->getQuote($cartId);
    }

    /**
     * Get quote from masked cart id
     *
     * @param string|null $cartId
     *
     * @throws NoQuoteException
     *
     * @return Quote
     */
    protected function getQuote(?string $cartId): Quote
    {
        try {
            $quoteIdMask = $this->quoteIdMaskFactory->create()->load($cartId, 'masked_id');
            /** @var Quote $quote */
            return $this->cartRepository->getActive($quoteIdMask->getQuoteId());
        } catch (\Throwable $th) {
            throw new NoQuoteException(__("The cart isn't active."), 0, $th);
        }
    }

    /**
     * Get RemainderAmount
     *
     * @return float
     *
     * @api
     */
    public function getRemainderAmount(): float
    {
        return $this->quote->getGrandTotal() - $this->getAlreadyPaid();
    }

    /**
     * Get AlreadyPaid
     *
     * @return float
     *
     * @api
     */
    public function getAlreadyPaid(): float
    {
        return $this->groupTransaction->getGroupTransactionAmount(
            $this->quote->getReservedOrderId()
        );
    }

    /**
     * Get the list of transactions for this cart
     *
     * @return TransactionResponseInterface[]
     */
    public function getTransactions(): array
    {
        return $this->formatFound(
            $this->groupTransaction->getActiveItemsWithName(
                $this->quote->getReservedOrderId()
            )
        );
    }

    /**
     * Format data for json response
     *
     * @param array $collection
     *
     * @return TransactionResponseInterface[]
     */
    protected function formatFound(array $collection): array
    {
        return array_map(function ($item) {
            return $this->trResponseFactory->create()->addData($item->getData());
        }, $collection);
    }
}
