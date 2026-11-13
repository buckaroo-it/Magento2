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

namespace Buckaroo\Magento2\Model;

use Buckaroo\Magento2\Api\Data\BuckarooRestOrderDataInterface;
use Buckaroo\Magento2\Api\Data\Giftcard\TransactionResponseInterfaceFactory;
use Buckaroo\Magento2\Helper\PaymentGroupTransaction;

class BuckarooRestOrderData implements BuckarooRestOrderDataInterface
{
    /**
     * @var string
     */
    private $orderIncrementId;

    /**
     * @var PaymentGroupTransaction
     */
    private $groupTransaction;

    /**
     * @var TransactionResponseInterfaceFactory
     */
    private $trResponseFactory;

    /**
     * Constructor
     *
     * @param string $orderIncrementId
     * @param PaymentGroupTransaction $groupTransaction
     * @param TransactionResponseInterfaceFactory $trResponseFactory
     */
    public function __construct(
        string $orderIncrementId,
        PaymentGroupTransaction $groupTransaction,
        TransactionResponseInterfaceFactory $trResponseFactory
    ) {
        $this->orderIncrementId = $orderIncrementId;
        $this->groupTransaction = $groupTransaction;
        $this->trResponseFactory = $trResponseFactory;
    }

    /**
     * Get active group transactions for the order
     *
     * @return \Buckaroo\Magento2\Api\Data\Giftcard\TransactionResponseInterface[]
     */
    public function getGroupTransactions()
    {
        return $this->formatFound(
            $this->groupTransaction->getActiveItemsWithName(
                $this->orderIncrementId
            )
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
}
