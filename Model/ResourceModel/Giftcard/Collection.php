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

namespace TIG\Buckaroo\Model\ResourceModel\Giftcard;

class Collection extends \Magento\Sales\Model\ResourceModel\Collection\AbstractCollection
{
    /**
     * @var string
     */
    protected $_idFieldName = 'entity_id';

    /**
     * Event prefix
     *
     * @var string
     */
    protected $_eventPrefix = 'tig_buckaroo_giftcard_collection';

    /**
     * Event object
     *
     * @var string
     */
    protected $_eventObject = 'giftcard_collection';

    /**
     * Model initialization
     *
     * @return void
     */
    protected function _construct()
    {
        $this->_init('TIG\Buckaroo\Model\Giftcard', 'TIG\Buckaroo\Model\ResourceModel\Giftcard');
    }
}
