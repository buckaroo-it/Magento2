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

use Buckaroo\Magento2\Logging\BuckarooLoggerInterface;
use Buckaroo\Magento2\Model\GroupTransactionFactory;
use Buckaroo\Magento2\Model\ResourceModel\GroupTransaction as GroupTransactionResource;
use Buckaroo\Magento2\Model\GroupTransaction;
use Buckaroo\Magento2\Model\ResourceModel\GroupTransaction\CollectionFactory as GroupTransactionCollectionFactory;
use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\App\ResourceConnection;
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
     * @var GroupTransactionResource
     */
    protected $resourceModel;

    /**
     * @var BuckarooLoggerInterface
     */
    private BuckarooLoggerInterface $logger;

    protected ResourceConnection $resourceConnection;


    /**
     * Constructor
     *
     * @param Context $context
     * @param GroupTransactionFactory $groupTransactionFactory
     * @param DateTime $dateTime
     * @param BuckarooLoggerInterface $logger
     * @param GroupTransactionCollectionFactory $grTrCollectionFactory
     * @param GroupTransactionResource $resourceModel
     * @param ResourceConnection|null $resourceConnection
     */
    public function __construct(
        Context $context,
        GroupTransactionFactory $groupTransactionFactory,
        DateTime $dateTime,
        BuckarooLoggerInterface $logger,
        GroupTransactionCollectionFactory $grTrCollectionFactory,
        GroupTransactionResource $resourceModel,
        ResourceConnection $resourceConnection
    ) {
        parent::__construct($context);

        $this->groupTransactionFactory = $groupTransactionFactory;
        $this->dateTime = $dateTime;
        $this->logger = $logger;
        $this->grTrCollectionFactory = $grTrCollectionFactory;
        $this->resourceModel = $resourceModel;
        $this->resourceConnection = $resourceConnection;
    }

    /**
     * Get additional information when there's a partial payment.
     *
     * @param integer $incrementId
     * @return mixed
     */
    public function getAdditionalData($incrementId)
    {
        $connection = $this->resourceConnection->getConnection();

        $tableName = $this->resourceConnection->getTableName('sales_order_payment');

        $select = $connection->select()
            ->from($tableName)
            ->where('parent_id = ?', $incrementId)
            ->order('entity_id DESC')
            ->limit(1);

        $result = $connection->fetchRow($select);

        return json_decode($result["additional_information"], true);
    }

    /**
     * Saves a group transaction in the database.
     *
     * @param array $response
     * @return mixed
     */
    public function saveGroupTransaction($response)
    {
        $this->logger->addDebug(sprintf(
            '[GROUP_TRANSACTION] | [Helper] | [%s:%s] - Save group transaction in database | response: %s',
            __METHOD__,
            __LINE__,
            var_export($response, true)
        ));

        $groupTransaction = $this->groupTransactionFactory->create();
        $data['order_id'] = $response['Invoice'];
        $data['transaction_id'] = $response['Key'];
        $data['relatedtransaction'] = $response['RequiredAction']['PayRemainderDetails']['GroupTransaction'] ??
            $response['RelatedTransactions'][0]['RelatedTransactionKey'] ?? null;
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
     * Check if is group transaction the order
     *
     * @param string|int $orderId
     * @return bool
     */
    public function isAnyGroupTransaction($orderId)
    {
        $groupTransactions = $this->getAnyGroupTransactionItems($orderId);
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
            return (float)$item['amount'] - (float)$item['refunded_amount'] > 0;
        });
    }

    /**
     * Retrieves the group transaction items for a given order ID.
     *
     * @param string|int $orderId
     * @return array
     */
    public function getAnyGroupTransactionItems($orderId)
    {
        $collection = $this->groupTransactionFactory->create()
            ->getCollection()
            ->addFieldToFilter(
                'order_id',
                ['eq' => $orderId]
            );
        $items = array_values($collection->getItems());

        return array_filter($items, function ($item) {
            return (float)$item['amount'] - (float)$item['refunded_amount'] > 0;
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
                $total += (float)$value['amount'] - (float)$value['refunded_amount'];
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
            ->addFieldToFilter('order_id', $orderId)
            ->addFieldToFilter('status', '190')
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
     * @return GroupTransaction
     */
    public function getGroupTransactionByTrxId($trxId)
    {
        $collection = $this->groupTransactionFactory->create()
            ->getCollection()
            ->addFieldToFilter('transaction_id', $trxId);

        return $collection->getFirstItem();
    }

    /**
     * Get successful group transactions for orderId with giftcard label
     *
     * @param string|null $orderId
     * @return GroupTransaction[]
     */
    public function getActiveItemsWithName($orderId)
    {
        if ($orderId === null) {
            return [];
        }

        $tableName = $this->resourceModel->getTable('buckaroo_magento2_giftcard');
        $connection = $this->resourceModel->getConnection();

        $collection = $this->grTrCollectionFactory->create()
            ->addFieldToFilter('order_id', ['eq' => $orderId])
            ->addFieldToFilter('status', ['eq' => '190']);

        if ($connection->isTableExists($tableName)) {
            $collection->getSelect()->joinLeft(
                [$tableName],
                "main_table.servicecode = {$tableName}.servicecode",
                ["{$tableName}.label"]
            );
        }

        return $collection->getItems();
    }

    /**
     * Get successful group transaction dor transaction id with giftcard label
     *
     * @param string $transactionId
     * @return GroupTransaction
     */
    public function getByTransactionIdWithName(string $transactionId)
    {
        $tableName = $this->resourceModel->getTable('buckaroo_magento2_giftcard');
        $connection = $this->resourceModel->getConnection();

        $collection = $this->grTrCollectionFactory->create()
            ->addFieldToFilter('transaction_id', ['eq' => $transactionId]);

        if ($connection->isTableExists($tableName)) {
            $collection->getSelect()->joinLeft(
                [$tableName],
                "main_table.servicecode = {$tableName}.servicecode",
                ["{$tableName}.label"]
            );
        }

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
                ['status' => $status],
                ['relatedtransaction = ?' => $groupTransactionId]
            );
    }
}
