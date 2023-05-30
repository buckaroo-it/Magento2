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

namespace Buckaroo\Magento2\Block\Adminhtml\Giftcard\Edit;

use Magento\Backend\Block\Widget\Form\Generic;
use Magento\Framework\Exception\LocalizedException;

class Form extends Generic
{
    /**
     * Edit Giftcards Form
     *
     * @return $this
     * @throws LocalizedException
     */
    protected function _prepareForm()
    {
        /**
         * @var \Buckaroo\Magento2\Model\Giftcard $model
         */
        $model = $this->_coreRegistry->registry('buckaroo_giftcard');

        /**
         * @var \Magento\Framework\Data\Form $form
         */
        $form = $this->_formFactory->create(
            [
                'data' => [
                    'id' => 'edit_form',
                    'enctype' => 'multipart/form-data',
                    'action' => $this->getData('action'),
                    'method' => 'post'
                ]
            ]
        );

        $form->setHtmlIdPrefix('giftcard_');
        $form->setFieldNameSuffix('giftcard');

        $fieldset = $form->addFieldset(
            'base_fieldset',
            ['legend' => __('Giftcard')]
        );

        if ($model->getId()) {
            $fieldset->addField(
                'entity_id',
                'hidden',
                ['name' => 'entity_id']
            );
        }

        $fieldset->addField(
            'servicecode',
            'text',
            [
                'name' => 'servicecode',
                'label' => __('Service Code'),
                'required' => true,
                'value' => $model->getServicecode()
            ]
        );

        $fieldset->addField(
            'label',
            'text',
            [
                'name' => 'label',
                'label' => __('Label'),
                'required' => true,
                'value' => $model->getLabel()
            ]
        );

        $fieldset->addField(
            'logo',
            'image',
            [
                'title' => __('Giftcard logo'),
                'label' => __('Giftcard logo'),
                'name' => 'logo',
                'note' => 'Allow image type: jpg, jpeg, gif, png'
            ]
        );

        $form->setValues($model->getData());
        $form->setUseContainer(true);
        $this->setForm($form);

        return parent::_prepareForm();
    }
}
