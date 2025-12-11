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

use Magento\Config\Block\System\Config\Form\Field;
use Magento\Framework\Data\Form\Element\AbstractElement;
use Magento\Framework\View\Asset\Repository;

class IdealButton extends Field
{
    protected $_template = 'Buckaroo_Magento2::ideal_button.phtml';

    /**
     * @var AbstractElement|null
     */
    protected $colorElement = null;

    /**
     * @var Repository
     */
    protected $assetRepo;

    /**
     * @param \Magento\Backend\Block\Template\Context $context
     * @param Repository $assetRepo
     * @param array $data
     */
    public function __construct(
        \Magento\Backend\Block\Template\Context $context,
        Repository $assetRepo,
        array $data = []
    ) {
        $this->assetRepo = $assetRepo;
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
        $this->colorElement = $element->getForm()->getElement($elementId);
        return $this->_toHtml();
    }

    /**
     * Get the current logo color setting
     *
     * @return string
     */
    public function getLogoColor(): string
    {
        $value = (string) $this->colorElement->getDataByKey('value');
        return $value ?: 'Dark';
    }

    /**
     * Get the logo color element ID
     *
     * @return string
     */
    public function getLogoColorElement(): string
    {
        return (string) $this->colorElement->getId();
    }

    /**
     * Get the logo URL based on color
     *
     * @param string $color
     * @return string
     */
    public function getLogoUrl(string $color): string
    {
        if ($color == "Light") {
            $name = "ideal/ideal-fast-checkout-rgb-light.png";
        } else {
            $name = "ideal/ideal-fast-checkout-rgb-dark.png";
        }
        return $this->assetRepo->getUrl("Buckaroo_Magento2::images/{$name}");
    }
}

