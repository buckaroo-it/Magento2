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

namespace TIG\Buckaroo\Block\Adminhtml\Giftcard;

use Magento\Backend\Block\Widget\Context;
use Magento\Framework\Registry;

class Edit extends \Magento\Backend\Block\Widget\Form\Container
{
    /**
     * @var Registry
     */
    protected $_coreRegistry = null;

    /**
     * @param Context  $context
     * @param Registry $registry
     * @param array    $data
     */
    public function __construct(
        Context $context,
        Registry $registry,
        array $data = []
    ) {
        $this->_coreRegistry = $registry;
        parent::__construct($context, $data);
    }

    protected function _construct()
    {
        $this->_objectId = 'entity_id';
        $this->_blockGroup = 'TIG_Buckaroo';
        $this->_controller = 'adminhtml_giftcard';

        parent::_construct();

        $this->buttonList->update('save', 'label', __('Save'));
        $this->buttonList->update('delete', 'label', __('Delete'));

        $this->buttonList->add(
            'saveandcontinue',
            [
                'label' => __('Save and Continue'),
                'class' => 'save',
                'data_attribute' => [
                    'mage-init' => [
                        'button' => [
                            'event' => 'saveAndContinueEdit',
                            'target' => '#edit_form'
                        ]
                    ]
                ]
            ]
        );
    }

    /**
     * @return \Magento\Framework\Phrase
     */
    public function getHeaderText()
    {
        $registry = $this->_coreRegistry->registry('buckaroo_giftcard');

        if ($registry->getId()) {
            $giftcardTitle = $this->escapeHtml($registry->getLabel());
            return __("Edit Giftcard '%s'", $giftcardTitle);
        } else {
            return __('Add Giftcard');
        }
    }
}
