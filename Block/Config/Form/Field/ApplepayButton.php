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

use Buckaroo\Magento2\Model\Config\Source\PaypalButtonStyle;
use Buckaroo\Magento2\Model\ConfigProvider\Method\Paypal;
use Magento\Config\Block\System\Config\Form\Field;
use Magento\Framework\Data\Form\Element\AbstractElement;

class ApplepayButton extends Field
{
    /**
     * @var AbstractElement|null
     */
    protected ?AbstractElement $styleElement;

    protected function _construct()
    {
        parent::_construct();
        $this->setTemplate('Buckaroo_Magento2::applepay.phtml');
    }
    /**
     * Return element html
     *
     * @param AbstractElement $element
     * @return string
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    protected function _getElementHtml(AbstractElement $element)
    {
        $elementId = str_replace("_preview","",$element->getId());
        $this->styleElement = $element->getForm()->getElement($elementId);
        return $this->_toHtml();
    }

    public function getButtonStyle(): string
    {
        return $this->styleElement->getDataByKey('value');
    }
    public function getButtonStyleElement():string
    {
        return $this->styleElement->getId();
    }
}
