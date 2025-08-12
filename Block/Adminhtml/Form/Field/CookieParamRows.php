<?php

namespace Buckaroo\Magento2\Block\Adminhtml\Form\Field;

use Magento\Config\Block\System\Config\Form\Field\FieldArray\AbstractFieldArray;

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
}
