<?php
/**
 * Buckaroo Magento 2 payment module (https://www.buckaroo.eu/)
 *
 * Copyright (c) Buckaroo B.V.
 * See LICENSE for license details.
 *
 * Support: support@buckaroo.nl
 *
 * @copyright Copyright (c) Buckaroo B.V.
 * @license   MIT
 */

namespace Buckaroo\Magento2\Model\ConfigProvider\Method;

class CapayableIn3 extends AbstractConfigProvider
{
    public const CODE = 'buckaroo_magento2_capayablein3';
    public const XPATH_CAPAYABLEIN3_PAYMENT_FEE          = 'payment/buckaroo_magento2_capayablein3/payment_fee';

    public const DEFAULT_NAME = 'In3';
    public const V2_NAME = 'In3';

    public const XPATH_CAPAYABLEIN3_PAYMENT_LOGO = 'payment_logo';

    /**
     * @var array
     */
    protected $allowedCurrencies = [
        'EUR'
    ];

    /**
     * @var array
     */
    protected $allowedCountries = [
        'NL'
    ];

    /**
     * @inheritdoc
     */
    public function getConfig(): array
    {
        if (!$this->getActive()) {
            return [];
        }

        return $this->fullConfig([
            'showFinancialWarning' => $this->canShowFinancialWarning(),
        ]);
    }

    /**
     * Get Logo for In3 (V3 API only)
     *
     * @param int|null $storeId
     *
     * @return string
     */
    public function getLogo($storeId = null): string
    {
        return $this->logoService->getLogoUrl("images/svg/in3.svg");
    }

    /**
     * Check if API Version is V2
     *
     * @return bool
     */
    public function isV2(): bool
    {
        return false;
    }

    /**
     * Get the configured payment fee.
     *
     * @param int|null $storeId
     *
     * @return float
     */
    public function getPaymentFee($storeId = null)
    {
        $paymentFee = $this->scopeConfig->getValue(
            self::XPATH_CAPAYABLEIN3_PAYMENT_FEE,
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
            $storeId
        );

        return $paymentFee ?: 0;
    }
}
