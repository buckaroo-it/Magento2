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

namespace Buckaroo\Magento2\Helper;

use Buckaroo\Magento2\Logging\Log;
use Buckaroo\Magento2\Model\GroupTransactionFactory;
use Buckaroo\Magento2\Model\ResourceModel\GroupTransaction;
use Buckaroo\Magento2\Model\ResourceModel\GroupTransaction\CollectionFactory as GroupTransactionCollectionFactory;
use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\Stdlib\DateTime\DateTime;

class PaymentGroupTransaction extends AbstractHelper
{
    /**
     * @var DateTime
     */
    protected $dateTime;

    /**
     * @var GroupTransactionFactory
     */
    protected $groupTransactionFactory;

    /**
     * @var GroupTransactionCollectionFactory
     */
    protected $grTrCollectionFactory;

    /**
     * @var GroupTransaction
     */
    protected $resourceModel;

    /**
     * @var Log
     */
    private Log $logging;

    /**
     * Constructor
     *
     * @param Context $context
     * @param GroupTransactionFactory $groupTransactionFactory
     * @param DateTime $dateTime
     * @param Log $logging
     * @param GroupTransactionCollectionFactory $grTrCollectionFactory
     * @param GroupTransaction $resourceModel
     */
    public function __construct(
        Context $context,
        GroupTransactionFactory $groupTransactionFactory,
        DateTime $dateTime,
        Log $logging,
        GroupTransactionCollectionFactory $grTrCollectionFactory,
        GroupTransaction $resourceModel
    ) {
        parent::__construct($context);

        $this->groupTransactionFactory = $groupTransactionFactory;
        $this->dateTime = $dateTime;
        $this->logging = $logging;
        $this->grTrCollectionFactory = $grTrCollectionFactory;
        $this->resourceModel = $resourceModel;
    }

    /**
     * Saves a group transaction in the database.
     *
     * @param array $response
     * @return mixed
     */
    public function saveGroupTransaction($response)
    {
        $this->logging->addDebug(__METHOD__ . '|1|' . var_export($response, true));
        $groupTransaction = $this->groupTransactionFactory->create();
        $data['order_id'] = $response['Invoice'];
        $data['transaction_id'] = $response['Key'];
        $data['relatedtransaction'] = $response['RequiredAction']['PayRemainderDetails']['GroupTransaction'] ?? null;
        $data['servicecode'] = $response['ServiceCode'];
        $data['currency'] = $response['Currency'];
        $data['amount'] = $response['AmountDebit'];
        $data['type'] = $response['RelatedTransactions'][0]['RelationType'] ?? null;
        $data['status'] = $response['Status']['Code']['Code'];
        $data['created_at'] = $this->dateTime->gmtDate();
        $groupTransaction->setData($data);
        return $groupTransaction->save();
    }

    /**
     * Updates a group transaction in the database.
     *
     * @param array $item
     * @return mixed
     * @throws \Exception
     */
    public function updateGroupTransaction($item)
    {
        $groupTransaction = $this->groupTransactionFactory->create();
        $groupTransaction->load($item['entity_id']);
        $groupTransaction->setData($item);
        return $groupTransaction->save();
    }

    /**
     * Check if is group transaction the order
     *
     * @param string|int $orderId
     * @return bool
     */
    public function isGroupTransaction($orderId)
    {
        $groupTransactions = $this->getGroupTransactionItems($orderId);
        return is_array($groupTransactions) && count($groupTransactions) > 0;
    }

    /**
     * Retrieves the group transaction items for a given order ID.
     *
     * @param string|int $orderId
     * @return array
     */
    public function getGroupTransactionItems($orderId)
    {
        $collection = $this->groupTransactionFactory->create()
            ->getCollection()
            ->addFieldToFilter(
                'order_id',
                ['eq' => $orderId]
            )
            ->addFieldToFilter(
                'status',
                ['eq' => '190']
            );
        $items = array_values($collection->getItems());

        return array_filter($items, function ($item) {
            return $item['amount'] - (float)$item['refunded_amount'] > 0;
        });
    }

    /**
     * Get already paid amount from db
     *
     * @param string|int|null $orderId
     * @return float
     */
    public function getAlreadyPaid($orderId)
    {
        if ($orderId === null) {
            return 0;
        }
        return $this->getGroupTransactionAmount($orderId);
    }

    /**
     * Calculates the total amount of group transactions for a given order ID.
     *
     * @param string|int $orderId
     * @return float|int
     */
    public function getGroupTransactionAmount($orderId)
    {
        $total = 0;
        foreach ($this->getGroupTransactionItems($orderId) as $value) {
            if ($value['status'] == '190') {
                $total += $value['amount'] - (float)$value['refunded_amount'];
            }
        }
        return $total;
    }

    /**
     * Get last transaction from group transaction filter by order
     *
     * @param string|int $orderId
     * @return string|null
     */
    public function getGroupTransactionOriginalTransactionKey($orderId): ?string
    {
        if ($orderId === null) {
            return null;
        }
        $collection = $this->grTrCollectionFactory->create();
        $groupTransaction = $collection
            ->addFieldToFilter(
                'order_id',
                ['eq' => $orderId]
            )
            ->addFieldToFilter(
                'status',
                ['eq' => '190']
            )->setOrder('entity_id', 'DESC')
            ->getFirstItem();
        if (!$groupTransaction->isEmpty()) {
            return $groupTransaction->getData('relatedtransaction');
        }

        return null;
    }

    /**
     * Retrieves the group transaction items that have not been refunded for a given order ID.
     *
     * @param string|int $orderId
     * @return array
     */
    public function getGroupTransactionItemsNotRefunded($orderId)
    {
        $collection = $this->groupTransactionFactory->create()
            ->getCollection()
            ->addFieldToFilter('order_id', ['eq' => $orderId])
            ->addFieldToFilter('refunded_amount', ['null' => true]);
        return array_values($collection->getItems());
    }

    /**
     * Retrieves the group transaction item for a given entity ID.
     *
     * @param int|string $entityId
     * @return mixed
     */
    public function getGroupTransactionById($entityId)
    {
        $collection = $this->groupTransactionFactory->create()
            ->getCollection()
            ->addFieldToFilter('entity_id', ['eq' => $entityId]);
        return $collection->getItems();
    }

    /**
     * Retrieves the group transaction item for a given transaction ID.
     *
     * @param int|string $trxId
     * @return mixed
     */
    public function getGroupTransactionByTrxId($trxId)
    {
        return $this->groupTransactionFactory->create()
            ->getCollection()
            ->addFieldToFilter('transaction_id', ['eq' => $trxId])->getItems();
    }

    /**
     * Get successful group transactions for orderId with giftcard label
     *
     * @param string|null $orderId
     * @return \Buckaroo\Magento2\Model\GroupTransaction[]
     */
    public function getActiveItemsWithName($orderId)
    {
        if ($orderId === null) {
            return [];
        }

        $collection = $this->grTrCollectionFactory->create();
        $collection
            ->addFieldToFilter(
                'order_id',
                ['eq' => $orderId]
            )
            ->addFieldToFilter(
                'status',
                ['eq' => '190']
            )
            ->getSelect()
            ->joinLeft(
                ['buckaroo_magento2_giftcard'],
                'main_table.servicecode = buckaroo_magento2_giftcard.servicecode',
                ['buckaroo_magento2_giftcard.label']
            );
        return $collection->getItems();
    }

    /**
     * Get successful group transaction dor transaction id with giftcard label
     *
     * @param string $transactionId
     * @return \Buckaroo\Magento2\Model\GroupTransaction
     */
    public function getByTransactionIdWithName(string $transactionId)
    {
        $collection = $this->grTrCollectionFactory->create();
        $collection
            ->addFieldToFilter(
                'transaction_id',
                ['eq' => $transactionId]
            )
            ->getSelect()
            ->joinLeft(
                ['buckaroo_magento2_giftcard'],
                'main_table.servicecode = buckaroo_magento2_giftcard.servicecode',
                ['buckaroo_magento2_giftcard.label']
            );
        return $collection->getFirstItem();
    }

    /**
     * Set status to all transactions in a group
     *
     * @param string $groupTransactionId
     * @param string $status
     * @return void
     */
    public function setGroupTransactionsStatus(string $groupTransactionId, string $status)
    {
        $this->resourceModel
            ->getConnection()
            ->update(
                $this->resourceModel->getTable('buckaroo_magento2_group_transaction'),
                [
                    'status' => $status
                ],
                [
                    'relatedtransaction = ?' => $groupTransactionId
                ]
            );
    }
}
