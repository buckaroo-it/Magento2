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

namespace Buckaroo\Magento2\Model\ConfigProvider\Method;

class Ideal extends AbstractConfigProvider
{
    const XPATH_IDEAL_PAYMENT_FEE           = 'payment/buckaroo_magento2_ideal/payment_fee';
    const XPATH_IDEAL_ACTIVE                = 'payment/buckaroo_magento2_ideal/active';
    const XPATH_IDEAL_SUBTEXT               = 'payment/buckaroo_magento2_ideal/subtext';
    const XPATH_IDEAL_SUBTEXT_STYLE         = 'payment/buckaroo_magento2_ideal/subtext_style';
    const XPATH_IDEAL_SUBTEXT_COLOR         = 'payment/buckaroo_magento2_ideal/subtext_color';
    const XPATH_IDEAL_ACTIVE_STATUS         = 'payment/buckaroo_magento2_ideal/active_status';
    const XPATH_IDEAL_ORDER_STATUS_SUCCESS  = 'payment/buckaroo_magento2_ideal/order_status_success';
    const XPATH_IDEAL_ORDER_STATUS_FAILED   = 'payment/buckaroo_magento2_ideal/order_status_failed';
    const XPATH_IDEAL_ORDER_EMAIL           = 'payment/buckaroo_magento2_ideal/order_email';
    const XPATH_IDEAL_AVAILABLE_IN_BACKEND  = 'payment/buckaroo_magento2_ideal/available_in_backend';

    const XPATH_ALLOW_SPECIFIC                  = 'payment/buckaroo_magento2_ideal/allowspecific';
    const XPATH_SPECIFIC_COUNTRY                = 'payment/buckaroo_magento2_ideal/specificcountry';
    const XPATH_IDEAL_SELECTION_TYPE            = 'buckaroo_magento2/account/selection_type';
    const XPATH_SPECIFIC_CUSTOMER_GROUP         = 'payment/buckaroo_magento2_ideal/specificcustomergroup';

    const XPATH_ALLOWED_CURRENCIES = 'payment/buckaroo_magento2_ideal/allowed_currencies';

    const XPATH_IDEAL_FAST_CHECKOUT_ENABLE = 'payment/buckaroo_magento2_ideal/ideal_fast_checkout';
    const XPATH_IDEAL_FAST_CHECKOUT_BUTTONS = 'payment/buckaroo_magento2_ideal/available_buttons';
    const XPATH_IDEAL_FAST_CHECKOUT_LOGO = 'payment/buckaroo_magento2_ideal/ideal_logo_colors';

    /**
     * @var array
     */
    protected $allowedCurrencies = [
        'EUR'
    ];

    /**
     * @return array|void
     */
    public function getConfig()
    {
        $storeId = $this->getStore();
        if (!$this->scopeConfig->getValue(
            static::XPATH_IDEAL_ACTIVE,
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
            $storeId
        )) {
            return [];
        }

        $paymentFeeLabel = $this->getBuckarooPaymentFeeLabel($storeId);

        return [
            'payment' => [
                'buckaroo' => [
                    'ideal' => [
                        'paymentFeeLabel'   => $paymentFeeLabel,
                        'subtext'           => $this->getSubtext($storeId),
                        'subtext_style'     => $this->getSubtextStyle($storeId),
                        'subtext_color'     => $this->getSubtextColor($storeId),
                        'allowedCurrencies' => $this->getAllowedCurrencies($storeId),
                    ],
                ],
            ],
        ];
    }

    /**
     * @param null|int $storeId
     *
     * @return float|false
     */
    public function getPaymentFee($storeId = null)
    {
        $paymentFee = $this->scopeConfig->getValue(
            self::XPATH_IDEAL_PAYMENT_FEE,
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
            $storeId
        );

        return $paymentFee ?: false;
    }

    /**
     * Generate the url to the desired asset.
     *
     * @param string $imgName
     * @param string $extension
     *
     * @return string
     */
    public function getImageUrl($imgName, string $extension = 'svg')
    {
        return parent::getImageUrl("ideal/{$imgName}", $extension);
    }

    /**
     * Check if Fast Checkout button should be shown for a specific page context.
     *
     * @param string $page
     * @param null|int|string $store
     * @return bool
     */
    public function canShowButtonForPage($page, $store = null)
    {
        $buttons = $this->getExpressButtons($store);
        if ($buttons === null || $buttons === '') {
            return false;
        }

        $pages = explode(",", $buttons);
        return in_array($page, $pages);
    }

    /**
     * Get configured visibility for Fast Checkout buttons.
     *
     * @param null|int|string $store
     * @return mixed
     */
    public function getExpressButtons($store = null)
    {
        return $this->getConfigFromXpath(self::XPATH_IDEAL_FAST_CHECKOUT_BUTTONS, $store);
    }

    /**
     * Check if iDEAL Fast Checkout is enabled.
     *
     * @param null|int|string $store
     * @return mixed
     */
    public function isFastCheckoutEnabled($store = null)
    {
        return $this->getConfigFromXpath(self::XPATH_IDEAL_FAST_CHECKOUT_ENABLE, $store);
    }

    /**
     * Check if the main iDEAL method is enabled.
     *
     * @param null|int|string $store
     * @return mixed
     */
    public function isIDealEnabled($store = null)
    {
        return $this->getConfigFromXpath(self::XPATH_IDEAL_ACTIVE, $store);
    }

    /**
     * Get the configured logo color scheme for Fast Checkout.
     *
     * @param null|int|string $store
     * @return mixed
     */
    public function getLogoColor($store = null)
    {
        return $this->getConfigFromXpath(self::XPATH_IDEAL_FAST_CHECKOUT_LOGO, $store);
    }
}
