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

namespace Buckaroo\Magento2\Model\Giftcard\Api;

use Buckaroo\Magento2\Api\Data\Giftcard\GetTransactionsResponseInterface;
use Buckaroo\Magento2\Api\Data\Giftcard\TransactionResponseInterfaceFactory;
use Buckaroo\Magento2\Helper\PaymentGroupTransaction;
use Magento\Framework\DataObject;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\QuoteIdMaskFactory;

class GetTransactionsResponse extends DataObject implements GetTransactionsResponseInterface
{
    /**
     * @var \Magento\Quote\Model\QuoteIdMaskFactory
     */
    protected QuoteIdMaskFactory $quoteIdMaskFactory;

    /**
     * @var \Magento\Quote\Api\CartRepositoryInterface
     */
    protected CartRepositoryInterface $cartRepository;

    /**
     * @var \Buckaroo\Magento2\Helper\PaymentGroupTransaction
     */
    protected PaymentGroupTransaction $groupTransaction;

    /**
     * @var  \Buckaroo\Magento2\Api\Data\Giftcard\TransactionResponseInterfaceFactory
     */
    protected TransactionResponseInterfaceFactory $trResponseFactory;

    /**
     * @var Quote
     */
    protected Quote $quote;

    /**
     * @param QuoteIdMaskFactory $quoteIdMaskFactory
     * @param CartRepositoryInterface $cartRepository
     * @param PaymentGroupTransaction $groupTransaction
     * @param TransactionResponseInterfaceFactory $trResponseFactory
     * @param string|null $cartId
     * @throws NoQuoteException
     */
    public function __construct(
        QuoteIdMaskFactory $quoteIdMaskFactory,
        CartRepositoryInterface $cartRepository,
        PaymentGroupTransaction $groupTransaction,
        TransactionResponseInterfaceFactory $trResponseFactory,
        string $cartId = null
    ) {
        $this->quoteIdMaskFactory = $quoteIdMaskFactory;
        $this->cartRepository = $cartRepository;
        $this->groupTransaction = $groupTransaction;
        $this->trResponseFactory = $trResponseFactory;
        $this->quote = $this->getQuote($cartId);
    }
    /**
     * Get RemainderAmount
     *
     * @api
     * @return float
     */
    public function getRemainderAmount(): float
    {
        return $this->quote->getGrandTotal() - $this->getAlreadyPaid();
    }
    /**
     * Get AlreadyPaid
     *
     * @api
     * @return float
     */
    public function getAlreadyPaid(): float
    {
        return $this->groupTransaction->getGroupTransactionAmount(
            $this->quote->getReservedOrderId()
        );
    }

    /**
     * Format data for json response
     *
     * @param array $collection
     *
     * @return \Buckaroo\Magento2\Api\Data\Giftcard\TransactionResponseInterface[]
     */
    protected function formatFound(array $collection)
    {
        return array_map(function ($item) {
            return $this->trResponseFactory->create()->addData($item->getData());
        }, $collection);
    }
    /**
     * Get the list of transactions for this cart
     *
     * @param string $cartId
     * @return \Buckaroo\Magento2\Api\Data\Giftcard\TransactionResponseInterface[]
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
     * Get quote from masked cart id
     *
     * @param string|null $cartId
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
}
