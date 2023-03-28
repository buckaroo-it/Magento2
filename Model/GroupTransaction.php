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
     *
     * @return void
     */
    protected function _construct()
    {
        $this->_init('Buckaroo\Magento2\Model\ResourceModel\GroupTransaction');
    }

    /**
     * @return string
     */
    public function getServicecode()
    {
        return $this->getData('servicecode');
    }

    /**
     * @param string $servicecode
     *
     * @return $this
     */
    public function setServicecode($servicecode)
    {
        return $this->setData('servicecode', $servicecode);
    }

    /**
     * {@inheritdoc}
     */
    public function setName($name)
    {
        return $this->setData('name', $name);
    }

    /**
     * {@inheritdoc}
     */
    public function setCreatedAt($createdAt)
    {
        return $this->setData('created_at', $createdAt);
    }

    /**
     * {@inheritdoc}
     */
    public function setRefundedAmount($refundedAmount)
    {
        return $this->setData('refunded_amount', $refundedAmount);
    }

    /**
     * @return float
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
     * Transaction is fully refunded
     *
     * @return boolean
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