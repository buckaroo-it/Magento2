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

namespace Buckaroo\Magento2\Block\Adminhtml\System\Config;

use Magento\Backend\Block\Widget\Button;
use Magento\Config\Block\System\Config\Form\Field;
use Magento\Framework\Data\Form\Element\AbstractElement;
use Magento\Framework\Exception\LocalizedException;

class CredentialsChecker extends Field
{
    /**
     * @inheritdoc
     */
    protected function _construct()
    {
        parent::_construct();
        $this->setTemplate('Buckaroo_Magento2::credentials_checker.phtml');
    }

    /**
     * @inheritdoc
     */
    public function render(AbstractElement $element)
    {
        $element->unsScope()->unsCanUseWebsiteValue()->unsCanUseDefaultValue();
        return parent::render($element);
    }

    /**
     * Get Test Credentials button html
     *
     * @throws LocalizedException
     *
     * @return string
     */
    public function getHtml()
    {
        $button = $this->getLayout()->createBlock(Button::class);
        if (!$button instanceof Button) {
            return '';
        }

        return $button->setData([
            'id' => 'buckaroo_magento2_credentials_checker_button',
            'label' => __('Test Credentials')
        ])->toHtml();
    }

    /**
     * Get the ACL-protected admin URL used to validate the credentials
     *
     * @return string
     */
    public function getAjaxUrl(): string
    {
        return $this->getUrl('buckaroo/credentialschecker/index', $this->getScopeParams());
    }

    /**
     * The configuration scope currently selected in the store switcher.
     *
     * The button validates whatever credentials that scope resolves to, so the scope has to travel
     * with the request. Without it the controller falls back to the default scope and a merchant
     * with per-store-view credentials gets a verdict about the wrong Buckaroo account.
     *
     * @return array
     */
    private function getScopeParams(): array
    {
        $params = [];

        $storeId = $this->getRequest()->getParam('store');
        if ($storeId !== null && $storeId !== '') {
            $params['store'] = $storeId;

            return $params;
        }

        $websiteId = $this->getRequest()->getParam('website');
        if ($websiteId !== null && $websiteId !== '') {
            $params['website'] = $websiteId;
        }

        return $params;
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
    public function _getElementHtml(AbstractElement $element): string
    {
        return $this->_toHtml();
    }
}
