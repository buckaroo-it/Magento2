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

namespace Buckaroo\Magento2\Model\ConfigProvider\Method;

class ZakelijkOpRekening extends AbstractConfigProvider
{
    public const CODE = 'buckaroo_magento2_zakelijkoprekening';

    public const XPATH_PAYMENT_FEE = 'payment/buckaroo_magento2_zakelijkoprekening/payment_fee';

    public const TOOLTIP_URL = 'https://doorpakken.abnamro.nl/hulpmiddelen-en-diensten/zakelijk-op-rekening/faq/';

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
