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

namespace Buckaroo\Magento2\Model\ResourceModel;

use Magento\Framework\Model\AbstractModel;

class Certificate extends \Magento\Framework\Model\ResourceModel\Db\VersionControl\AbstractDb
{
    /**
     * Event prefix
     *
     * @var string
     */
    protected $_eventPrefix = 'buckaroo_magento2_certificate_resource';

    /**
     * Event object
     *
     * @var string
     */
    protected $_eventObject = 'resource';

    /**
     * @var \Magento\Framework\Stdlib\DateTime\DateTime
     */
    protected $dateTime;

    /**
     * @var \Magento\Framework\Encryption\Encryptor
     */
    protected $encryptor;

    /**
     * @param \Magento\Framework\Model\ResourceModel\Db\Context                          $context
     * @param \Magento\Framework\Model\ResourceModel\Db\VersionControl\Snapshot          $entitySnapshot
     * @param \Magento\Framework\Model\ResourceModel\Db\VersionControl\RelationComposite $entityRelationComposite
     * @param \Magento\Framework\Encryption\Encryptor                                    $encryptor
     * @param \Magento\Framework\Stdlib\DateTime\DateTime                                $dateTime
     * @param string                                                                     $connectionName
     */
    public function __construct(
        \Magento\Framework\Model\ResourceModel\Db\Context $context,
        \Magento\Framework\Model\ResourceModel\Db\VersionControl\Snapshot $entitySnapshot,
        \Magento\Framework\Model\ResourceModel\Db\VersionControl\RelationComposite $entityRelationComposite,
        \Magento\Framework\Encryption\Encryptor $encryptor,
        \Magento\Framework\Stdlib\DateTime\DateTime $dateTime,
        $connectionName = null
    ) {
        parent::__construct(
            $context,
            $entitySnapshot,
            $entityRelationComposite,
            $connectionName
        );

        $this->dateTime = $dateTime;
        $this->encryptor = $encryptor;
    }

    // @codingStandardsIgnoreStart
    /**
     * Model Initialization
     */
    protected function _construct()
    {
        $this->_init('buckaroo_magento2_certificate', 'entity_id');
    }

    /**
     * Perform actions before object save
     *
     * @param AbstractModel $object
     * @return $this
     */
    protected function _beforeSave(\Magento\Framework\Model\AbstractModel $object)
    {
        /** @var \Buckaroo\Magento2\Model\Certificate $object */
        if ($object->isObjectNew()) {
            $object->setData('created_at', $this->dateTime->gmtDate());
        }

        /**
         * Encrypt the key before saving.
         */
        if (!$object->isSkipEncryptionOnSave()) {
            $object->setData(
                'certificate',
                $this->encryptor->encrypt(
                    $object->getData('certificate')
                )
            );
        }

        return $this;
    }

    /**
     * @param \Magento\Framework\Model\AbstractModel $object
     *
     * @return $this
     */
    protected function _afterLoad(\Magento\Framework\Model\AbstractModel $object)
    {
        /**
         * Decrypt the key after loading.
         */
        $object->setData(
            'certificate',
            $this->encryptor->decrypt(
                $object->getData('certificate')
            )
        );

        return parent::_afterLoad($object);
    }
    // @codingStandardsIgnoreEnd
}
