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

namespace Buckaroo\Magento2\Block\Adminhtml\System\Config\Fieldset;

use Magento\Backend\Block\Template;
use Magento\Backend\Block\Context;
use Magento\Backend\Model\Auth\Session;
use Magento\Config\Block\System\Config\Form\Fieldset;
use Magento\Config\Model\Config;
use Magento\Framework\Data\Form\Element\AbstractElement;
use Magento\Framework\View\Helper\Js;

/**
 * Fieldset renderer for Buckaroo solution
 */
class Payment extends Fieldset
{
    private const HEADER_TEMPLATE = 'Buckaroo_Magento2::system/config/fieldset/payment_header.phtml';

    /**
     * @var Config
     */
    protected $backendConfig;

    /**
     * @param Context $context
     * @param Session $authSession
     * @param Js      $jsHelper
     * @param Config  $backendConfig
     * @param array   $data
     */
    public function __construct(
        Context $context,
        Session $authSession,
        Js $jsHelper,
        Config $backendConfig,
        array $data = []
    ) {
        $this->backendConfig = $backendConfig;
        parent::__construct($context, $authSession, $jsHelper, $data);
    }

    /**
     * Render the payment fieldset header HTML.
     *
     * @param AbstractElement $element
     *
     * @return string
     *
     * @SuppressWarnings(PHPMD.NPathComplexity)
     */
    protected function _getHeaderTitleHtml($element)
    {
        return $this->renderHeaderTitleTemplate($element);
    }

    /**
     * Render the payment fieldset header through a template block.
     *
     * @param AbstractElement $element
     *
     * @return string
     */
    private function renderHeaderTitleTemplate(AbstractElement $element): string
    {
        $groupConfig = $element->getGroup();
        $isEnabled = $this->isPaymentEnabled($element);
        $templateBlock = $this->getLayout()->createBlock(Template::class);

        return $templateBlock
            ->setTemplate(self::HEADER_TEMPLATE)
            ->setData(
                [
                    'comment' => $element->getComment(),
                    'demo_url' => $groupConfig['demo_url'] ?? '',
                    'html_id' => $element->getHtmlId(),
                    'is_enabled' => $isEnabled,
                    'legend' => $element->getLegend(),
                    'more_url' => $groupConfig['more_url'] ?? '',
                    'toggle_url' => $this->getUrl('adminhtml/*/state'),
                ]
            )
            ->toHtml();
    }

    /**
     * Check whether the current payment method is enabled
     *
     * @param AbstractElement $element
     *
     * @return bool
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    protected function isPaymentEnabled($element)
    {
        return (bool)(string)$this->backendConfig->getConfigDataValue('buckaroo_magento2/account/active') > 0;
    }

    /**
     * Return the fieldset header comment HTML.
     *
     * @param AbstractElement $element
     *
     * @return string
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    protected function _getHeaderCommentHtml($element)
    {
        return '';
    }

    /**
     * Determine the initial collapse state.
     *
     * @param AbstractElement $element
     *
     * @return false
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    protected function _isCollapseState($element)
    {
        return false;
    }

    /**
     * Return the extra JavaScript needed for the fieldset behavior.
     *
     * @param AbstractElement $element
     *
     * @return string
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    protected function _getExtraJs($element)
    {
        $script = "require(['jquery', 'prototype'], function(jQuery){
            window.buckarooToggleSolution = function (id, url) {
                var doScroll = false;
                Fieldset.toggleCollapse(id, url);
                if ($(this).hasClassName(\"open\")) {
                    $$(\".with-button button.button\").each(function(anotherButton) {
                        if (anotherButton != this && $(anotherButton).hasClassName(\"open\")) {
                            $(anotherButton).click();
                            doScroll = true;
                        }
                    }.bind(this));
                }
                if (doScroll) {
                    var pos = Element.cumulativeOffset($(this));
                    window.scrollTo(pos[0], pos[1] - 45);
                }
            }
        });";

        return $this->_jsHelper->getScript($script);
    }

    /**
     * Return the frontend CSS class for the fieldset.
     *
     * @param AbstractElement $element
     *
     * @return string
     */
    protected function _getFrontendClass($element)
    {
        $enabledString = $this->isPaymentEnabled($element) ? ' enabled' : '';
        return parent::_getFrontendClass($element) . ' with-button' . $enabledString;
    }
}
