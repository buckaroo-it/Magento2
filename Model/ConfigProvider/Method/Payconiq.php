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
use Magento\Framework\Data\Form\FormKey;
use Magento\Framework\View\Asset\Repository;
use Buckaroo\Magento2\Helper\PaymentFee;
use Buckaroo\Magento2\Model\ConfigProvider\AllowedCurrencies;

/**
 * @method getSellersProtection()
 * @method getSellersProtectionEligible()
 * @method getSellersProtectionIneligible()
 * @method getSellersProtectionItemnotreceivedEligible()
 * @method getSellersProtectionUnauthorizedpaymentEligible()
 */
class Payconiq extends AbstractConfigProvider
{
    public const XPATH_PAYCONIQ_PAYMENT_FEE                      = 'payment/buckaroo_magento2_payconiq/payment_fee';
    public const XPATH_PAYCONIQ_ACTIVE                           = 'payment/buckaroo_magento2_payconiq/active';
    public const XPATH_PAYCONIQ_SUBTEXT                          = 'payment/buckaroo_magento2_payconiq/subtext';
    public const XPATH_PAYCONIQ_SUBTEXT_STYLE                    = 'payment/buckaroo_magento2_payconiq/subtext_style';
    public const XPATH_PAYCONIQ_SUBTEXT_COLOR                    = 'payment/buckaroo_magento2_payconiq/subtext_color';
    public const XPATH_PAYCONIQ_ACTIVE_STATUS                    = 'payment/buckaroo_magento2_payconiq/active_status';
    public const XPATH_PAYCONIQ_ORDER_STATUS_SUCCESS             = 'payment/buckaroo_magento2_payconiq/order_status_success';
    public const XPATH_PAYCONIQ_ORDER_STATUS_FAILED              = 'payment/buckaroo_magento2_payconiq/order_status_failed';
    public const XPATH_PAYCONIQ_AVAILABLE_IN_BACKEND             = 'payment/buckaroo_magento2_payconiq/available_in_backend';
    public const XPATH_PAYCONIQ_SELLERS_PROTECTION               = 'payment/buckaroo_magento2_payconiq/sellers_protection';
    public const XPATH_PAYCONIQ_SELLERS_PROTECTION_ELIGIBLE      = 'payment/'.
        'buckaroo_magento2_payconiq/sellers_protection_eligible';
    public const XPATH_PAYCONIQ_SELLERS_PROTECTION_INELIGIBLE    = 'payment/'.
        'buckaroo_magento2_payconiq/sellers_protection_ineligible';
    public const XPATH_PAYCONIQ_SELLERS_PROTECTION_ITEMNOTRECEIVED_ELIGIBLE = 'payment/'.
        'buckaroo_magento2_payconiq/sellers_protection_itemnotreceived_eligible';
    public const XPATH_PAYCONIQ_SELLERS_PROTECTION_UNAUTHORIZEDPAYMENT_ELIGIBLE = 'payment/'.
        'buckaroo_magento2_payconiq/sellers_protection_unauthorizedpayment_eligible';

    public const XPATH_ALLOWED_CURRENCIES = 'payment/buckaroo_magento2_payconiq/allowed_currencies';

    public const XPATH_ALLOW_SPECIFIC                  = 'payment/buckaroo_magento2_payconiq/allowspecific';
    public const XPATH_SPECIFIC_COUNTRY                = 'payment/buckaroo_magento2_payconiq/specificcountry';
    public const XPATH_SPECIFIC_CUSTOMER_GROUP         = 'payment/buckaroo_magento2_payconiq/specificcustomergroup';

    public const PAYCONIC_REDIRECT_URL = '/buckaroo/payconiq/pay';

    /** @var FormKey */
    private $formKey;

    /**
     * @param Repository           $assetRepo
     * @param ScopeConfigInterface $scopeConfig
     * @param AllowedCurrencies    $allowedCurrencies
     * @param PaymentFee           $paymentFeeHelper
     * @param FormKey              $formKey
     */
    public function __construct(
        Repository $assetRepo,
        ScopeConfigInterface $scopeConfig,
        AllowedCurrencies $allowedCurrencies,
        PaymentFee $paymentFeeHelper,
        FormKey $formKey
    ) {
        parent::__construct($assetRepo, $scopeConfig, $allowedCurrencies, $paymentFeeHelper);

        $this->formKey = $formKey;
    }

    private function getFormKey()
    {
        return $this->formKey->getFormKey();
    }

    /**
     * @return array
     */
    public function getConfig()
    {
        $paymentFeeLabel = $this->getBuckarooPaymentFeeLabel();

        return [
            'payment' => [
                'buckaroo' => [
                    'payconiq' => [
                        'paymentFeeLabel' => $paymentFeeLabel,
                        'subtext'   => $this->getSubtext(),
                        'subtext_style'   => $this->getSubtextStyle(),
                        'subtext_color'   => $this->getSubtextColor(),
                        'allowedCurrencies' => $this->getAllowedCurrencies(),
                        'redirecturl' => self::PAYCONIC_REDIRECT_URL . '?form_key=' . $this->getFormKey(),
                    ],
                ],
            ],
        ];
    }

    /**
     * @param  null|mixed $storeId
     * @return float
     */
    public function getPaymentFee($storeId = null)
    {
        $paymentFee = $this->scopeConfig->getValue(
            self::XPATH_PAYCONIQ_PAYMENT_FEE,
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );

        return $paymentFee ? $paymentFee : false;
    }
}
