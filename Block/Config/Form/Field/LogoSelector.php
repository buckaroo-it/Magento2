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

use \Magento\Config\Block\System\Config\Form\Field;
use Magento\Framework\Data\Form\Element\AbstractElement;
use Magento\Framework\View\Asset\Repository;
use Magento\Backend\Block\Template\Context;
use Magento\Framework\View\Helper\SecureHtmlRenderer;

class LogoSelector extends Field
{
    /**
     * @var Repository
     */
    protected Repository $assetRepo;

    /**
     * @param Context $context
     * @param Repository $assetRepo
     * @param array $data
     * @param SecureHtmlRenderer|null $secureRenderer
     */
    public function __construct(
        Context $context,
        Repository $assetRepo,
        array $data = [],
        ?SecureHtmlRenderer $secureRenderer = null
    ) {
        $this->assetRepo = $assetRepo;
        parent::__construct($context, $data, $secureRenderer);
    }

    /**
     * @inheritdoc
     */
    protected function _construct()
    {
        parent::_construct();
        $this->setTemplate('Buckaroo_Magento2::in3_logo.phtml');
    }

    /**
     * Return element html
     *
     * @param  AbstractElement $element
     * @return string
     */
    protected function _getElementHtml(AbstractElement $element)
    {
        $element->setData('type', 'hidden');
        $this->assign("input", $element->getElementHtml());
        $this->assign("inputId",   $element->getHtmlId());
        $this->assign("inputValue",   $element->getEscapedValue());
        return $this->_toHtml();
    }
    
    public function getLogos(): array
    {
        return [
            "ideal-in3.svg" => $this->assetRepo->getUrl("Buckaroo_Magento2::images/svg/ideal-in3.svg"),
            "in3.svg" => $this->assetRepo->getUrl("Buckaroo_Magento2::images/svg/in3.svg")
        ];
    }
}
