<?php
/**
 * NOTICE OF LICENSE
 *
 * This source file is subject to the MIT License
 * It is available through the world-wide-web at this URL:
 * https://tldrlegal.com/license/mit-license
 * If you are unable to obtain it through the world-wide-web, please email
 * to support@buckaroo.nl, so we can send you a copy immediately.
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

use Buckaroo\Magento2\Service\LogoService;
use Magento\Backend\Block\Context;
use Magento\Backend\Model\Auth\Session;
use Magento\Config\Block\System\Config\Form\Fieldset as MagentoFieldset;
use Magento\Framework\Data\Form\Element\AbstractElement;
use Magento\Framework\View\Helper\Js;
use Magento\Framework\View\Helper\SecureHtmlRenderer;

class VerificationMethod extends MagentoFieldset
{
    /**
     * @var LogoService
     */
    protected LogoService $logoService;

    /**
     * @param Context $context
     * @param Session $authSession
     * @param Js $jsHelper
     * @param LogoService $logoService
     * @param array $data
     * @param SecureHtmlRenderer|null $secureRenderer
     */
    public function __construct(
        Context $context,
        Session $authSession,
        Js $jsHelper,
        LogoService $logoService,
        array $data = [],
        ?SecureHtmlRenderer $secureRenderer = null
    ) {
        parent::__construct($context, $authSession, $jsHelper, $data, $secureRenderer);
        $this->logoService = $logoService;
    }

    /**
     * Get frontend class
     *
     * @param AbstractElement $element
     * @return string
     */
    protected function _getFrontendClass($element): string
    {
        return parent::_getFrontendClass($element). ' bk-payment-method';
    }



    /**
     * Get the header title HTML including a logo.
     *
     * @param AbstractElement $element
     * @return string
     */
    protected function _getHeaderTitleHtml($element): string
    {
        if (!isset($element->getGroup()['id']) ||
            !is_string($element->getGroup()['id'])
        ) {
            return parent::_getHeaderTitleHtml($element);
        }

        $element->setLegend($this->getTabImgAndLink($element));
        return parent::_getHeaderTitleHtml($element);
    }

    /**
     * @param AbstractElement $element
     * @return string
     */
    private function getTabImgAndLink($element)
    {
        $code = str_replace("buckaroo_magento2_", "", $element->getGroup()['id']);
        $logo = $this->getLogo($code);
        return '<div class="bk-tab-title"><img class="bk-ad-payment-logo" src="' . $logo . '">'.
         $element->getLegend().
         "</div>";
    }

    /**
     * Get logo
     *
     * @param string $code
     * @return string
     */
    private function getLogo(string $code): string
    {
        if ($code === 'idin') {
            return $this->logoService->getAssetUrl("Buckaroo_Magento2::images/idin.svg");
        }

        return '';
    }
}
