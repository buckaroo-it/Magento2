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

namespace Buckaroo\Magento2\Model\ResourceModel;

use Magento\Framework\DataObject;
use Magento\Framework\Encryption\Encryptor;
use Magento\Framework\Model\AbstractModel;
use Magento\Framework\Model\ResourceModel\Db\Context;
use Magento\Framework\Model\ResourceModel\Db\VersionControl\AbstractDb;
use Magento\Framework\Model\ResourceModel\Db\VersionControl\RelationComposite;
use Magento\Framework\Model\ResourceModel\Db\VersionControl\Snapshot;
use Magento\Framework\Stdlib\DateTime\DateTime;

class Certificate extends AbstractDb
{
    /**
     * @var string
     */
    protected $_eventPrefix = 'buckaroo_magento2_certificate_resource';

    /**
     * @var string
     */
    protected $_eventObject = 'resource';

    /**
     * @var DateTime
     */
    protected $dateTime;

    /**
     * @var Encryptor
     */
    protected $encryptor;

    /**
     * @param Context $context
     * @param Snapshot $entitySnapshot
     * @param RelationComposite $entityRelationComposite
     * @param Encryptor $encryptor
     * @param DateTime $dateTime
     * @param string|null $connectionName
     */
    public function __construct(
        Context $context,
        Snapshot $entitySnapshot,
        RelationComposite $entityRelationComposite,
        Encryptor $encryptor,
        DateTime $dateTime,
        string $connectionName = null
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

    /**
     * Model Initialization
     *
     * @return void
     */
    protected function _construct()
    {
        $this->_init('buckaroo_magento2_certificate', 'entity_id');
    }

    /**
     * Perform actions before object save
     *
     * @param AbstractModel|DataObject $object
     * @return $this
     */
    protected function _beforeSave(AbstractModel $object): Certificate
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
     * Decrypt the key after loading.
     *
     * @param AbstractModel $object
     *
     * @return $this
     * @throws \Exception
     */
    protected function _afterLoad(AbstractModel $object): Certificate
    {
        $object->setData(
            'certificate',
            $this->encryptor->decrypt(
                $object->getData('certificate')
            )
        );

        return parent::_afterLoad($object);
    }
}
