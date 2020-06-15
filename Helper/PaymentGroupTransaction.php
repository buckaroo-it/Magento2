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

use Buckaroo\Magento2\Model\GroupTransactionFactory;

use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Email\Sender\InvoiceSender;
use Magento\Sales\Model\Order\Email\Sender\OrderSender;
use Magento\Sales\Model\Order\Payment\Transaction;
use Magento\Sales\Api\Data\TransactionInterface;

use Buckaroo\Magento2\Model\Method\AbstractMethod;

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
     */
    public function __construct(
        \Magento\Framework\App\Helper\Context $context,
        GroupTransactionFactory $groupTransactionFactory,
        \Magento\Framework\Stdlib\DateTime\DateTime $dateTime,
        Order $order,
        TransactionInterface $transaction
    ) {
        parent::__construct($context);

        $this->groupTransactionFactory = $groupTransactionFactory;
        $this->dateTime = $dateTime;

        $this->order                        = $order;
        $this->transaction                  = $transaction;
    }

    public function saveGroupTransaction($response){
        $groupTransaction = $this->groupTransactionFactory->create();
        $data['order_id'] = $response['Invoice'];
        $data['transaction_id'] = $response['Key'];
        $data['relatedtransaction'] = $response['RequiredAction']['PayRemainderDetails']['GroupTransaction'];
        $data['servicecode'] = $response['ServiceCode'];
        $data['currency'] = $response['Currency'];
        $data['amount'] = $response['AmountDebit'];
        $data['type'] = $response['RelatedTransactions'][0]['RelationType'];
        $data['status'] = $response['Status']['Code']['Code'];
        $data['created_at'] = $this->dateTime->gmtDate();
        $groupTransaction->setData($data);
        return $groupTransaction->save();
    }

    public function updateGroupTransaction($item){
        $groupTransaction = $this->groupTransactionFactory->create();
        $groupTransaction->load($item['entity_id']);
        $groupTransaction->setData($item);
        return $groupTransaction->save();
    }

    public function isGroupTransaction($order_id){
        return $this->getGroupTransactionItems($order_id);
    }

    public function getGroupTransactionItems($order_id){
        $collection = $this->groupTransactionFactory->create()->getCollection()->addFieldToFilter('order_id', ['eq' => $order_id]);
        return array_values($collection->getItems());
    }

    public function getGroupTransactionById($entity_id){
        $collection = $this->groupTransactionFactory->create()->getCollection()->addFieldToFilter('entity_id', ['eq' => $entity_id]);
        return $collection->getItems();
    }

    public function getGroupTransactionByTrxId($trx_id){
        return $this->groupTransactionFactory->create()->getCollection()->addFieldToFilter('transaction_id', ['eq' => $trx_id])->getItems();
    }
}
