<?php
/**
 * Copyright ©  All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Buckaroo\Magento2\Api\Data;

interface LogInterface extends \Magento\Framework\Api\ExtensibleDataInterface
{

    const TIMESTAMP = 'timestamp';
    const SESSION_ID = 'session_id';
    const CHANNEL = 'channel';
    const QUOTE_ID = 'quote_id';
    const LEVEL = 'level';
    const ORDER_ID = 'order_id';
    const LOG_ID = 'log_id';
    const MESSAGE = 'message';
    const CUSTOMER_ID = 'customer_id';

    /**
     * Get log_id
     * @return string|null
     */
    public function getLogId();

    /**
     * Set log_id
     * @param string $logId
     * @return \Buckaroo\Magento2\Api\Data\LogInterface
     */
    public function setLogId($logId);

    /**
     * Retrieve existing extension attributes object or create a new one.
     * @return \Buckaroo\Magento2\Api\Data\LogExtensionInterface|null
     */
    public function getExtensionAttributes();

    /**
     * Set an extension attributes object.
     * @param \Buckaroo\Magento2\Api\Data\LogExtensionInterface $extensionAttributes
     * @return $this
     */
    public function setExtensionAttributes(
        \Buckaroo\Magento2\Api\Data\LogExtensionInterface $extensionAttributes
    );

    /**
     * Get channel
     * @return string|null
     */
    public function getChannel();

    /**
     * Set channel
     * @param string $channel
     * @return \Buckaroo\Magento2\Api\Data\LogInterface
     */
    public function setChannel($channel);

    /**
     * Get level
     * @return string|null
     */
    public function getLevel();

    /**
     * Set level
     * @param string $level
     * @return \Buckaroo\Magento2\Api\Data\LogInterface
     */
    public function setLevel($level);

    /**
     * Get message
     * @return string|null
     */
    public function getMessage();

    /**
     * Set message
     * @param string $message
     * @return \Buckaroo\Magento2\Api\Data\LogInterface
     */
    public function setMessage($message);

    /**
     * Get timestamp
     * @return string|null
     */
    public function getTimestamp();

    /**
     * Set timestamp
     * @param string $timestamp
     * @return \Buckaroo\Magento2\Api\Data\LogInterface
     */
    public function setTimestamp($timestamp);

    /**
     * Get session_id
     * @return string|null
     */
    public function getSessionId();

    /**
     * Set session_id
     * @param string $sessionId
     * @return \Buckaroo\Magento2\Api\Data\LogInterface
     */
    public function setSessionId($sessionId);

    /**
     * Get customer_id
     * @return string|null
     */
    public function getCustomerId();

    /**
     * Set customer_id
     * @param string $customerId
     * @return \Buckaroo\Magento2\Api\Data\LogInterface
     */
    public function setCustomerId($customerId);

    /**
     * Get quote_id
     * @return string|null
     */
    public function getQuoteId();

    /**
     * Set quote_id
     * @param string $quoteId
     * @return \Buckaroo\Magento2\Api\Data\LogInterface
     */
    public function setQuoteId($quoteId);

    /**
     * Get order_id
     * @return string|null
     */
    public function getOrderId();

    /**
     * Set order_id
     * @param string $orderId
     * @return \Buckaroo\Magento2\Api\Data\LogInterface
     */
    public function setOrderId($orderId);
}
