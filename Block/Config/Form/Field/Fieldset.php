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
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Data\Form\Element\AbstractElement;
use Magento\Framework\View\Helper\Js;
use Magento\Framework\View\Helper\SecureHtmlRenderer;
use Magento\Store\Model\ScopeInterface;

class Fieldset extends MagentoFieldset
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
     * Collapsed or expanded fieldset when page loaded?
     *
     * @param AbstractElement $element
     * @return bool
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    protected function _isCollapseState($element): bool
    {
        return false;
    }

    /**
     * Get frontend class
     *
     * @param AbstractElement $element
     * @return string
     */
    protected function _getFrontendClass($element): string
    {
        if ($element->getGroup()['id'] === 'buckaroo_magento2_klarna_group' || 'buckaroo_magento2_afterpay_group') {
            $value = $this->getActiveStatusByGroup($element);
        } else {
            $value = $this->getElementValue($element);
        }

        $class = 'payment_method_';

        switch ($value) {
            case '0':
                $class .= 'payment_method_inactive';
                break;
            case '1':
                $class .= 'payment_method_active payment_method_test';
                break;
            default:
                $class .= 'payment_method_active payment_method_live';
                break;
        }

        $classes = parent::_getFrontendClass($element);
        $classes .= ' bk-payment-method ' . $class;

        return $classes;
    }

    /**
     * Get element value
     *
     * @param AbstractElement $element
     * @return string
     */
    private function getElementValue(AbstractElement $element): string
    {
        $scopeValues = $this->getScopeValue();

        $group = $element->getData('group');
        return $this->_scopeConfig->getValue(
            $group['children']['active']['config_path'],
            $scopeValues['scope'],
            $scopeValues['scopevalue']
        );
    }

    /**
     * Get active status for a group
     *
     * @param AbstractElement $element
     * @return string
     */
    private function getActiveStatusByGroup(AbstractElement $element): string
    {
        $children = $element->getGroup()['children'];
        $status = '0';

        foreach ($children as $child) {
            $childStatus = $this->getElementValueByConfigPath($child['children']['active']['config_path'] ?? '');
            if($childStatus === '1') {
                $status = '1';
                continue;
            }
            if($childStatus === '2') {
                return '2';
            }

        }

        return $status;

    }

    /**
     * Get element value
     *
     * @param string $configPath
     * @return string
     */
    private function getElementValueByConfigPath(string $configPath): string
    {
        if (empty($configPath)) {
            return '';
        }
        $scopeValues = $this->getScopeValue();

        return $this->_scopeConfig->getValue(
            $configPath,
            $scopeValues['scope'],
            $scopeValues['scopevalue']
        );
    }

    /**
     * Get scope value
     *
     * @return array
     */
    private function getScopeValue(): array
    {
        $scopeValues = [
            'scope'      => ScopeConfigInterface::SCOPE_TYPE_DEFAULT,
            'scopevalue' => null
        ];

        $store = $this->getRequest()->getParam('store');
        $website = $this->getRequest()->getParam('website');

        if (!empty($website)) {
            $scopeValues['scope'] = ScopeInterface::SCOPE_WEBSITE;
            $scopeValues['scopevalue'] = $website;
        }

        if (!empty($store)) {
            $scopeValues['scope'] = ScopeInterface::SCOPE_STORE;
            $scopeValues['scopevalue'] = $store;
        }

        return $scopeValues;
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
    private function getTabImgAndLink($element) {
        $method = str_replace("buckaroo_magento2_", "", $element->getGroup()['id']);
        $logo = $this->getPaymentLogo($method);
        return '<div class="bk-tab-title"><img class="bk-ad-payment-logo" src="' . $logo . '">'.
         $element->getLegend().
         "</div>";
    }

    /**
     * Get payment method logo
     *
     * @param string $method
     * @return string
     */
    private function getPaymentLogo(string $method): string
    {
        if ($method == "voucher") {
            $method = "buckaroovoucher";
        }

        if ($method == "klarna_group") {
            $method = "klarna";
        }

        if ($method == "afterpay_group") {
            $method = "afterpay";
        }

        return $this->logoService->getPayment($method, true);
    }
}
