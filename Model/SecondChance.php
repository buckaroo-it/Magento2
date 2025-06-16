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
declare(strict_types=1);

namespace Buckaroo\Magento2\Model;

use Buckaroo\Magento2\Api\Data\SecondChanceInterface;
use Buckaroo\Magento2\Api\Data\SecondChanceInterfaceFactory;
use Magento\Framework\Api\DataObjectHelper;
use Buckaroo\Magento2\Model\ResourceModel\SecondChance as SecondChanceResource;
use Buckaroo\Magento2\Model\ResourceModel\SecondChance\Collection as SecondChanceCollection;

class SecondChance extends \Magento\Framework\Model\AbstractModel implements SecondChanceInterface
{
    protected $dataObjectHelper;

    protected $_eventPrefix = 'buckaroo_magento2_second_chance';

    protected $secondChanceDataFactory;

    /**
     * @param \Magento\Framework\Model\Context $context
     * @param \Magento\Framework\Registry $registry
     * @param SecondChanceInterfaceFactory $secondChanceDataFactory
     * @param DataObjectHelper $dataObjectHelper
     * @param SecondChanceResource $resource
     * @param SecondChanceCollection $resourceCollection
     * @param array $data
     */
    public function __construct(
        \Magento\Framework\Model\Context $context,
        \Magento\Framework\Registry $registry,
        SecondChanceInterfaceFactory $secondChanceDataFactory,
        DataObjectHelper $dataObjectHelper,
        SecondChanceResource $resource,
        SecondChanceCollection $resourceCollection,
        array $data = []
    ) {
        $this->secondChanceDataFactory = $secondChanceDataFactory;
        $this->dataObjectHelper = $dataObjectHelper;
        parent::__construct($context, $registry, $resource, $resourceCollection, $data);
    }

    /**
     * Retrieve secondChance model with secondChance data
     *
     * @return SecondChanceInterface
     */
    public function getDataModel()
    {
        $secondChanceData = $this->getData();

        $secondChanceDataObject = $this->secondChanceDataFactory->create();
        $this->dataObjectHelper->populateWithArray(
            $secondChanceDataObject,
            $secondChanceData,
            SecondChanceInterface::class
        );

        return $secondChanceDataObject;
    }

    /**
     * Get secondChance_id
     *
     * @return string|null
     */
    public function getSecondChanceId()
    {
        return $this->_get(self::ENTITY_ID);
    }

    /**
     * Set secondChance_id
     *
     * @param string $secondChanceId
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
     * @return \Buckaroo\Magento2\Api\Data\SecondChanceInterface
     */
    public function setStoreId($storeId)
    {
        return $this->setData(self::STORE_ID, $storeId);
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
     * @return \Buckaroo\Magento2\Api\Data\SecondChanceInterface
     */
    public function setStatus($status)
    {
        return $this->setData(self::STATUS, $status);
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
     * @return \Buckaroo\Magento2\Api\Data\SecondChanceInterface
     */
    public function setCreatedAt($createdAt)
    {
        return $this->setData(self::CREATED_AT, $createdAt);
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
     * @return $this
     */
    public function setExtensionAttributes(
        \Buckaroo\Magento2\Api\Data\SecondChanceExtensionInterface $extensionAttributes
    ) {
        return $this->_setExtensionAttributes($extensionAttributes);
    }
} 