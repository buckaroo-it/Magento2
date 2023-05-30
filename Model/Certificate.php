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

namespace Buckaroo\Magento2\Model;

use Magento\Framework\Model\AbstractModel;
use Buckaroo\Magento2\Api\Data\CertificateInterface;

class Certificate extends AbstractModel implements CertificateInterface
{
    /**
     * @var string
     */
    protected $_eventPrefix = 'buckaroo_magento2_certificate';

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
        $this->_init('Buckaroo\Magento2\Model\ResourceModel\Certificate');
    }

    /**
     * @inheritdoc
     */
    public function getCertificate(): string
    {
        return $this->getData('certificate');
    }

    /**
     * @inheritdoc
     */
    public function getName(): string
    {
        return $this->getData('name');
    }

    /**
     * @inheritdoc
     */
    public function getCreatedAt(): string
    {
        return $this->getData('created_at');
    }

    /**
     * @inheritdoc
     */
    public function setCertificate(string $certificate): CertificateInterface
    {
        return $this->setData('certificate', $certificate);
    }

    /**
     * @inheritdoc
     */
    public function setName(string $name): CertificateInterface
    {
        return $this->setData('name', $name);
    }

    /**
     * @inheritdoc
     */
    public function setCreatedAt(string $createdAt): CertificateInterface
    {
        return $this->setData('created_at', $createdAt);
    }

    /**
     * Skip encryption on save
     *
     * @param boolean $skipEncryptionOnSave
     * @return $this
     */
    public function setSkipEncryptionOnSave(bool $skipEncryptionOnSave): Certificate
    {
        $this->skipEncryptionOnSave = $skipEncryptionOnSave;

        return $this;
    }

    /**
     * Is skip encryption on save active
     *
     * @return boolean
     */
    public function isSkipEncryptionOnSave(): bool
    {
        return $this->skipEncryptionOnSave;
    }
}
