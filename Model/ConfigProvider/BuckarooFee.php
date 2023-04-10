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
declare(strict_types=1);

namespace Buckaroo\Magento2\Model\ConfigProvider;

use Buckaroo\Magento2\Model\Config\Source\Display\Type as DisplayType;
use Magento\Store\Model\ScopeInterface;

class BuckarooFee extends AbstractConfigProvider
{
    /**
     * Buckaroo fee tax class
     */
    public const XPATH_BUCKAROOFEE_TAX_CLASS = 'tax/classes/buckaroo_fee_tax_class';

    /**
     * Calculation fee tax settings
     */
    public const XPATH_BUCKAROOFEE_PAYMENT_FEE_TAX = 'tax/calculation/buckaroo_fee';

    /**
     * Shopping cart display settings
     */
    public const XPATH_BUCKAROOFEE_PRICE_DISPLAY_CART = 'tax/cart_display/buckaroo_fee';

    /**
     * Sales display settings
     */
    public const XPATH_BUCKAROOFEE_PRICE_DISPLAY_SALES = 'tax/sales_display/buckaroo_fee';

    /**
     * Retrieve associated array of checkout configuration
     *
     * @param int|null|string $store
     * @return array
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function getConfig($store = null): array
    {
        return [
            'buckarooFee' => [
                'calculation' => [
                    'buckarooPaymentFeeInclTax' =>
                        $this->getPaymentFeeTax() == DisplayType::DISPLAY_TYPE_INCLUDING_TAX,
                ],
                'cart'        => [
                    'displayBuckarooFeeBothPrices' => $this->getPriceDisplayCart() == DisplayType::DISPLAY_TYPE_BOTH,
                    'displayBuckarooFeeInclTax'    => $this->getPriceDisplayCart() == DisplayType::DISPLAY_TYPE_BOTH
                        || $this->getPriceDisplayCart() == DisplayType::DISPLAY_TYPE_INCLUDING_TAX,
                ],
                'sales'       => [
                    'displayBuckarooFeeBothPrices' => $this->getPriceDisplaySales() == DisplayType::DISPLAY_TYPE_BOTH,
                    'displayBuckarooFeeInclTax'    => $this->getPriceDisplaySales() == DisplayType::DISPLAY_TYPE_BOTH
                        || $this->getPriceDisplaySales() == DisplayType::DISPLAY_TYPE_INCLUDING_TAX,
                ],
            ],
        ];
    }

    /**
     * Get Buckaroo payment fee
     *
     * @param null|int|string $store
     * @return mixed
     */
    public function getPaymentFeeTax($store = null)
    {
        return $this->scopeConfig->getValue(
            static::XPATH_BUCKAROOFEE_PAYMENT_FEE_TAX,
            ScopeInterface::SCOPE_STORE,
            $store
        );
    }

    /**
     * Get Buckaroo fee displayed on cart
     *
     * @param null|int|string $store
     * @return mixed
     */
    public function getPriceDisplayCart($store = null)
    {
        return $this->scopeConfig->getValue(
            static::XPATH_BUCKAROOFEE_PRICE_DISPLAY_CART,
            ScopeInterface::SCOPE_STORE,
            $store
        );
    }

    /**
     * Get Buckaroo fee display on sales
     *
     * @param null|int|string $store
     * @return mixed
     */
    public function getPriceDisplaySales($store = null)
    {
        return $this->scopeConfig->getValue(
            static::XPATH_BUCKAROOFEE_PRICE_DISPLAY_SALES,
            ScopeInterface::SCOPE_STORE,
            $store
        );
    }

    /**
     * Get Buckaroo fee tax class
     *
     * @param null|int|string $store
     * @return mixed
     */
    public function getTaxClass($store = null)
    {
        return $this->scopeConfig->getValue(
            static::XPATH_BUCKAROOFEE_TAX_CLASS,
            ScopeInterface::SCOPE_STORE,
            $store
        );
    }
}
