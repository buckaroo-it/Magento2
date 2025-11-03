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

namespace Buckaroo\Magento2\Block\Adminhtml\Giftcard;

use Buckaroo\Magento2\Model\Data\BuckarooGiftcardDataInterface;
use Magento\Backend\Block\Widget\Context;
use Magento\Backend\Block\Widget\Form\Container;
use Magento\Framework\Phrase;

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
     * @return Phrase
     */
    public function getHeaderText()
    {
        $giftcard = $this->buckarooGiftcardData->getGiftcardModel() ?? null;

        if ($giftcard && $giftcard->getId()) {
            $giftcardTitle = $this->escapeHtml($giftcard->getLabel());
            return __("Edit Giftcard '%s'", $giftcardTitle);
        } else {
            return __('Add Giftcard');
        }
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
