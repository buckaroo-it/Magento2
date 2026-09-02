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

use Buckaroo\Magento2\Model\Config\Source\PaypalButtonStyle;
use Magento\Config\Block\System\Config\Form\Field;
use Magento\Framework\Data\Form\Element\AbstractElement;

class PaypalButton extends Field
{
    /**
     * @var AbstractElement|null
     */
    protected $colorElement;
    /**
     * @var AbstractElement|null
     */
    protected $shapeElement;

    /**
     * Set the PayPal button preview template.
     *
     * @return void
     */
    protected function _construct()
    {
        parent::_construct();
        $this->setTemplate('Buckaroo_Magento2::paypal.phtml');
    }

    /**
     * Return element HTML
     *
     * @param AbstractElement $element
     *
     * @return string
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    protected function _getElementHtml(AbstractElement $element)
    {
        $elementId = $element->getId();
        $this->colorElement = $element->getForm()->getElement(str_replace("preview", "color", $elementId));
        $this->shapeElement = $element->getForm()->getElement(str_replace("preview", "rounded", $elementId));
        return $this->_toHtml();
    }

    /**
     * Get the configured PayPal button color.
     *
     * @return string
     */
    public function getButtonColor(): string
    {
        return $this->colorElement->getDataByKey('value') ?? PaypalButtonStyle::COLOR_DEFAULT;
    }

    /**
     * Get the configured PayPal button shape.
     *
     * @return string
     */
    public function getButtonShape(): string
    {
        return $this->shapeElement->getDataByKey('value') ?? "0";
    }

    /**
     * Get the id of the button color form element.
     *
     * @return string
     */
    public function getButtonColorElement():string
    {
        return $this->colorElement->getId();
    }

    /**
     * Get the id of the button shape form element.
     *
     * @return string
     */
    public function getButtonShapeElement():string
    {
        return $this->shapeElement->getId();
    }
}
