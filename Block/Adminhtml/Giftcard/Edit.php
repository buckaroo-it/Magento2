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

use Buckaroo\Magento2\Model\Data\BuckarooGiftcardDataInterface;
use Magento\Backend\Block\Widget\Context;
use Magento\Backend\Block\Widget\Form\Container;

class Edit extends Container
{
    /**
     * @var BuckarooGiftcardDataInterface
     */
    protected $buckarooGiftcardData;

    /**
     * @param Context                       $context
     * @param BuckarooGiftcardDataInterface $buckarooGiftcardData
     * @param array                         $data
     */
    public function __construct(
        Context $context,
        BuckarooGiftcardDataInterface $buckarooGiftcardData,
        array $data = []
    ) {
        $this->buckarooGiftcardData = $buckarooGiftcardData;
        parent::__construct($context, $data);
    }

    /**
     * Get header text
     *
     * @return string
     */
    public function getHeaderText(): string
    {
        $giftcard = $this->buckarooGiftcardData->getGiftcardModel();

        if ($giftcard->getId()) {
            $giftcardTitle = $this->escapeHtml($giftcard->getLabel());
            return (string) __("Edit Giftcard '%1'", $giftcardTitle);
        }

        return (string) __('Add Giftcard');
    }

    /**
     * Initialize form.
     */
    protected function _construct()
    {
        $this->_objectId = 'entity_id';
        $this->_blockGroup = 'Buckaroo_Magento2';
        $this->_controller = 'adminhtml_giftcard';

        parent::_construct();

        $this->buttonList->update('save', 'label', __('Save'));
        $this->buttonList->update('delete', 'label', __('Delete'));

        $this->buttonList->add(
            'saveandcontinue',
            [
                'label'          => __('Save and Continue'),
                'class'          => 'save',
                'data_attribute' => [
                    'mage-init' => [
                        'button' => [
                            'event'  => 'saveAndContinueEdit',
                            'target' => '#edit_form'
                        ]
                    ]
                ]
            ]
        );
    }
}
