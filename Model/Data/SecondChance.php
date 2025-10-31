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

namespace Buckaroo\Magento2\Model\Data;

use Buckaroo\Magento2\Api\Data\SecondChanceInterface;
use Magento\Framework\Api\AbstractExtensibleObject;

class SecondChance extends AbstractExtensibleObject implements SecondChanceInterface
{
    /**
     * Get second chance ID
     *
     * @return string|null
     */
    public function getSecondChanceId()
    {
        return $this->_get(self::ENTITY_ID);
    }

    /**
     * Set second chance ID
     *
     * @param string $secondChanceId
     *
     * @return \Buckaroo\Magento2\Api\Data\SecondChanceInterface
     */
    public function setSecondChanceId($secondChanceId)
    {
        return $this->setData(self::ENTITY_ID, $secondChanceId);
    }

    /**
     * Get order ID
     *
     * @return string|null
     */
    public function getOrderId()
    {
        return $this->_get(self::ORDER_ID);
    }

    /**
     * Set order ID
     *
     * @param string $orderId
     *
     * @return \Buckaroo\Magento2\Api\Data\SecondChanceInterface
     */
    public function setOrderId($orderId)
    {
        return $this->setData(self::ORDER_ID, $orderId);
    }

    /**
     * Get store ID
     *
     * @return int|null
     */
    public function getStoreId()
    {
        return $this->_get(self::STORE_ID);
    }

    /**
     * Set store ID
     *
     * @param int $storeId
     *
     * @return \Buckaroo\Magento2\Api\Data\SecondChanceInterface
     */
    public function setStoreId($storeId)
    {
        return $this->setData(self::STORE_ID, $storeId);
    }

    /**
     * Get customer email
     *
     * @return string|null
     */
    public function getCustomerEmail()
    {
        return $this->_get(self::CUSTOMER_EMAIL);
    }

    /**
     * Set customer email
     *
     * @param string $customerEmail
     *
     * @return \Buckaroo\Magento2\Api\Data\SecondChanceInterface
     */
    public function setCustomerEmail($customerEmail)
    {
        return $this->setData(self::CUSTOMER_EMAIL, $customerEmail);
    }

    /**
     * Get token
     *
     * @return string|null
     */
    public function getToken()
    {
        return $this->_get(self::TOKEN);
    }

    /**
     * Set token
     *
     * @param string $token
     *
     * @return \Buckaroo\Magento2\Api\Data\SecondChanceInterface
     */
    public function setToken($token)
    {
        return $this->setData(self::TOKEN, $token);
    }

    /**
     * Get status
     *
     * @return string|null
     */
    public function getStatus()
    {
        return $this->_get(self::STATUS);
    }

    /**
     * Set status
     *
     * @param string $status
     *
     * @return \Buckaroo\Magento2\Api\Data\SecondChanceInterface
     */
    public function setStatus($status)
    {
        return $this->setData(self::STATUS, $status);
    }

    /**
     * Get step
     *
     * @return int|null
     */
    public function getStep()
    {
        return $this->_get(self::STEP);
    }

    /**
     * Set step
     *
     * @param int $step
     *
     * @return \Buckaroo\Magento2\Api\Data\SecondChanceInterface
     */
    public function setStep($step)
    {
        return $this->setData(self::STEP, $step);
    }

    /**
     * Get created at
     *
     * @return string|null
     */
    public function getCreatedAt()
    {
        return $this->_get(self::CREATED_AT);
    }

    /**
     * Set created at
     *
     * @param string $createdAt
     *
     * @return \Buckaroo\Magento2\Api\Data\SecondChanceInterface
     */
    public function setCreatedAt($createdAt)
    {
        return $this->setData(self::CREATED_AT, $createdAt);
    }

    /**
     * Get first email sent
     *
     * @return string|null
     */
    public function getFirstEmailSent()
    {
        return $this->_get(self::FIRST_EMAIL_SENT);
    }

    /**
     * Set first email sent
     *
     * @param string $firstEmailSent
     *
     * @return \Buckaroo\Magento2\Api\Data\SecondChanceInterface
     */
    public function setFirstEmailSent($firstEmailSent)
    {
        return $this->setData(self::FIRST_EMAIL_SENT, $firstEmailSent);
    }

    /**
     * Get second email sent
     *
     * @return string|null
     */
    public function getSecondEmailSent()
    {
        return $this->_get(self::SECOND_EMAIL_SENT);
    }

    /**
     * Set second email sent
     *
     * @param string $secondEmailSent
     *
     * @return \Buckaroo\Magento2\Api\Data\SecondChanceInterface
     */
    public function setSecondEmailSent($secondEmailSent)
    {
        return $this->setData(self::SECOND_EMAIL_SENT, $secondEmailSent);
    }

    /**
     * Get last order ID
     *
     * @return string|null
     */
    public function getLastOrderId()
    {
        return $this->_get(self::LAST_ORDER_ID);
    }

    /**
     * Set last order ID
     *
     * @param string $lastOrderId
     *
     * @return \Buckaroo\Magento2\Api\Data\SecondChanceInterface
     */
    public function setLastOrderId($lastOrderId)
    {
        return $this->setData(self::LAST_ORDER_ID, $lastOrderId);
    }

    /**
     * Retrieve existing extension attributes object or create a new one.
     *
     * @return \Buckaroo\Magento2\Api\Data\SecondChanceExtensionInterface|null
     */
    public function getExtensionAttributes()
    {
        return $this->_getExtensionAttributes();
    }

    /**
     * Set an extension attributes object.
     *
     * @param \Buckaroo\Magento2\Api\Data\SecondChanceExtensionInterface $extensionAttributes
     *
     * @return $this
     */
    public function setExtensionAttributes(
        \Buckaroo\Magento2\Api\Data\SecondChanceExtensionInterface $extensionAttributes
    ) {
        return $this->_setExtensionAttributes($extensionAttributes);
    }
}
