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

class Ideal2 extends AbstractConfigProvider
{
    const XPATH_IDEAL2_PAYMENT_FEE           = 'payment/buckaroo_magento2_ideal2/payment_fee';
    const XPATH_IDEAL2_PAYMENT_FEE_LABEL     = 'payment/buckaroo_magento2_ideal2/payment_fee_label';
    const XPATH_IDEAL2_ACTIVE                = 'payment/buckaroo_magento2_ideal2/active';
    const XPATH_IDEAL2_SUBTEXT               = 'payment/buckaroo_magento2_ideal2/subtext';
    const XPATH_IDEAL2_SUBTEXT_STYLE         = 'payment/buckaroo_magento2_ideal2/subtext_style';
    const XPATH_IDEAL2_SUBTEXT_COLOR         = 'payment/buckaroo_magento2_ideal2/subtext_color';
    const XPATH_IDEAL2_ACTIVE_STATUS         = 'payment/buckaroo_magento2_ideal2/active_status';
    const XPATH_IDEAL2_ORDER_STATUS_SUCCESS  = 'payment/buckaroo_magento2_ideal2/order_status_success';
    const XPATH_IDEAL2_ORDER_STATUS_FAILED   = 'payment/buckaroo_magento2_ideal2/order_status_failed';
    const XPATH_IDEAL2_ORDER_EMAIL           = 'payment/buckaroo_magento2_ideal2/order_email';
    const XPATH_IDEAL2_AVAILABLE_IN_BACKEND  = 'payment/buckaroo_magento2_ideal2/available_in_backend';

    const XPATH_ALLOWED_CURRENCIES = 'payment/buckaroo_magento2_ideal2/allowed_currencies';

    const XPATH_ALLOW_SPECIFIC                  = 'payment/buckaroo_magento2_ideal2/allowspecific';
    const XPATH_SPECIFIC_COUNTRY                = 'payment/buckaroo_magento2_ideal2/specificcountry';
    const XPATH_IDEAL2_SELECTION_TYPE            = 'buckaroo_magento2/account/selection_type';
    const XPATH_SPECIFIC_CUSTOMER_GROUP         = 'payment/buckaroo_magento2_ideal2/specificcustomergroup';
    const XPATH_SHOW_ISSUERS                    = 'payment/buckaroo_magento2_ideal2/show_issuers';
    const XPATH_SORTED_ISSUERS                  = 'payment/buckaroo_magento2_ideal2/sorted_issuers';

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
            static::XPATH_IDEAL2_ACTIVE,
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        )) {
            return [];
        }

        $issuers = $this->formatIssuers();
        $paymentFeeLabel = $this->getBuckarooPaymentFeeLabel(
            \Buckaroo\Magento2\Model\Method\Ideal2::PAYMENT_METHOD_CODE
        );

        $selectionType = $this->scopeConfig->getValue(
            self::XPATH_IDEAL2_SELECTION_TYPE,
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );

        return [
            'payment' => [
                'buckaroo' => [
                    'ideal2' => [
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
            self::XPATH_IDEAL2_PAYMENT_FEE,
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
}
