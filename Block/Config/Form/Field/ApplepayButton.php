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

namespace Buckaroo\Magento2\Block\Config\Form\Field;

use Magento\Config\Block\System\Config\Form\Field;
use Magento\Framework\Data\Form\Element\AbstractElement;

class ApplepayButton extends Field
{
    /**
     * @var string
     */
    protected $_template = 'Buckaroo_Magento2::applepay.phtml';

    /**
     * @var AbstractElement|null
     */
    protected $styleElement = null;

    /**
     * Return element html
     *
     * @param AbstractElement $element
     *
     * @return string
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    protected function _getElementHtml(AbstractElement $element)
    {
        $elementId = str_replace('_preview', '', $element->getId());
        $this->styleElement = $element->getForm()->getElement($elementId);
        return $this->_toHtml();
    }

    /**
     * Get the configured Apple Pay button style value.
     *
     * @return string
     */
    public function getButtonStyle(): string
    {
        return (string) $this->styleElement->getDataByKey('value');
    }

    /**
     * Get the id of the Apple Pay button style form element.
     *
     * @return string
     */
    public function getButtonStyleElement(): string
    {
        return (string) $this->styleElement->getId();
    }
}
