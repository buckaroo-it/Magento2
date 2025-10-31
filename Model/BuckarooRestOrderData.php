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

namespace Buckaroo\Magento2\Model;

use Buckaroo\Magento2\Api\Data\BuckarooRestOrderDataInterface;
use Buckaroo\Magento2\Api\Data\Giftcard\TransactionResponseInterfaceFactory;
use Buckaroo\Magento2\Helper\PaymentGroupTransaction;

class BuckarooRestOrderData implements BuckarooRestOrderDataInterface
{
    private $orderIncrementId;

    private $groupTransaction;

    /**
     * @var TransactionResponseInterfaceFactory
     */
    private $trResponseFactory;

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
