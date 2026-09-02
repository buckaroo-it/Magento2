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

use Magento\Framework\Model\AbstractModel;
use Buckaroo\Magento2\Api\Data\GroupTransactionInterface;

class GroupTransaction extends AbstractModel implements GroupTransactionInterface
{
    /**
     * @var string
     */
    protected $_eventPrefix = 'buckaroo_magento2_group_transaction';

    /**
     * @var string
     */
    protected $_eventObject = 'grouptransaction';

    /**
     * Initialize resource model
     */
    protected function _construct()
    {
        $this->_init('Buckaroo\Magento2\Model\ResourceModel\GroupTransaction');
    }

    /**
     * @inheritdoc
     */
    public function getServicecode()
    {
        return $this->getData('servicecode');
    }

    /**
     * @inheritdoc
     */
    public function setServicecode($servicecode)
    {
        return $this->setData('servicecode', $servicecode);
    }

    /**
     * Set name
     *
     * @param string $name
     *
     * @return GroupTransaction
     */
    public function setName(string $name): GroupTransaction
    {
        return $this->setData('name', $name);
    }

    /**
     * Set created at date
     *
     * @param mixed $createdAt
     *
     * @return GroupTransaction
     */
    public function setCreatedAt($createdAt): GroupTransaction
    {
        return $this->setData('created_at', $createdAt);
    }

    /**
     * Set refund amount
     *
     * @param mixed $refundedAmount
     *
     * @return GroupTransaction
     */
    public function setRefundedAmount($refundedAmount): GroupTransaction
    {
        return $this->setData('refunded_amount', $refundedAmount);
    }

    /**
     * Get refund amount
     *
     * @return array|mixed|null
     */
    public function getRefundedAmount()
    {
        return (float)$this->getData('refunded_amount');
    }

    /**
     * Getter for currency
     *
     * @return string
     */
    public function getCurrency()
    {
        return $this->getData('currency');
    }

    /**
     * Getter for amount
     *
     * @return float
     */
    public function getAmount()
    {
        return (float)$this->getData('amount');
    }

    /**
     * Getter for order increment it
     *
     * @return string
     */
    public function getOrderIncrementId()
    {
        return $this->getData('order_id');
    }

    /**
     * Get transaction id
     *
     * @return string
     */
    public function getTransactionId()
    {
        return $this->getData('transaction_id');
    }

    /**
     * Get related transaction id
     *
     * @return string
     */
    public function getRelatedTransaction(): string
    {
        return $this->getData('relatedtransaction');
    }

    /**
     * Transaction is fully refunded
     *
     * @return bool
     */
    public function isFullyRefunded()
    {
        return abs($this->getRemainingAmount()) <= 0.001 ;
    }

    /**
     * Get the amount - any refund made
     *
     * @return float
     */
    public function getRemainingAmount()
    {
        return $this->getAmount() - $this->getRefundedAmount();
    }
}
