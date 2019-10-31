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

namespace TIG\Buckaroo\Model\ResourceModel;

class Giftcard extends \Magento\Framework\Model\ResourceModel\Db\VersionControl\AbstractDb
{
    /**
     * Event prefix
     *
     * @var string
     */
    protected $_eventPrefix = 'tig_buckaroo_giftcard_resource';

    /**
     * Event object
     *
     * @var string
     */
    protected $_eventObject = 'resource';

    /**
     * Model Initialization
     *
     * @return void
     */
    protected function _construct()
    {
        $this->_init('tig_buckaroo_giftcard', 'entity_id');
    }
}
