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

class Klarnakp extends AbstractConfigProvider
{
    public const CODE = 'buckaroo_magento2_klarnakp';

    public const XPATH_KLARNAKP_CREATE_INVOICE_BY_SHIP =
        'payment/buckaroo_magento2_klarnakp/create_invoice_after_shipment';

    /**
     * @var array
     */
    protected $allowedCurrencies = [
        'EUR',
        'AUD',
        'CAD',
        'CHF',
        'DKK',
        'GBP',
        'NOK',
        'SEK'
    ];

    /**
     * @var array
     */
    protected $allowedCountries = [
        'NL',
        'BE',
        'DE',
        'AT',
        'FI',
        'IT',
        'FR',
        'ES',
        'AU',
        'CA',
        'CH',
        'DK',
        'GB',
        'NO',
        'SE'
    ];

    /**
     * @inheritdoc
     *
     * @throws Exception
     */
    public function getConfig(): array
    {
        if (!$this->getActive()) {
            return [];
        }

        $paymentFeeLabel = $this->getBuckarooPaymentFeeLabel(self::CODE);

        return [
            'payment' => [
                'buckaroo' => [
                    'klarnakp' => [
                        'paymentFeeLabel'      => $paymentFeeLabel,
                        'subtext'              => $this->getSubtext(),
                        'subtext_style'        => $this->getSubtextStyle(),
                        'subtext_color'        => $this->getSubtextColor(),
                        'allowedCurrencies'    => $this->getAllowedCurrencies(),
                        'paymentFee'           => $this->getPaymentFee(),
                        'showFinancialWarning' => $this->canShowFinancialWarning()
                    ],
                    'response' => [],
                ],
            ],
        ];
    }

    /**
     * Get Create Invoice After Shipment
     *
     * @param null|int|string $store
     * @return bool
     */
    public function isInvoiceCreatedAfterShipment($store = null): bool
    {
        $createInvoiceAfterShipment = (bool)$this->scopeConfig->getValue(
            static::XPATH_KLARNAKP_CREATE_INVOICE_BY_SHIP,
            ScopeInterface::SCOPE_STORE,
            $store
        );

        return $createInvoiceAfterShipment ?: false;
    }
}
