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

namespace Buckaroo\Magento2\Model\ConfigProvider\Method;

use Buckaroo\Magento2\Exception;
use Magento\Store\Model\ScopeInterface;
use Buckaroo\Magento2\Model\Config\Source\PaypalButtonStyle;

class Paypal extends AbstractConfigProvider
{
    public const CODE = 'buckaroo_magento2_paypal';

    public const XPATH_PAYPAL_SELLERS_PROTECTION               = 'payment/buckaroo_magento2_paypal/sellers_protection';
    public const XPATH_PAYPAL_SELLERS_PROTECTION_ELIGIBLE      = 'payment/' .
        'buckaroo_magento2_paypal/sellers_protection_eligible';
    public const XPATH_PAYPAL_SELLERS_PROTECTION_INELIGIBLE    = 'payment/' .
        'buckaroo_magento2_paypal/sellers_protection_ineligible';
    public const XPATH_PAYPAL_SELLERS_PROTECTION_ITEMNOTRECEIVED_ELIGIBLE = 'payment/' .
        'buckaroo_magento2_paypal/sellers_protection_itemnotreceived_eligible';
    public const XPATH_PAYPAL_SELLERS_PROTECTION_UNAUTHORIZEDPAYMENT_ELIGIBLE = 'payment/' .
        'buckaroo_magento2_paypal/sellers_protection_unauthorizedpayment_eligible';

    public const XPATH_PAYPAL_EXPRESS_BUTTONS           = 'payment/buckaroo_magento2_paypal/available_buttons';
    public const XPATH_PAYPAL_EXPRESS_MERCHANT_ID       = 'payment/buckaroo_magento2_paypal/express_merchant_id';
    public const XPATH_PAYPAL_EXPRESS_BUTTON_COLOR      = 'payment/buckaroo_magento2_paypal/express_button_color';
    public const XPATH_PAYPAL_EXPRESS_BUTTON_IS_ROUNDED = 'payment/buckaroo_magento2_paypal/express_button_rounded';

    /**
     * @inheritdoc
     *
     * @throws Exception
     */
    public function getConfig(): array
    {
        $paymentFeeLabel = $this->getBuckarooPaymentFeeLabel(self::CODE);

        return [
            'payment' => [
                'buckaroo' => [
                    'paypal' => [
                        'paymentFeeLabel' => $paymentFeeLabel,
                        'subtext'   => $this->getSubtext(),
                        'subtext_style'   => $this->getSubtextStyle(),
                        'subtext_color'   => $this->getSubtextColor(),
                        'allowedCurrencies' => $this->getAllowedCurrencies(),
                    ],
                ],
            ],
        ];
    }

    /**
     * Get Sellers Protection
     *
     * @param null|int|string $store
     * @return mixed
     */
    public function getSellersProtection($store = null)
    {
        return $this->scopeConfig->getValue(
            static::XPATH_PAYPAL_SELLERS_PROTECTION,
            ScopeInterface::SCOPE_STORE,
            $store
        );
    }

    /**
     * Get Sellers Protection Eligible
     *
     * @param null|int|string $store
     * @return mixed
     */
    public function getSellersProtectionEligible($store = null)
    {
        return $this->scopeConfig->getValue(
            static::XPATH_PAYPAL_SELLERS_PROTECTION_ELIGIBLE,
            ScopeInterface::SCOPE_STORE,
            $store
        );
    }

    /**
     * Get Sellers Protection Ineligible
     *
     * @param null|int|string $store
     * @return mixed
     */
    public function getSellersProtectionIneligible($store = null)
    {
        return $this->scopeConfig->getValue(
            static::XPATH_PAYPAL_SELLERS_PROTECTION_INELIGIBLE,
            ScopeInterface::SCOPE_STORE,
            $store
        );
    }

    /**
     * Get Sellers Protection Unauthorizedpayment Eligible
     *
     * @param null|int|string $store
     * @return mixed
     */
    public function getSellersProtectionItemnotreceivedEligible($store = null)
    {
        return $this->scopeConfig->getValue(
            static::XPATH_PAYPAL_SELLERS_PROTECTION_ITEMNOTRECEIVED_ELIGIBLE,
            ScopeInterface::SCOPE_STORE,
            $store
        );
    }

    /**
     * Get Sellers Protection Unauthorizedpayment Eligible
     *
     * @param null|int|string $store
     * @return mixed
     */
    public function getSellersProtectionUnauthorizedpaymentEligible($store = null)
    {
        return $this->scopeConfig->getValue(
            static::XPATH_PAYPAL_SELLERS_PROTECTION_UNAUTHORIZEDPAYMENT_ELIGIBLE,
            ScopeInterface::SCOPE_STORE,
            $store
        );
    }

    /**
     * Enable or disable Paypal express buttons
     *
     * @param null|int|string $store
     * @return mixed
     */
    public function getExpressButtons($store = null)
    {
        return $this->getConfigFromXpath(self::XPATH_PAYPAL_EXPRESS_BUTTONS, $store);
    }

    /**
     * Get PayPal merchant ID
     *
     * @param null|int|string $store
     * @return mixed
     */
    public function getExpressMerchantId($store = null)
    {
        return $this->getConfigFromXpath(self::XPATH_PAYPAL_EXPRESS_MERCHANT_ID, $store);
    }

    /**
     * Get PayPal express button color
     *
     * @param null|int|string $store
     * @return string
     */
    public function getButtonColor($store = null): string
    {
        $color = $this->getConfigFromXpath(self::XPATH_PAYPAL_EXPRESS_BUTTON_COLOR, $store);
        if (!is_string($color)) {
            $color = PaypalButtonStyle::COLOR_DEFAULT;
        }
        return $color;
    }

    /**
     * Get PayPal express button shape
     *
     * @param null|int|string $store
     * @return string
     */
    public function getButtonShape($store = null): string
    {
        return $this->getConfigFromXpath(self::XPATH_PAYPAL_EXPRESS_BUTTON_IS_ROUNDED, $store) === "1"
            ? 'pill'
            : 'rect';
    }


    /**
     * Test if express button is enabled for the $page
     *
     * @param string $page
     * @return boolean
     */
    public function canShowButtonForPage($page, $store = null)
    {
        $buttons = $this->getExpressButtons($store);
        if ($buttons === null) {
            return false;
        }

        $pages = explode(",", $buttons);
        return in_array($page, $pages);
    }
}
