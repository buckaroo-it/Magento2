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
declare(strict_types=1);

namespace Buckaroo\Magento2\Api\Data;

use Magento\Framework\Api\ExtensibleDataInterface;

interface LogInterface extends ExtensibleDataInterface
{
    public const TIME = 'time';
    public const SESSION_ID = 'session_id';
    public const CHANNEL = 'channel';
    public const QUOTE_ID = 'quote_id';
    public const LEVEL = 'level';
    public const ORDER_ID = 'order_id';
    public const LOG_ID = 'log_id';
    public const MESSAGE = 'message';
    public const CUSTOMER_ID = 'customer_id';

    /**
     * Get log_id
     *
     * @return string|null
     */
    public function getLogId(): ?string;

    /**
     * Set log_id
     *
     * @param  string       $logId
     * @return LogInterface
     */
    public function setLogId(string $logId): LogInterface;

    /**
     * Retrieve existing extension attributes object or create a new one.
     *
     * @return LogExtensionInterface|null
     */
    public function getExtensionAttributes();

    /**
     * Set an extension attributes object.
     *
     * @param LogExtensionInterface $extensionAttributes
     * @return $this
     */
    public function setExtensionAttributes(
        LogExtensionInterface $extensionAttributes
    );

    /**
     * Get channel
     *
     * @return string|null
     */
    public function getChannel(): ?string;

    /**
     * Set channel
     *
     * @param  string       $channel
     * @return LogInterface
     */
    public function setChannel(string $channel): LogInterface;

    /**
     * Get level
     *
     * @return string|null
     */
    public function getLevel(): ?string;

    /**
     * Set level
     *
     * @param  string       $level
     * @return LogInterface
     */
    public function setLevel(string $level): LogInterface;

    /**
     * Get message
     *
     * @return string|null
     */
    public function getMessage(): ?string;

    /**
     * Set message
     *
     * @param  string       $message
     * @return LogInterface
     */
    public function setMessage(string $message): LogInterface;

    /**
     * Get time
     *
     * @return string|null
     */
    public function getTime(): ?string;

    /**
     * Set time
     *
     * @param  string       $time
     * @return LogInterface
     */
    public function setTime(string $time): LogInterface;

    /**
     * Get session_id
     *
     * @return string|null
     */
    public function getSessionId(): ?string;

    /**
     * Set session_id
     *
     * @param  string       $sessionId
     * @return LogInterface
     */
    public function setSessionId(string $sessionId): LogInterface;

    /**
     * Get customer_id
     *
     * @return string|null
     */
    public function getCustomerId(): ?string;

    /**
     * Set customer_id
     *
     * @param  string       $customerId
     * @return LogInterface
     */
    public function setCustomerId(string $customerId): LogInterface;

    /**
     * Get quote_id
     *
     * @return string|null
     */
    public function getQuoteId(): ?string;

    /**
     * Set quote_id
     *
     * @param  string       $quoteId
     * @return LogInterface
     */
    public function setQuoteId(string $quoteId): LogInterface;

    /**
     * Get order_id
     *
     * @return string|null
     */
    public function getOrderId(): ?string;

    /**
     * Set order_id
     *
     * @param  string       $orderId
     * @return LogInterface
     */
    public function setOrderId(string $orderId): LogInterface;
}
