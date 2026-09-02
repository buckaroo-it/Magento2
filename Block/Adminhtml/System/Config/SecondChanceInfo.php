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

namespace Buckaroo\Magento2\Block\Adminhtml\System\Config;

use Magento\Config\Block\System\Config\Form\Field;
use Magento\Framework\Data\Form\Element\AbstractElement;

class SecondChanceInfo extends Field
{
    /**
     * @inheritdoc
     */
    protected function _construct()
    {
        parent::_construct();
        $this->setTemplate('Buckaroo_Magento2::second_chance_info.phtml');
    }

    /**
     * @inheritdoc
     */
    public function render(AbstractElement $element)
    {
        $element->unsScope()->unsCanUseWebsiteValue()->unsCanUseDefaultValue();

        $html = parent::render($element);

        $html = str_replace(
            '<td class="label">',
            '<td class="label" style="display: none;">',
            $html
        );

        $html = preg_replace(
            '/<td class="value"[^>]*>/',
            '<td class="value" colspan="3" style="width: 100%; padding-left: 0; padding-right: 0;">',
            $html,
            1
        );

        return $html;
    }

    /**
     * Return element html
     *
     * @param AbstractElement $element
     *
     * @return string
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function _getElementHtml(AbstractElement $element): string
    {
        return $this->_toHtml();
    }
}
