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
    const XPATH_SHOW_ISSUERS                    = 'payment/buckaroo_magento2_ideal/show_issuers';
    const XPATH_SORTED_ISSUERS                  = 'payment/buckaroo_magento2_ideal/sorted_issuers';

    const XPATH_ALLOWED_CURRENCIES = 'payment/buckaroo_magento2_ideal/allowed_currencies';

    // Related to Ideal Fast Checkout
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
        if (!$this->scopeConfig->getValue(
            static::XPATH_IDEAL_ACTIVE,
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        )) {
            return [];
        }

        $issuers = $this->formatIssuers();
        $paymentFeeLabel = $this->getBuckarooPaymentFeeLabel();

        $selectionType = $this->scopeConfig->getValue(
            self::XPATH_IDEAL_SELECTION_TYPE,
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );

        return [
            'payment' => [
                'buckaroo' => [
                    'ideal' => [
                        'banks' => $issuers,
                        'paymentFeeLabel' => $paymentFeeLabel,
                        'subtext'   => $this->getSubtext(),
                        'subtext_style'   => $this->getSubtextStyle(),
                        'subtext_color'   => $this->getSubtextColor(),
                        'allowedCurrencies' => $this->getAllowedCurrencies(),
                        'selectionType' => $selectionType,
                        'showIssuers' => $this->canShowIssuers()
                    ],
                ],
            ],
        ];
    }

    /**
     * Can show issuer selection in checkout
     *
     * @param string|null $storeId
     *
     * @return boolean
     */
    public function canShowIssuers(string $storeId = null): bool {
        return $this->scopeConfig->getValue(
            self::XPATH_SHOW_ISSUERS,
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
            $storeId
        ) == 1;
    }

    /**
     * @param null|int $storeId
     *
     * @return float
     */
    public function getPaymentFee($storeId = null)
    {
        $paymentFee = $this->scopeConfig->getValue(
            self::XPATH_IDEAL_PAYMENT_FEE,
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
            $storeId
        );

        return $paymentFee ? $paymentFee : false;
    }

    /**
     * @param $storeId
     * @return mixed
     */
    public function getSortedIssuers($storeId = null)
    {
        return $this->scopeConfig->getValue(
            self::XPATH_SORTED_ISSUERS,
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
            $storeId
        ) ?? '';
    }

    /**
     * Generate the url to the desired asset.
     *
     * @param string $imgName
     * @param string $extension
     *
     * @return string
     */
    public function getImageUrl($imgName, string $extension = 'png')
    {
        return parent::getImageUrl("ideal/{$imgName}", "svg");
    }

    public function canShowButtonForPage($page, $store = null)
    {
        $buttons = $this->getExpressButtons($store);
        if ($buttons === null) {
            return false;
        }

        $pages = explode(",", $buttons);
        return in_array($page, $pages);
    }

    public function getExpressButtons($store = null)
    {
        return $this->getConfigFromXpath(self::XPATH_IDEAL_FAST_CHECKOUT_BUTTONS, $store);
    }

    public function isFastCheckoutEnabled($store = null)
    {
        return $this->getConfigFromXpath(self::XPATH_IDEAL_FAST_CHECKOUT_ENABLE, $store);
    }

    public function isIDealEnabled($store = null)
    {
        return $this->getConfigFromXpath(self::XPATH_IDEAL_ACTIVE, $store);
    }

    public function getLogoColor($store = null)
    {
        return $this->getConfigFromXpath(self::XPATH_IDEAL_FAST_CHECKOUT_LOGO, $store);
    }
}
