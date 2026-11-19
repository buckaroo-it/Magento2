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

use Buckaroo\Magento2\Model\Config\Source\GooglepayButtonType;
use Magento\Config\Block\System\Config\Form\Field;
use Magento\Directory\Helper\Data as DirectoryHelper;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Data\Form\Element\AbstractElement;
use Magento\Store\Model\ScopeInterface;

class GooglepayButton extends Field
{
    /**
     * @var string
     */
    protected $_template = 'Buckaroo_Magento2::config/form/field/googlepay_button.phtml';

    /**
     * @var AbstractElement|null
     */
    protected $styleElement = null;

    /**
     * @var AbstractElement|null
     */
    protected $typeElement = null;

    /**
     * @var GooglepayButtonType
     */
    private $buttonTypes;

    /**
     * @param \Magento\Backend\Block\Template\Context $context
     * @param GooglepayButtonType                     $buttonTypes
     * @param array                                   $data
     */
    public function __construct(
        \Magento\Backend\Block\Template\Context $context,
        GooglepayButtonType $buttonTypes,
        array $data = []
    ) {
        $this->buttonTypes = $buttonTypes;
        parent::__construct($context, $data);
    }

    /**
     * Remove scope label
     *
     * @param  AbstractElement $element
     * @return string
     */
    public function render(AbstractElement $element)
    {
        $element->unsScope()->unsCanUseWebsiteValue()->unsCanUseDefaultValue();
        return parent::render($element);
    }

    /**
     * Return element html
     *
     * @param  AbstractElement $element
     * @return string
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    protected function _getElementHtml(AbstractElement $element)
    {
        $this->styleElement = $element->getForm()->getElement(str_replace('_preview', '', $element->getId()));
        $this->typeElement = $element->getForm()->getElement(
            str_replace('_style_preview', '_type', $element->getId())
        );
        return $this->_toHtml();
    }

    /**
     * Get the configured Google Pay button colour, "black" like the storefront when not set or unknown.
     *
     * @return string
     */
    public function getButtonStyle(): string
    {
        $style = $this->styleElement ? (string)$this->styleElement->getDataByKey('value') : '';
        return isset($this->getButtonStyles()[$style]) ? $style : 'black';
    }

    /**
     * Get the id of the Google Pay button style form element.
     *
     * @return string
     */
    public function getButtonStyleElement(): string
    {
        return $this->styleElement ? (string)$this->styleElement->getId() : '';
    }

    /**
     * Get the Google Pay button type the storefront renders, so the preview matches it.
     *
     * @return string
     */
    public function getButtonType(): string
    {
        return $this->buttonTypes->normalize(
            $this->typeElement ? $this->typeElement->getDataByKey('value') : null
        );
    }

    /**
     * Get the id of the Google Pay button type form element.
     *
     * @return string
     */
    public function getButtonTypeElement(): string
    {
        return $this->typeElement ? (string)$this->typeElement->getId() : '';
    }

    /**
     * Get the language of the configuration scope being edited, as the storefront passes it to Google.
     *
     * The storefront uses the store view's locale (Googlepay::getCultureCode), not the admin user's.
     *
     * @return string
     */
    public function getButtonLocale(): string
    {
        $store = $this->getRequest()->getParam('store');
        $website = $this->getRequest()->getParam('website');

        if ($store) {
            $locale = $this->_scopeConfig->getValue(
                DirectoryHelper::XML_PATH_DEFAULT_LOCALE,
                ScopeInterface::SCOPE_STORE,
                $store
            );
        } elseif ($website) {
            $locale = $this->_scopeConfig->getValue(
                DirectoryHelper::XML_PATH_DEFAULT_LOCALE,
                ScopeInterface::SCOPE_WEBSITE,
                $website
            );
        } else {
            $locale = $this->_scopeConfig->getValue(
                DirectoryHelper::XML_PATH_DEFAULT_LOCALE,
                ScopeConfigInterface::SCOPE_TYPE_DEFAULT
            );
        }

        return explode('_', (string)$locale)[0];
    }

    /**
     * Get the button styles array
     *
     * @return array
     */
    public function getButtonStyles()
    {
        return [
            'black' => __('Black'),
            'white' => __('White'),
        ];
    }
}
