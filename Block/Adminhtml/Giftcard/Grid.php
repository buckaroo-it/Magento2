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

namespace Buckaroo\Magento2\Block\Adminhtml\Giftcard;

use Magento\Backend\Block\Widget\Grid\Container;

class Grid extends Container
{
    /**
     * Initialize the giftcard grid container block properties.
     *
     * @return void
     */
    protected function _construct()
    {
        $this->_blockGroup = 'Buckaroo_Magento2';
        $this->_controller = 'adminhtml_giftcard';
        $this->_headerText = __('Buckaroo Giftcards');
        $this->_addButtonLabel = __('Add New Giftcard');

        parent::_construct();
    }
}
