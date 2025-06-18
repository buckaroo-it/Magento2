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

namespace Buckaroo\Magento2\Api\Data;

use Magento\Framework\Api\ExtensibleDataInterface;

interface SecondChanceInterface extends ExtensibleDataInterface
{
    const ENTITY_ID = 'entity_id';
    const ORDER_ID = 'order_id';
    const STORE_ID = 'store_id';
    const CUSTOMER_EMAIL = 'customer_email';
    const TOKEN = 'token';
    const STATUS = 'status';
    const STEP = 'step';
    const CREATED_AT = 'created_at';
    const FIRST_EMAIL_SENT = 'first_email_sent';
    const SECOND_EMAIL_SENT = 'second_email_sent';
    const LAST_ORDER_ID = 'last_order_id';

    /**
     * Get second chance ID
     *
     * @return string|null
     */
    public function getSecondChanceId();

    /**
     * Set second chance ID
     *
     * @param string $secondChanceId
     * @return \Buckaroo\Magento2\Api\Data\SecondChanceInterface
     */
    public function setSecondChanceId($secondChanceId);

    /**
     * Get order ID
     *
     * @return string|null
     */
    public function getOrderId();

    /**
     * Set order ID
     *
     * @param string $orderId
     * @return \Buckaroo\Magento2\Api\Data\SecondChanceInterface
     */
    public function setOrderId($orderId);

    /**
     * Get store ID
     *
     * @return int|null
     */
    public function getStoreId();

    /**
     * Set store ID
     *
     * @param int $storeId
     * @return \Buckaroo\Magento2\Api\Data\SecondChanceInterface
     */
    public function setStoreId($storeId);

    /**
     * Get customer email
     *
     * @return string|null
     */
    public function getCustomerEmail();

    /**
     * Set customer email
     *
     * @param string $customerEmail
     * @return \Buckaroo\Magento2\Api\Data\SecondChanceInterface
     */
    public function setCustomerEmail($customerEmail);

    /**
     * Get token
     *
     * @return string|null
     */
    public function getToken();

    /**
     * Set token
     *
     * @param string $token
     * @return \Buckaroo\Magento2\Api\Data\SecondChanceInterface
     */
    public function setToken($token);

    /**
     * Get status
     *
     * @return string|null
     */
    public function getStatus();

    /**
     * Set status
     *
     * @param string $status
     * @return \Buckaroo\Magento2\Api\Data\SecondChanceInterface
     */
    public function setStatus($status);

    /**
     * Get step
     *
     * @return int|null
     */
    public function getStep();

    /**
     * Set step
     *
     * @param int $step
     * @return \Buckaroo\Magento2\Api\Data\SecondChanceInterface
     */
    public function setStep($step);

    /**
     * Get created at
     *
     * @return string|null
     */
    public function getCreatedAt();

    /**
     * Set created at
     *
     * @param string $createdAt
     * @return \Buckaroo\Magento2\Api\Data\SecondChanceInterface
     */
    public function setCreatedAt($createdAt);

    /**
     * Get first email sent
     *
     * @return string|null
     */
    public function getFirstEmailSent();

    /**
     * Set first email sent
     *
     * @param string $firstEmailSent
     * @return \Buckaroo\Magento2\Api\Data\SecondChanceInterface
     */
    public function setFirstEmailSent($firstEmailSent);

    /**
     * Get second email sent
     *
     * @return string|null
     */
    public function getSecondEmailSent();

    /**
     * Set second email sent
     *
     * @param string $secondEmailSent
     * @return \Buckaroo\Magento2\Api\Data\SecondChanceInterface
     */
    public function setSecondEmailSent($secondEmailSent);

    /**
     * Get last order ID
     *
     * @return string|null
     */
    public function getLastOrderId();

    /**
     * Set last order ID
     *
     * @param string $lastOrderId
     * @return \Buckaroo\Magento2\Api\Data\SecondChanceInterface
     */
    public function setLastOrderId($lastOrderId);

    /**
     * Retrieve existing extension attributes object or create a new one.
     *
     * @return \Buckaroo\Magento2\Api\Data\SecondChanceExtensionInterface|null
     */
    public function getExtensionAttributes();

    /**
     * Set an extension attributes object.
     *
     * @param \Buckaroo\Magento2\Api\Data\SecondChanceExtensionInterface $extensionAttributes
     * @return $this
     */
    public function setExtensionAttributes(
        \Buckaroo\Magento2\Api\Data\SecondChanceExtensionInterface $extensionAttributes
    );
}
