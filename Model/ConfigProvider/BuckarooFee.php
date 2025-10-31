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

namespace Buckaroo\Magento2\Model\ConfigProvider;

class BuckarooFee extends AbstractConfigProvider
{
    /**
     * Buckaroo fee tax class
     */
    const XPATH_ACCOUNT_BUCKAROO_FEE_TAX_CLASS           = 'buckaroo_magento2/account/buckaroo_fee_tax_class';


    /**
     * Retrieve the tax class for Buckaroo fee
     *
     * @param null $store
     *
     * @return int|string
     */
    public function getBuckarooFeeTaxClass($store = null)
    {
        return $this->scopeConfig->getValue(self::XPATH_ACCOUNT_BUCKAROO_FEE_TAX_CLASS, \Magento\Store\Model\ScopeInterface::SCOPE_STORE, $store);
    }


    /**
     * Retrieve associated array of checkout configuration
     *
     * @param null $store
     *
     * @return array
     */
    public function getConfig($store = null)
    {
        return [
            'buckarooFee' => [
                'tax_class_id' => $this->getBuckarooFeeTaxClass($store),
            ],
        ];
    }
}
