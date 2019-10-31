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
use TIG\Buckaroo\Api\Data\GiftcardInterface;

class Giftcard extends AbstractModel implements GiftcardInterface
{
    /**
     * @var string
     */
    protected $_eventPrefix = 'tig_buckaroo_giftcard';

    /**
     * @var string
     */
    protected $_eventObject = 'giftcard';

    /**
     * Initialize resource model
     *
     * @return void
     */
    protected function _construct()
    {
        $this->_init('TIG\Buckaroo\Model\ResourceModel\Giftcard');
    }

    /**
     * @return string
     */
    public function getServicecode()
    {
        return $this->getData('servicecode');
    }

    /**
     * @return string
     */
    public function getLabel()
    {
        return $this->getData('label');
    }

    /**
     * @param string $servicecode
     *
     * @return $this
     */
    public function setServicecode($servicecode)
    {
        return $this->setData('servicecode', $servicecode);
    }

    /**
     * @param string $label
     *
     * @return $this
     */
    public function setLabel($label)
    {
        return $this->setData('label', $label);
    }
}
