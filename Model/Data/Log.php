<?php
/**
 * Copyright ©  All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Buckaroo\Magento2\Model\Data;

use Buckaroo\Magento2\Api\Data\LogInterface;

class Log extends \Magento\Framework\Api\AbstractExtensibleObject implements LogInterface
{

    /**
     * Get log_id
     * @return string|null
     */
    public function getLogId()
    {
        return $this->_get(self::LOG_ID);
    }

    /**
     * Set log_id
     * @param string $logId
     * @return \Buckaroo\Magento2\Api\Data\LogInterface
     */
    public function setLogId($logId)
    {
        return $this->setData(self::LOG_ID, $logId);
    }

    /**
     * Retrieve existing extension attributes object or create a new one.
     * @return \Buckaroo\Magento2\Api\Data\LogExtensionInterface|null
     */
    public function getExtensionAttributes()
    {
        return $this->_getExtensionAttributes();
    }

    /**
     * Set an extension attributes object.
     * @param \Buckaroo\Magento2\Api\Data\LogExtensionInterface $extensionAttributes
     * @return $this
     */
    public function setExtensionAttributes(
        \Buckaroo\Magento2\Api\Data\LogExtensionInterface $extensionAttributes
    ) {
        return $this->_setExtensionAttributes($extensionAttributes);
    }

    /**
     * Get channel
     * @return string|null
     */
    public function getChannel()
    {
        return $this->_get(self::CHANNEL);
    }

    /**
     * Set channel
     * @param string $channel
     * @return \Buckaroo\Magento2\Api\Data\LogInterface
     */
    public function setChannel($channel)
    {
        return $this->setData(self::CHANNEL, $channel);
    }

    /**
     * Get level
     * @return string|null
     */
    public function getLevel()
    {
        return $this->_get(self::LEVEL);
    }

    /**
     * Set level
     * @param string $level
     * @return \Buckaroo\Magento2\Api\Data\LogInterface
     */
    public function setLevel($level)
    {
        return $this->setData(self::LEVEL, $level);
    }

    /**
     * Get message
     * @return string|null
     */
    public function getMessage()
    {
        return $this->_get(self::MESSAGE);
    }

    /**
     * Set message
     * @param string $message
     * @return \Buckaroo\Magento2\Api\Data\LogInterface
     */
    public function setMessage($message)
    {
        return $this->setData(self::MESSAGE, $message);
    }

    /**
     * Get timestamp
     * @return string|null
     */
    public function getTimestamp()
    {
        return $this->_get(self::TIMESTAMP);
    }

    /**
     * Set timestamp
     * @param string $timestamp
     * @return \Buckaroo\Magento2\Api\Data\LogInterface
     */
    public function setTimestamp($timestamp)
    {
        return $this->setData(self::TIMESTAMP, $timestamp);
    }

    /**
     * Get session_id
     * @return string|null
     */
    public function getSessionId()
    {
        return $this->_get(self::SESSION_ID);
    }

    /**
     * Set session_id
     * @param string $sessionId
     * @return \Buckaroo\Magento2\Api\Data\LogInterface
     */
    public function setSessionId($sessionId)
    {
        return $this->setData(self::SESSION_ID, $sessionId);
    }

    /**
     * Get customer_id
     * @return string|null
     */
    public function getCustomerId()
    {
        return $this->_get(self::CUSTOMER_ID);
    }

    /**
     * Set customer_id
     * @param string $customerId
     * @return \Buckaroo\Magento2\Api\Data\LogInterface
     */
    public function setCustomerId($customerId)
    {
        return $this->setData(self::CUSTOMER_ID, $customerId);
    }

    /**
     * Get quote_id
     * @return string|null
     */
    public function getQuoteId()
    {
        return $this->_get(self::QUOTE_ID);
    }

    /**
     * Set quote_id
     * @param string $quoteId
     * @return \Buckaroo\Magento2\Api\Data\LogInterface
     */
    public function setQuoteId($quoteId)
    {
        return $this->setData(self::QUOTE_ID, $quoteId);
    }

    /**
     * Get order_id
     * @return string|null
     */
    public function getOrderId()
    {
        return $this->_get(self::ORDER_ID);
    }

    /**
     * Set order_id
     * @param string $orderId
     * @return \Buckaroo\Magento2\Api\Data\LogInterface
     */
    public function setOrderId($orderId)
    {
        return $this->setData(self::ORDER_ID, $orderId);
    }
}
