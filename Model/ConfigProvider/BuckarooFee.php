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

namespace TIG\Buckaroo\Model\ConfigProvider;

use \TIG\Buckaroo\Model\Config\Source\Display\Type as DisplayType;

/**
 * @method int getPriceDisplayCart()
 * @method int getPriceDisplaySales()
 * @method int getPaymentFeeTax()
 * @method string getTaxClass()
 */
class BuckarooFee extends AbstractConfigProvider
{
    /**
     * Buckaroo fee tax class
     */
    const XPATH_BUCKAROOFEE_TAX_CLASS           = 'tax/classes/buckaroo_fee_tax_class';

    /**
     * Calculation fee tax settings
     */
    const XPATH_BUCKAROOFEE_PAYMENT_FEE_TAX      = 'tax/calculation/buckaroo_fee';

    /**
     * Shopping cart display settings
     */
    const XPATH_BUCKAROOFEE_PRICE_DISPLAY_CART  = 'tax/cart_display/buckaroo_fee';

    /**
     * Sales display settings
     */
    const XPATH_BUCKAROOFEE_PRICE_DISPLAY_SALES = 'tax/sales_display/buckaroo_fee';

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
                'calculation' => [
                    'buckarooPaymentFeeInclTax'    =>
                        $this->getPaymentFeeTax() == DisplayType::DISPLAY_TYPE_INCLUDING_TAX,
                ],
                'cart' => [
                    'displayBuckarooFeeBothPrices' => $this->getPriceDisplayCart() == DisplayType::DISPLAY_TYPE_BOTH,
                    'displayBuckarooFeeInclTax'    => $this->getPriceDisplayCart() == DisplayType::DISPLAY_TYPE_BOTH
                        || $this->getPriceDisplayCart() == DisplayType::DISPLAY_TYPE_INCLUDING_TAX,
                ],
                'sales' => [
                    'displayBuckarooFeeBothPrices' => $this->getPriceDisplaySales() == DisplayType::DISPLAY_TYPE_BOTH,
                    'displayBuckarooFeeInclTax'    => $this->getPriceDisplaySales() == DisplayType::DISPLAY_TYPE_BOTH
                        || $this->getPriceDisplaySales() == DisplayType::DISPLAY_TYPE_INCLUDING_TAX,
                ],
            ],
        ];
    }
}
