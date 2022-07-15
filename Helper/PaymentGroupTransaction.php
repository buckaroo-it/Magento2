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

use Magento\Sales\Model\Order;
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

    protected $groupTransactionFactory;

    /**
     * @var Order $order
     */
    public $order;

    /** @var Transaction */
    private $transaction;

    /**
     * @var \Buckaroo\Magento2\Model\ResourceModel\GroupTransaction\CollectionFactory
     */
    protected $grTrCollectionFactory;

    /**
     * @var \Buckaroo\Magento2\Model\ResourceModel\GroupTransaction
     */
    protected $resourceModel;

    /**
     */
    public function __construct(
        \Magento\Framework\App\Helper\Context $context,
        GroupTransactionFactory $groupTransactionFactory,
        \Magento\Framework\Stdlib\DateTime\DateTime $dateTime,
        Order $order,
        TransactionInterface $transaction,
        Log $logging,
        GroupTransactionCollectionFactory $grTrCollectionFactory,
        GroupTransaction $resourceModel
    ) {
        parent::__construct($context);

        $this->groupTransactionFactory = $groupTransactionFactory;
        $this->dateTime                = $dateTime;

        $this->order       = $order;
        $this->transaction = $transaction;
        $this->logging     = $logging;
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

    public function isGroupTransaction($order_id)
    {
        return $this->getGroupTransactionItems($order_id);
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

        return array_filter($items, function($item) {
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
        foreach ($this->getGroupTransactionItems($order_id) as $key => $value) {
            if ($value['status'] == '190') {
                $total += $value['amount'] - (float)$value['refunded_amount'];
            }
        }
        return $total;
    }

    public function getGroupTransactionOriginalTransactionKey($order_id)
    {
        if($order_id === null) {
            return;
        }
        $collection = $this->grTrCollectionFactory->create();
        $groupTransaction = $collection
            ->addFieldToFilter(
                'order_id',
                ['eq' => $order_id]
            )
            ->addFieldToFilter(
                'status',
                ['eq' => '190']
            )->setOrder('entity_id','DESC')
            ->getFirstItem();
            if (!$groupTransaction->isEmpty()) {
                return $groupTransaction->getData('relatedtransaction');
            }
            return;
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
        if($orderId === null) {
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
    /**
     * Check to see if we have any transactions with that orderId
     *
     * @param string $orderId
     *
     * @return void
     */
    public function existsOrderId(string $orderId)
    {
        $collection = $this->grTrCollectionFactory->create();
        $groupTransaction = $collection
            ->addFieldToFilter(
                'order_id',
                ['eq' => $orderId]
            )
            ->getFirstItem();
        return !$groupTransaction->isEmpty();
    }
}
