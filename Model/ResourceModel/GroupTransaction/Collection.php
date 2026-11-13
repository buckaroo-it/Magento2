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

namespace Buckaroo\Magento2\Model\ResourceModel\GroupTransaction;

class Collection extends \Magento\Sales\Model\ResourceModel\Collection\AbstractCollection
{
    /**
     * @var string
     */
    protected $_idFieldName = 'entity_id';

    /**
     * Prefix used for the events dispatched by this collection
     *
     * @var string
     */
    protected $_eventPrefix = 'buckaroo_magento2_group_transaction_collection';

    /**
     * Name of the parameter passed to dispatched events
     *
     * @var string
     */
    protected $_eventObject = 'group_transaction_collection';

    /**
     * Model initialization
     */
    protected function _construct()
    {
        $this->_init(
            'Buckaroo\Magento2\Model\GroupTransaction',
            'Buckaroo\Magento2\Model\ResourceModel\GroupTransaction'
        );
    }
}
