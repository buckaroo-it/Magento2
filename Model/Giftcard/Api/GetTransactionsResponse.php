<?php

/**
 * NOTICE OF LICENSE
 *
 * This source file is subject to the MIT License
 * It is available through the world-wide-web at this URL:
 * https://tldrlegal.com/license/mit-license
 * If you are unable to obtain it through the world-wide-web, please send an email
 * to support@buckaroo.nl so we can send you a copy immediately.
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

namespace Buckaroo\Magento2\Model\Giftcard\Api;

use Magento\Framework\DataObject;
use Magento\Quote\Model\QuoteIdMaskFactory;
use Magento\Quote\Api\CartRepositoryInterface;
use Buckaroo\Magento2\Helper\PaymentGroupTransaction;
use Buckaroo\Magento2\Model\Giftcard\Api\NoQuoteException;
use Buckaroo\Magento2\Api\Data\Giftcard\GetTransactionsResponseInterface;
use Buckaroo\Magento2\Api\Data\Giftcard\TransactionResponseInterfaceFactory;

class GetTransactionsResponse extends DataObject implements GetTransactionsResponseInterface
{
    /**
     * @var \Magento\Quote\Model\QuoteIdMaskFactory
     */
    protected $quoteIdMaskFactory;

    /**
     * @var \Magento\Quote\Api\CartRepositoryInterface
     */
    protected $cartRepository;

    /**
     * @var \Buckaroo\Magento2\Helper\PaymentGroupTransaction
     */
    protected $groupTransaction;

    /**
     * @var  \Buckaroo\Magento2\Api\Data\Giftcard\TransactionResponseInterfaceFactory
     */
    protected $trResponseFactory;


    protected $quote;

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
    public function getRemainderAmount()
    {
        return $this->quote->getGrandTotal() - $this->getAlreadyPaid();
    }
    /**
     * Get AlreadyPaid
     *
     * @api
     * @return float
     */
    public function getAlreadyPaid()
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
    public function getTransactions()
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
    protected function getQuote($cartId)
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
