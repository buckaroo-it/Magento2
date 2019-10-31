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
namespace TIG\Buckaroo\Block\Config\Form\Field;

use Magento\Config\Block\System\Config\Form\Fieldset as MagentoFieldset;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;

class Fieldset extends MagentoFieldset
{
    /**
     * {@inheritdoc}
     *
     */
    // @codingStandardsIgnoreLine
    protected function _getFrontendClass($element)
    {
        $value = $this->getElementValue($element);
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
        $classes .= ' ' . $class;

        return $classes;
    }

    /**
     * @param $element
     *
     * @return string
     */
    private function getElementValue($element)
    {
        $scopeValues = $this->getScopeValue();

        $group = $element->getData('group');
        $value = $this->_scopeConfig->getValue(
            $group['children']['active']['config_path'],
            $scopeValues['scope'],
            $scopeValues['scopevalue']
        );

        return $value;
    }

    /**
     * @return array
     */
    private function getScopeValue()
    {
        $scopeValues = [
            'scope' => ScopeConfigInterface::SCOPE_TYPE_DEFAULT,
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
}
