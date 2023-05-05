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

namespace Buckaroo\Magento2\Model\Data;

use Buckaroo\Magento2\Api\Data\LogInterface;
use Magento\Framework\Api\AbstractExtensibleObject;

class Log extends AbstractExtensibleObject implements LogInterface
{
    /**
     * @inheritdoc
     */
    public function getLogId(): ?string
    {
        return $this->_get(self::LOG_ID);
    }

    /**
     * @inheritdoc
     */
    public function setLogId(string $logId): LogInterface
    {
        return $this->setData(self::LOG_ID, $logId);
    }

    /**
     * @inheritdoc
     */
    public function getExtensionAttributes()
    {
        return $this->_getExtensionAttributes();
    }

    /**
     * @inheritdoc
     */
    public function setExtensionAttributes(
        \Buckaroo\Magento2\Api\Data\LogExtensionInterface $extensionAttributes
    ) {
        return $this->_setExtensionAttributes($extensionAttributes);
    }

    /**
     * @inheritdoc
     */
    public function getChannel(): ?string
    {
        return $this->_get(self::CHANNEL);
    }

    /**
     * @inheritdoc
     */
    public function setChannel(string $channel): LogInterface
    {
        return $this->setData(self::CHANNEL, $channel);
    }

    /**
     * @inheritdoc
     */
    public function getLevel(): ?string
    {
        return $this->_get(self::LEVEL);
    }

    /**
     * @inheritdoc
     */
    public function setLevel(string $level): LogInterface
    {
        return $this->setData(self::LEVEL, $level);
    }

    /**
     * @inheritdoc
     */
    public function getMessage(): ?string
    {
        return $this->_get(self::MESSAGE);
    }

    /**
     * @inheritdoc
     */
    public function setMessage(string $message): LogInterface
    {
        return $this->setData(self::MESSAGE, $message);
    }

    /**
     * @inheritdoc
     */
    public function getTime(): ?string
    {
        return $this->_get(self::TIME);
    }

    /**
     * @inheritdoc
     */
    public function setTime(string $time): LogInterface
    {
        return $this->setData(self::TIME, $time);
    }

    /**
     * @inheritdoc
     */
    public function getSessionId(): ?string
    {
        return $this->_get(self::SESSION_ID);
    }

    /**
     * @inheritdoc
     */
    public function setSessionId(string $sessionId): LogInterface
    {
        return $this->setData(self::SESSION_ID, $sessionId);
    }

    /**
     * @inheritdoc
     */
    public function getCustomerId(): ?string
    {
        return $this->_get(self::CUSTOMER_ID);
    }

    /**
     * @inheritdoc
     */
    public function setCustomerId(string $customerId): LogInterface
    {
        return $this->setData(self::CUSTOMER_ID, $customerId);
    }

    /**
     * @inheritdoc
     */
    public function getQuoteId(): ?string
    {
        return $this->_get(self::QUOTE_ID);
    }

    /**
     * @inheritdoc
     */
    public function setQuoteId(string $quoteId): LogInterface
    {
        return $this->setData(self::QUOTE_ID, $quoteId);
    }

    /**
     * @inheritdoc
     */
    public function getOrderId(): ?string
    {
        return $this->_get(self::ORDER_ID);
    }

    /**
     * @inheritdoc
     */
    public function setOrderId(string $orderId): LogInterface
    {
        return $this->setData(self::ORDER_ID, $orderId);
    }
}
