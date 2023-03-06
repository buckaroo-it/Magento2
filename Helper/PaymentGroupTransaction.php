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

namespace Buckaroo\Magento2\Helper;

use Magento\Framework\App\Helper\Context;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Magento\Sales\Model\Order as OrderModel;
use Buckaroo\Magento2\Logging\Log;
use Magento\Sales\Api\Data\TransactionInterface;
use Magento\Sales\Model\Order\Payment\Transaction;
use Buckaroo\Magento2\Model\GroupTransactionFactory;
use Buckaroo\Magento2\Model\ResourceModel\GroupTransaction;
use Buckaroo\Magento2\Model\ResourceModel\GroupTransaction\CollectionFactory as GroupTransactionCollectionFactory;

class PaymentGroupTransaction extends \Magento\Framework\App\Helper\AbstractHelper
{
    /**
     * @var \Magento\Framework\Stdlib\DateTime\DateTime
     */
    protected $dateTime;

    /**
     * @var GroupTransactionFactory
     */
    protected $groupTransactionFactory;

    /**
     * @var \Buckaroo\Magento2\Model\ResourceModel\GroupTransaction\CollectionFactory
     */
    protected $grTrCollectionFactory;

    /**
     * @var \Buckaroo\Magento2\Model\ResourceModel\GroupTransaction
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
        \Magento\Framework\App\Helper\Context $context,
        GroupTransactionFactory $groupTransactionFactory,
        \Magento\Framework\Stdlib\DateTime\DateTime $dateTime,
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

    public function saveGroupTransaction($response)
    {
        $this->logging->addDebug(__METHOD__ . '|1|' . var_export($response, true));
        $groupTransaction           = $this->groupTransactionFactory->create();
        $data['order_id']           = $response['Invoice'];
        $data['transaction_id']     = $response['Key'];
        $data['relatedtransaction'] = $response['RequiredAction']['PayRemainderDetails']['GroupTransaction'] ?? null;
        $data['servicecode']        = $response['ServiceCode'];
        $data['currency']           = $response['Currency'];
        $data['amount']             = $response['AmountDebit'];
        $data['type']               = $response['RelatedTransactions'][0]['RelationType'] ?? null;
        $data['status']             = $response['Status']['Code']['Code'];
        $data['created_at']         = $this->dateTime->gmtDate();
        $groupTransaction->setData($data);
        return $groupTransaction->save();
    }

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

    public function getGroupTransactionItems($order_id)
    {
        $collection = $this->groupTransactionFactory->create()
            ->getCollection()
            ->addFieldToFilter(
                'order_id',
                ['eq' => $order_id]
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
     * @param string|null $order_id
     *
     * @return float
     */
    public function getAlreadyPaid($order_id)
    {
        if ($order_id === null) {
            return 0;
        }
        return $this->getGroupTransactionAmount($order_id);
    }

    public function getGroupTransactionAmount($order_id)
    {
        $total = 0;
        foreach ($this->getGroupTransactionItems($order_id) as $value) {
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
     * @return \Buckaroo\Magento2\Model\GroupTransaction|null
     */
    public function getGroupTransactionOriginalTransactionKey($orderId)
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

    public function getGroupTransactionItemsNotRefunded($order_id)
    {
        $collection = $this->groupTransactionFactory->create()
            ->getCollection()
            ->addFieldToFilter('order_id', ['eq' => $order_id])
            ->addFieldToFilter('refunded_amount', ['null' => true]);
        return array_values($collection->getItems());
    }


    public function getGroupTransactionById($entity_id)
    {
        $collection = $this->groupTransactionFactory->create()
            ->getCollection()
            ->addFieldToFilter('entity_id', ['eq' => $entity_id]);
        return $collection->getItems();
    }

    public function getGroupTransactionByTrxId($trx_id)
    {
        return $this->groupTransactionFactory->create()
            ->getCollection()
            ->addFieldToFilter('transaction_id', ['eq' => $trx_id])->getItems();
    }
    /**
     * Get successful group transactions for orderId
     * with giftcard label
     *
     * @param string|null $orderId
     *
     *@return \Buckaroo\Magento2\Model\GroupTransaction[]
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
     * Get successful group transaction dor transaction id
     * with giftcard label
     *
     * @param string $orderId
     *
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
     *
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
