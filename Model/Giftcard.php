<?php
/**
 * Buckaroo Magento 2 payment module (https://www.buckaroo.eu/)
 *
 * Copyright (c) Buckaroo B.V.
 * See LICENSE for license details.
 *
 * Support: support@buckaroo.nl
 *
 * @copyright Copyright (c) Buckaroo B.V.
 * @license   MIT
 */
declare(strict_types=1);

namespace Buckaroo\Magento2\Model;

use Magento\Framework\Model\AbstractModel;
use Buckaroo\Magento2\Api\Data\GiftcardInterface;

class Giftcard extends AbstractModel implements GiftcardInterface
{
    /**
     * @var string
     */
    protected $_eventPrefix = 'buckaroo_magento2_giftcard';

    /**
     * @var string
     */
    protected $_eventObject = 'giftcard';

    /**
     * Initialize resource model
     */
    protected function _construct()
    {
        $this->_init('Buckaroo\Magento2\Model\ResourceModel\Giftcard');
    }

    /**
     * @inheritdoc
     */
    public function getServicecode()
    {
        return $this->getData('servicecode');
    }

    /**
     * @inheritdoc
     */
    public function getLabel()
    {
        return $this->getData('label');
    }

    /**
     * @inheritdoc
     */
    public function setServicecode($servicecode)
    {
        return $this->setData('servicecode', $servicecode);
    }

    /**
     * @inheritdoc
     */
    public function setLabel($label)
    {
        return $this->setData('label', $label);
    }

    /**
     * Set the giftcard acquirer.
     *
     * @param string|null $acquirer
     *
     * @return $this
     */
    public function setAcquirer(?string $acquirer = null)
    {
        return $this->setData('acquirer', $acquirer);
    }

    /**
     * Get the giftcard acquirer.
     *
     * @return string|null
     */
    public function getAcquirer()
    {
        return $this->getData('acquirer');
    }
}
