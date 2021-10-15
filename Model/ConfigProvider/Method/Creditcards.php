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

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\View\Asset\Repository;
use Magento\Store\Model\ScopeInterface;
use Buckaroo\Magento2\Helper\PaymentFee;
use Buckaroo\Magento2\Model\ConfigProvider\AllowedCurrencies;

class Creditcards extends AbstractConfigProvider
{
    const XPATH_CREDITCARDS_PAYMENT_FEE = 'payment/buckaroo_magento2_creditcards/payment_fee';
    const XPATH_CREDITCARDS_PAYMENT_FEE_LABEL = 'payment/buckaroo_magento2_creditcards/payment_fee_label';
    const XPATH_CREDITCARDS_ACTIVE = 'payment/buckaroo_magento2_creditcards/active';
    const XPATH_CREDITCARDS_ACTIVE_STATUS = 'payment/buckaroo_magento2_creditcards/active_status';
    const XPATH_CREDITCARDS_ORDER_STATUS_SUCCESS = 'payment/buckaroo_magento2_creditcards/order_status_success';
    const XPATH_CREDITCARDS_ORDER_STATUS_FAILED = 'payment/buckaroo_magento2_creditcards/order_status_failed';
    const XPATH_CREDITCARDS_AVAILABLE_IN_BACKEND = 'payment/buckaroo_magento2_creditcards/available_in_backend';
    const XPATH_CREDITCARDS_SELLERS_PROTECTION = 'payment/buckaroo_magento2_creditcards/sellers_protection';
    const XPATH_CREDITCARDS_SELLERS_PROTECTION_ELIGIBLE = 'payment/'.
        'buckaroo_magento2_creditcards/sellers_protection_eligible';
    const XPATH_CREDITCARDS_SELLERS_PROTECTION_INELIGIBLE = 'payment/'.
        'buckaroo_magento2_creditcards/sellers_protection_ineligible';
    const XPATH_CREDITCARDS_SELLERS_PROTECTION_ITEMNOTRECEIVED_ELIGIBLE = 'payment/'.
        'buckaroo_magento2_creditcards/sellers_protection_itemnotreceived_eligible';
    const XPATH_CREDITCARDS_SELLERS_PROTECTION_UNAUTHORIZEDPAYMENT_ELIGIBLE = 'payment/'.
        'buckaroo_magento2_creditcards/sellers_protection_unauthorizedpayment_eligible';
    const XPATH_CREDITCARDS_ALLOWED_ISSUERS = 'payment/buckaroo_magento2_creditcards/allowed_creditcards';
    const XPATH_USE_CARD_DESIGN = 'payment/buckaroo_magento2_creditcards/card_design';
    const XPATH_ALLOWED_CURRENCIES = 'payment/buckaroo_magento2_creditcards/allowed_currencies';
    const XPATH_ALLOW_SPECIFIC = 'payment/buckaroo_magento2_creditcards/allowspecific';
    const XPATH_SPECIFIC_COUNTRY = 'payment/buckaroo_magento2_creditcards/specificcountry';
    const XPATH_SPECIFIC_CUSTOMER_GROUP = 'payment/buckaroo_magento2_creditcards/specificcustomergroup';

    /**
     * Creditcards constructor.
     *
     * @param Repository           $assetRepo
     * @param ScopeConfigInterface $scopeConfig
     * @param AllowedCurrencies    $allowedCurrencies
     * @param PaymentFee           $paymentFeeHelper
     * @param Creditcard           $creditcardConfigProvider
     */
    public function __construct(
        Repository $assetRepo,
        ScopeConfigInterface $scopeConfig,
        AllowedCurrencies $allowedCurrencies,
        PaymentFee $paymentFeeHelper,
        Creditcard $creditcardConfigProvider
    ) {
        parent::__construct($assetRepo, $scopeConfig, $allowedCurrencies, $paymentFeeHelper);

        $this->issuers = $creditcardConfigProvider->getIssuers();
    }

    /**
     * @return array
     */
    public function getConfig()
    {
        $paymentFeeLabel = $this->getBuckarooPaymentFeeLabel(
            \Buckaroo\Magento2\Model\Method\Creditcards::PAYMENT_METHOD_CODE
        );
        $issuers = $this->formatIssuers();

        return [
            'payment' => [
                'buckaroo' => [
                    'creditcards' => [
                        'paymentFeeLabel' => $paymentFeeLabel,
                        'creditcards' => $issuers,
                        'defaultCardImage' => $this->getImageUrl('buckaroo_magento2_creditcard_title'),
                        'useCardDesign' => $this->useCardDesign(),
                        'allowedCurrencies' => $this->getAllowedCurrencies(),
                    ],
                ],
            ],
        ];
    }

    /**
     * @param null|int $storeId
     *
     * @return float
     */
    public function getPaymentFee($storeId = null)
    {
        $paymentFee = $this->scopeConfig->getValue(
            static::XPATH_CREDITCARDS_PAYMENT_FEE,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );

        return $paymentFee ? $paymentFee : false;
    }

    /**
     * Add the active flag to the creditcard list. This is used in the checkout process.
     *
     * @return array
     */
    public function formatIssuers()
    {
        $issuers = parent::formatIssuers();
        $allowed = explode(',', $this->scopeConfig->getValue(
            static::XPATH_CREDITCARDS_ALLOWED_ISSUERS,
            ScopeInterface::SCOPE_STORE
        ));

        foreach ($issuers as $key => $issuer) {
            $issuers[$key]['active'] = in_array($issuer['code'], $allowed);
        }

        return $issuers;
    }

    /**
     * @return bool
     */
    private function useCardDesign()
    {
        return $this->scopeConfig->getValue(static::XPATH_USE_CARD_DESIGN, ScopeInterface::SCOPE_STORE);
    }



    /**
     * get Payment Fee Labe
     */
    public function getPaymentFeeLabel($store = null)
    {
        return $this->scopeConfig->getValue(
            self::XPATH_CREDITCARDS_PAYMENT_FEE_LABEL,
            ScopeInterface::SCOPE_STORE,
            $store
        );
    }

    /**
     * get Active
     */
    public function getActive($store = null)
    {
        return $this->scopeConfig->getValue(
            self::XPATH_CREDITCARDS_ACTIVE,
            ScopeInterface::SCOPE_STORE,
            $store
        );
    }

    /**
     * get Active Status
     */
    public function getActiveStatus($store = null)
    {
        return $this->scopeConfig->getValue(
            self::XPATH_CREDITCARDS_ACTIVE_STATUS,
            ScopeInterface::SCOPE_STORE,
            $store
        );
    }

    /**
     * get Order Status Success
     */
    public function getOrderStatusSuccess($store = null)
    {
        return $this->scopeConfig->getValue(
            self::XPATH_CREDITCARDS_ORDER_STATUS_SUCCESS,
            ScopeInterface::SCOPE_STORE,
            $store
        );
    }

    /**
     * get Order Status Failed
     */
    public function getOrderStatusFailed($store = null)
    {
        return $this->scopeConfig->getValue(
            self::XPATH_CREDITCARDS_ORDER_STATUS_FAILED,
            ScopeInterface::SCOPE_STORE,
            $store
        );
    }

    /**
     * get Available In Backend
     */
    public function getAvailableInBackend($store = null)
    {
        return $this->scopeConfig->getValue(
            self::XPATH_CREDITCARDS_AVAILABLE_IN_BACKEND,
            ScopeInterface::SCOPE_STORE,
            $store
        );
    }

    /**
     * get Sellers Protection
     */
    public function getSellersProtection($store = null)
    {
        return $this->scopeConfig->getValue(
            self::XPATH_CREDITCARDS_SELLERS_PROTECTION,
            ScopeInterface::SCOPE_STORE,
            $store
        );
    }

    /**
     * get Sellers Protection Eligible
     */
    public function getSellersProtectionEligible($store = null)
    {
        return $this->scopeConfig->getValue(
            self::XPATH_CREDITCARDS_SELLERS_PROTECTION_ELIGIBLE,
            ScopeInterface::SCOPE_STORE,
            $store
        );
    }

    /**
     * get Sellers Protection Ineligible
     */
    public function getSellersProtectionIneligible($store = null)
    {
        return $this->scopeConfig->getValue(
            self::XPATH_CREDITCARDS_SELLERS_PROTECTION_INELIGIBLE,
            ScopeInterface::SCOPE_STORE,
            $store
        );
    }

    /**
     * get Sellers Protection Itemnotreceived Eligible
     */
    public function getSellersProtectionItemnotreceivedEligible($store = null)
    {
        return $this->scopeConfig->getValue(
            self::XPATH_CREDITCARDS_SELLERS_PROTECTION_ITEMNOTRECEIVED_ELIGIBLE,
            ScopeInterface::SCOPE_STORE,
            $store
        );
    }

    /**
     * get Sellers Protection Unauthorizedpayment Eligible
     */
    public function getSellersProtectionUnauthorizedpaymentEligible($store = null)
    {
        return $this->scopeConfig->getValue(
            self::XPATH_CREDITCARDS_SELLERS_PROTECTION_UNAUTHORIZEDPAYMENT_ELIGIBLE,
            ScopeInterface::SCOPE_STORE,
            $store
        );
    }

    /**
     * get Allowed Issuers
     */
    public function getAllowedIssuers($store = null)
    {
        return $this->scopeConfig->getValue(
            self::XPATH_CREDITCARDS_ALLOWED_ISSUERS,
            ScopeInterface::SCOPE_STORE,
            $store
        );
    }
    
    /**
     * get Active Status Cm3
     */
    public function getActiveStatusCm3($store = null)
    {
        return null;
    }
}
