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

namespace Buckaroo\Magento2\Model\ConfigProvider;

class BuckarooFee extends AbstractConfigProvider
{
    /**
     * Buckaroo fee tax class
     */
    public const XPATH_ACCOUNT_BUCKAROO_FEE_TAX_CLASS           = 'buckaroo_magento2/account/buckaroo_fee_tax_class';

    /**
     * How the Buckaroo fee is shown on orders, invoices and credit memos (a Display\Type value)
     */
    public const XPATH_SALES_DISPLAY_BUCKAROO_FEE = 'tax/sales_display/buckaroo_fee';

    /**
     * Retrieve the tax class for Buckaroo fee
     *
     * @param mixed $store
     *
     * @return int|string
     */
    public function getBuckarooFeeTaxClass($store = null)
    {
        return $this->scopeConfig->getValue(self::XPATH_ACCOUNT_BUCKAROO_FEE_TAX_CLASS, \Magento\Store\Model\ScopeInterface::SCOPE_STORE, $store);
    }

    /**
     * Retrieve how the Buckaroo fee is shown on orders, invoices and credit memos
     *
     * @param mixed $store
     *
     * @return int One of the \Buckaroo\Magento2\Model\Config\Source\Display\Type constants
     */
    public function getSalesDisplayType($store = null): int
    {
        return (int)$this->scopeConfig->getValue(
            self::XPATH_SALES_DISPLAY_BUCKAROO_FEE,
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
            $store
        );
    }

    /**
     * Retrieve associated array of checkout configuration
     *
     * @param mixed $store
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
