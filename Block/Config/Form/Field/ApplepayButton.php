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

namespace Buckaroo\Magento2\Block\Config\Form\Field;

use Buckaroo\Magento2\Model\Config\Source\ApplepayButtonType;
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
     * @var AbstractElement|null
     */
    protected $typeElement = null;

    /**
     * @var ApplepayButtonType
     */
    private $buttonTypes;

    /**
     * @param \Magento\Backend\Block\Template\Context $context
     * @param ApplepayButtonType                      $buttonTypes
     * @param array                                   $data
     */
    public function __construct(
        \Magento\Backend\Block\Template\Context $context,
        ApplepayButtonType $buttonTypes,
        array $data = []
    ) {
        $this->buttonTypes = $buttonTypes;
        parent::__construct($context, $data);
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
    protected function _getElementHtml(AbstractElement $element)
    {
        $elementId = str_replace('_preview', '', $element->getId());
        $this->styleElement = $element->getForm()->getElement($elementId);
        $this->typeElement = $element->getForm()->getElement(
            str_replace('_style_preview', '_type', $element->getId())
        );
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

    /**
     * Get the Apple Pay button type the storefront renders, so the preview matches it.
     *
     * @return string
     */
    public function getButtonType(): string
    {
        return $this->buttonTypes->normalize($this->typeElement->getDataByKey('value'));
    }

    /**
     * Get the id of the Apple Pay button type form element.
     *
     * @return string
     */
    public function getButtonTypeElement(): string
    {
        return (string) $this->typeElement->getId();
    }
}
