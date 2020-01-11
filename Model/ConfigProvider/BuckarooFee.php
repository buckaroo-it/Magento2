<?php
/**
 *                  ___________       __            __
 *                  \__    ___/____ _/  |_ _____   |  |
 *                    |    |  /  _ \\   __\\__  \  |  |
 *                    |    | |  |_| ||  |   / __ \_|  |__
 *                    |____|  \____/ |__|  (____  /|____/
 *                                              \/
 *          ___          __                                   __
 *         |   |  ____ _/  |_   ____ _______   ____    ____ _/  |_
 *         |   | /    \\   __\_/ __ \\_  __ \ /    \ _/ __ \\   __\
 *         |   ||   |  \|  |  \  ___/ |  | \/|   |  \\  ___/ |  |
 *         |___||___|  /|__|   \_____>|__|   |___|  / \_____>|__|
 *                  \/                           \/
 *                  ________
 *                 /  _____/_______   ____   __ __ ______
 *                /   \  ___\_  __ \ /  _ \ |  |  \\____ \
 *                \    \_\  \|  | \/|  |_| ||  |  /|  |_| |
 *                 \______  /|__|    \____/ |____/ |   __/
 *                        \/                       |__|
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Creative Commons License.
 * It is available through the world-wide-web at this URL:
 * http://creativecommons.org/licenses/by-nc-nd/3.0/nl/deed.en_US
 * If you are unable to obtain it through the world-wide-web, please send an email
 * to servicedesk@tig.nl so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade this module to newer
 * versions in the future. If you wish to customize this module for your
 * needs please contact servicedesk@tig.nl for more information.
 *
 * @copyright Copyright (c) Total Internet Group B.V. https://tig.nl/copyright
 * @license   http://creativecommons.org/licenses/by-nc-nd/3.0/nl/deed.en_US
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
