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

use Magento\Store\Model\ScopeInterface;

class Paypal extends AbstractConfigProvider
{
    public const CODE = 'buckaroo_magento2_paypal';

    public const SELLERS_PROTECTION                              = 'sellers_protection';
    public const SELLERS_PROTECTION_ELIGIBLE                     = 'sellers_protection_eligible';
    public const SELLERS_PROTECTION_INELIGIBLE                   = 'sellers_protection_ineligible';
    public const SELLERS_PROTECTION_ITEMNOTRECEIVED_ELIGIBLE     = 'sellers_protection_itemnotreceived_eligible';
    public const SELLERS_PROTECTION_UNAUTHORIZEDPAYMENT_ELIGIBLE = 'sellers_protection_unauthorizedpayment_eligible';
    public const XPATH_PAYPAL_PAYMENT_FEE                        = 'payment/buckaroo_magento2_paypal/payment_fee';

    public const EXPRESS_BUTTONS           = 'available_buttons';
    public const EXPRESS_MERCHANT_ID       = 'express_merchant_id';
    public const EXPRESS_BUTTON_COLOR      = 'express_button_color';
    public const EXPRESS_BUTTON_IS_ROUNDED = 'express_button_rounded';

    /**
     * Get Sellers Protection
     *
     * @param  null|int|string $store
     * @return mixed
     */
    public function getSellersProtection($store = null)
    {
        return $this->getMethodConfigValue(self::SELLERS_PROTECTION, $store);
    }

    /**
     * Get Sellers Protection Eligible
     *
     * @param  null|int|string $store
     * @return mixed
     */
    public function getSellersProtectionEligible($store = null)
    {
        return $this->getMethodConfigValue(self::SELLERS_PROTECTION_ELIGIBLE, $store);
    }

    /**
     * Get Sellers Protection Ineligible
     *
     * @param  null|int|string $store
     * @return mixed
     */
    public function getSellersProtectionIneligible($store = null)
    {
        return $this->getMethodConfigValue(self::SELLERS_PROTECTION_INELIGIBLE, $store);
    }

    /**
     * Get Sellers Protection Unauthorizedpayment Eligible
     *
     * @param  null|int|string $store
     * @return mixed
     */
    public function getSellersProtectionItemnotreceivedEligible($store = null)
    {
        return $this->getMethodConfigValue(self::SELLERS_PROTECTION_ITEMNOTRECEIVED_ELIGIBLE, $store);
    }

    /**
     * Get Sellers Protection Unauthorizedpayment Eligible
     *
     * @param  null|int|string $store
     * @return mixed
     */
    public function getSellersProtectionUnauthorizedpaymentEligible($store = null)
    {
        return $this->getMethodConfigValue(self::SELLERS_PROTECTION_UNAUTHORIZEDPAYMENT_ELIGIBLE, $store);
    }

    /**
     * Get PayPal merchant ID
     *
     * @param  null|int|string $store
     * @return mixed
     */
    public function getExpressMerchantId($store = null)
    {
        return $this->getMethodConfigValue(self::EXPRESS_MERCHANT_ID, $store);
    }

    /**
     * Get PayPal express button color
     *
     * @param  null|int|string $store
     * @return string
     */
    public function getButtonColor($store = null): string
    {
        return $this->getMethodConfigValue(self::EXPRESS_BUTTON_COLOR, $store);
    }

    /**
     * Get PayPal express button shape
     *
     * @param  null|int|string $store
     * @return string
     */
    public function getButtonShape($store = null): string
    {
        return $this->getMethodConfigValue(self::EXPRESS_BUTTON_IS_ROUNDED, $store) === "1"
            ? 'pill'
            : 'rect';
    }

    /**
     * Test if express button is enabled for the $page
     *
     * @param  string     $page
     * @param  null|mixed $store
     * @return bool
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

    /**
     * Enable or disable Paypal express buttons
     *
     * @param  null|int|string $store
     * @return mixed
     */
    public function getExpressButtons($store = null)
    {
        return $this->getMethodConfigValue(self::EXPRESS_BUTTONS, $store);
    }

    /**
     * @param  null             $store
     * @return string|int|float
     */
    public function getPaymentFee($store = null)
    {
        $paymentFee = $this->scopeConfig->getValue(
            self::XPATH_PAYPAL_PAYMENT_FEE,
            ScopeInterface::SCOPE_STORE,
            $store
        );

        return $paymentFee ?: 0;
    }
}
