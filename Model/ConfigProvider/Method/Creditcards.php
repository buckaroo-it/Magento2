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

use Magento\Store\Model\ScopeInterface;

/**
 * @method getPaymentFeeLabel()
 * @method getSellersProtection()
 * @method getSellersProtectionEligible()
 * @method getSellersProtectionIneligible()
 * @method getSellersProtectionItemnotreceivedEligible()
 * @method getSellersProtectionUnauthorizedpaymentEligible()
 */
class Creditcards extends AbstractConfigProvider
{
    /**#@+
     * Creditcard service codes.
     */
    const CREDITCARD_SERVICE_CODE_MASTERCARD    = 'MasterCard';
    const CREDITCARD_SERVICE_CODE_VISA          = 'Visa';
    const CREDITCARD_SERVICE_CODE_AMEX          = 'Amex';
    const CREDITCARD_SERVICE_CODE_MAESTRO       = 'Maestro';

    const XPATH_CREDITCARDS_PAYMENT_FEE = 'payment/buckaroo_magento2_creditcards/payment_fee';
    const XPATH_CREDITCARDS_PAYMENT_FEE_LABEL = 'payment/buckaroo_magento2_creditcards/payment_fee_label';
    const XPATH_CREDITCARDS_ACTIVE = 'payment/buckaroo_magento2_creditcards/active';
    const XPATH_CREDITCARDS_SUBTEXT                = 'payment/buckaroo_magento2_creditcards/subtext';
    const XPATH_CREDITCARDS_SUBTEXT_STYLE          = 'payment/buckaroo_magento2_creditcards/subtext_style';
    const XPATH_CREDITCARDS_SUBTEXT_COLOR          = 'payment/buckaroo_magento2_creditcards/subtext_color';
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
    const XPATH_CREDITCARDS_ALLOWED_ISSUERS = 'payment/buckaroo_magento2_creditcards/allowed_issuers';
    const XPATH_CREDITCARDS_HOSTED_FIELDS_CLIENT_ID = 'payment/buckaroo_magento2_creditcards/hosted_fields_client_id';
    const XPATH_CREDITCARDS_HOSTED_FIELDS_CLIENT_SECRET = 'payment/buckaroo_magento2_creditcards/hosted_fields_client_secret';
    const XPATH_ALLOWED_CURRENCIES = 'payment/buckaroo_magento2_creditcards/allowed_currencies';
    const XPATH_ALLOW_SPECIFIC = 'payment/buckaroo_magento2_creditcards/allowspecific';
    const XPATH_SPECIFIC_COUNTRY = 'payment/buckaroo_magento2_creditcards/specificcountry';
    const XPATH_SPECIFIC_CUSTOMER_GROUP = 'payment/buckaroo_magento2_creditcards/specificcustomergroup';

    protected $issuers = [
        [
            'name' => 'American Express',
            'code' => self::CREDITCARD_SERVICE_CODE_AMEX,
            'sort' => 0
        ],
        [
            'name' => 'Maestro',
            'code' => self::CREDITCARD_SERVICE_CODE_MAESTRO,
            'sort' => 0
        ],
        [
            'name' => 'MasterCard',
            'code' => self::CREDITCARD_SERVICE_CODE_MASTERCARD,
            'sort' => 0
        ],
        [
            'name' => 'VISA',
            'code' => self::CREDITCARD_SERVICE_CODE_VISA,
            'sort' => 0
        ]
    ];

    /**
     * @return array
     */
    public function getConfig()
    {
        $paymentFeeLabel = $this->getBuckarooPaymentFeeLabel();

        $issuers = $this->formatIssuers();

        return [
            'payment' => [
                'buckaroo' => [
                    'creditcards' => [
                        'paymentFeeLabel' => $paymentFeeLabel,
                        'subtext'   => $this->getSubtext(),
                        'subtext_style'   => $this->getSubtextStyle(),
                        'subtext_color'   => $this->getSubtextColor(),
                        'creditcards' => $issuers,
                        'defaultCardImage' => $this->getImageUrl('svg/creditcards', 'svg'),
                        'allowedCurrencies' => $this->getAllowedCurrencies(),
                        'isTestMode' => $this->isTestMode()
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
            self::XPATH_CREDITCARDS_PAYMENT_FEE,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );

        return $paymentFee ? $paymentFee : false;
    }

    public function getHostedFieldsClientId()
    {
        return $this->scopeConfig->getValue(
            self::XPATH_CREDITCARDS_HOSTED_FIELDS_CLIENT_ID,
            ScopeInterface::SCOPE_STORE
        );
    }

    public function getHostedFieldsClientSecret()
    {
        return $this->scopeConfig->getValue(
            self::XPATH_CREDITCARDS_HOSTED_FIELDS_CLIENT_SECRET,
            ScopeInterface::SCOPE_STORE
        );
    }

    /**
     * Add the active flag to the creditcard list. This is used in the checkout process.
     *
     * @return array
     */
    public function formatIssuers()
    {
        $allowed = explode(',', (string)$this->scopeConfig->getValue(
            self::XPATH_CREDITCARDS_ALLOWED_ISSUERS,
            ScopeInterface::SCOPE_STORE
        ));

        $issuers = $this->issuers;

        foreach ($issuers as $key => $issuer) {
            $issuers[$key]['active'] = in_array($issuer['code'], $allowed);
            $issuers[$key]['img'] = $this->getCreditcardLogo($issuer['code']);
        }

        return $issuers;
    }

    public function getSupportedServices(): array
    {
        $issuers = $this->formatIssuers();
        $supportedServices = [];

        foreach ($issuers as $issuer) {
            if ($issuer['active']) {
                $supportedServices[] = $issuer['code'];
            }
        }

        return $supportedServices;
    }
}
