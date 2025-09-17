<?php

namespace Buckaroo\Magento2\Block\Adminhtml\Form\Field;

use Magento\Config\Block\System\Config\Form\Field\FieldArray\AbstractFieldArray;
use Magento\Framework\Data\Form\Element\AbstractElement;

class CookieParamRows extends AbstractFieldArray
{
    protected function _prepareToRender()
    {
        $this->addColumn('cookie', [
            'label' => __('Cookie Name'),
            'class' => 'required-entry',
        ]);

        $this->addColumn('url_param', [
            'label' => __('Url Param Name'),
            'class' => 'required-entry',
        ]);

        $this->addColumn('replace_regex', [
            'label' => __('Replace Regex')
        ]);

        $this->_addAfter = false;
        $this->_addButtonLabel = __('Add');
    }

    protected function _getElementHtml(AbstractElement $element)
    {
        $value = $element->getValue();

        if (empty($value) || $value === null || $value === '') {
            $defaultValue = [
                [
                    'cookie' => '_ga',
                    'url_param' => 'clientId',
                    'replace_regex' => '/^[^\.]*\.[^\.]*\./'
                ],
                [
                    'cookie' => '_gcl_aw',
                    'url_param' => 'gclid',
                    'replace_regex' => '/^[^\.]*\.[^\.]*\./'
                ],
                [
                    'cookie' => '_uetmsclkid',
                    'url_param' => 'msclkid',
                    'replace_regex' => '/^_uet/'
                ]
            ];

            $element->setValue($defaultValue);
        }

        return parent::_getElementHtml($element);
    }
}
