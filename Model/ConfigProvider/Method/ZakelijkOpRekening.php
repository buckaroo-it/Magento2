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

class ZakelijkOpRekening extends AbstractConfigProvider
{
    public const CODE = 'buckaroo_magento2_zakelijkoprekening';

    public const XPATH_PAYMENT_FEE = 'payment/buckaroo_magento2_zakelijkoprekening/payment_fee';

    public const TOOLTIP_URL = 'https://doorpakken.abnamro.nl/hulpmiddelen-en-diensten/zakelijk-op-rekening/faq/';

    /**
     * Order amount range accepted by ABN AMRO for this route. The configured
     * min/max amounts can narrow this range but not widen it.
     */
    public const MIN_ORDER_AMOUNT = 302.5;

    public const MAX_ORDER_AMOUNT = 25000.0;

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
            'tooltipText' => __('Voor iedereen, powered by ABN AMRO,'),
            'tooltipUrl'  => self::TOOLTIP_URL,
        ]);
    }

    /**
     * Get logo for Zakelijk op rekening (ABN AMRO).
     *
     * @param int|null $storeId
     *
     * @return string
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function getLogo($storeId = null): string
    {
        return $this->logoService->getLogoUrl('images/svg/zakelijk-op-rekening-abn-amro.svg');
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
            self::XPATH_PAYMENT_FEE,
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
            $storeId
        );

        return $paymentFee ?: 0;
    }
}
