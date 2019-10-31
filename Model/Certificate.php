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

namespace TIG\Buckaroo\Model;

use Magento\Framework\Model\AbstractModel;
use TIG\Buckaroo\Api\Data\CertificateInterface;

class Certificate extends AbstractModel implements CertificateInterface
{
    // @codingStandardsIgnoreStart
    /**
     * @var string
     */
    protected $_eventPrefix = 'tig_buckaroo_certificate';

    /**
     * @var string
     */
    protected $_eventObject = 'certificate';

    /**
     * @var bool
     */
    protected $skipEncryptionOnSave = false;

    /**
     * Initialize resource model
     *
     * @return void
     */
    protected function _construct()
    {
        $this->_init('TIG\Buckaroo\Model\ResourceModel\Certificate');
    }
    // @codingStandardsIgnoreEnd

    /**
     * {@inheritdoc}
     */
    public function getCertificate()
    {
        return $this->getData('certificate');
    }

    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return $this->getData('name');
    }

    /**
     * {@inheritdoc}
     */
    public function getCreatedAt()
    {
        return $this->getData('created_at');
    }

    /**
     * {@inheritdoc}
     */
    public function setCertificate($certificate)
    {
        return $this->setData('certificate', $certificate);
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
     * @param boolean $skipEncryptionOnSave
     *
     * @return $this
     */
    public function setSkipEncryptionOnSave($skipEncryptionOnSave)
    {
        $this->skipEncryptionOnSave = $skipEncryptionOnSave;

        return $this;
    }

    /**
     * @return boolean
     */
    public function isSkipEncryptionOnSave()
    {
        return $this->skipEncryptionOnSave;
    }
}
