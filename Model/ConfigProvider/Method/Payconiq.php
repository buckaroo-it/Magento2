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
use Magento\Store\Model\ScopeInterface;
use Buckaroo\Magento2\Helper\PaymentFee;
use Buckaroo\Magento2\Model\ConfigProvider\AllowedCurrencies;

class Payconiq extends AbstractConfigProvider
{
    public const CODE = 'buckaroo_magento2_payconiq';

    public const XPATH_PAYCONIQ_SELLERS_PROTECTION               =
        'payment/buckaroo_magento2_payconiq/sellers_protection';
    public const XPATH_PAYCONIQ_SELLERS_PROTECTION_ELIGIBLE      =
        'payment/buckaroo_magento2_payconiq/sellers_protection_eligible';
    public const XPATH_PAYCONIQ_SELLERS_PROTECTION_INELIGIBLE    =
        'payment/buckaroo_magento2_payconiq/sellers_protection_ineligible';
    public const XPATH_PAYCONIQ_SELLERS_PROTECTION_ITEMNOTRECEIVED_ELIGIBLE =
        'payment/buckaroo_magento2_payconiq/sellers_protection_itemnotreceived_eligible';
    public const XPATH_PAYCONIQ_SELLERS_PROTECTION_UNAUTHORIZEDPAYMENT_ELIGIBLE =
        'payment/buckaroo_magento2_payconiq/sellers_protection_unauthorizedpayment_eligible';

    public const PAYCONIC_REDIRECT_URL = '/buckaroo/payconiq/pay';

    /** @var FormKey */
    private FormKey $formKey;

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
     * @inheritdoc
     */
    public function getConfig()
    {
        $paymentFeeLabel = $this->getBuckarooPaymentFeeLabel(self::CODE);

        return [
            'payment' => [
                'buckaroo' => [
                    'payconiq' => [
                        'paymentFeeLabel' => $paymentFeeLabel,
                        'allowedCurrencies' => $this->getAllowedCurrencies(),
                        'redirecturl' => static::PAYCONIC_REDIRECT_URL . '?form_key=' . $this->getFormKey()
                    ],
                ],
            ],
        ];
    }

    /**
     * @inheritdoc
     */
    public function getSellersProtection($store = null)
    {
        return $this->scopeConfig->getValue(
            static::XPATH_PAYCONIQ_SELLERS_PROTECTION,
            ScopeInterface::SCOPE_STORE,
            $store
        );
    }

    /**
     * @inheritdoc
     */
    public function getSellersProtectionEligible($store = null)
    {
        return $this->scopeConfig->getValue(
            static::XPATH_PAYCONIQ_SELLERS_PROTECTION_ELIGIBLE,
            ScopeInterface::SCOPE_STORE,
            $store
        );
    }

    /**
     * @inheritdoc
     */
    public function getSellersProtectionIneligible($store = null)
    {
        return $this->scopeConfig->getValue(
            static::XPATH_PAYCONIQ_SELLERS_PROTECTION_INELIGIBLE,
            ScopeInterface::SCOPE_STORE,
            $store
        );
    }

    /**
     * @inheritdoc
     */
    public function getSellersProtectionItemnotreceivedEligible($store = null)
    {
        return $this->scopeConfig->getValue(
            static::XPATH_PAYCONIQ_SELLERS_PROTECTION_ITEMNOTRECEIVED_ELIGIBLE,
            ScopeInterface::SCOPE_STORE,
            $store
        );
    }

    /**
     * @inheritdoc
     */
    public function getSellersProtectionUnauthorizedpaymentEligible($store = null)
    {
        return $this->scopeConfig->getValue(
            static::XPATH_PAYCONIQ_SELLERS_PROTECTION_UNAUTHORIZEDPAYMENT_ELIGIBLE,
            ScopeInterface::SCOPE_STORE,
            $store
        );
    }
}
