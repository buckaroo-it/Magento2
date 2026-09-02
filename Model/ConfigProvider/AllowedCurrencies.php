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
declare(strict_types=1);

namespace Buckaroo\Magento2\Model\ConfigProvider;

class AllowedCurrencies extends AbstractConfigProvider
{
    /**
     * @var array
     */
    protected $allowedCurrencies = [
        'CAD',
        'GBP',
        'EUR',
        'NOK',
        'SHR',
        'USD',
        'SEK',
        'DKK',
        'ARS',
        'BRL',
        'HRK',
        'LTL',
        'TRY',
        'TRL',
        'AUD',
        'CNY',
        'LVL',
        'MXN',
        'MXP',
        'PLN',
        'CHF',
        'CZK',
        'HUF',
        'ISK',
        'JPY',
        'NZD',
        'RUB',
        'ZAR'
    ];

    /**
     * Get the config.
     *
     * @return array
     */
    public function getConfig(): array
    {
        return [
            'allowedCurrencies' => $this->getAllowedCurrencies(),
        ];
    }

    /**
     * Get the list of allowed currencies.
     *
     * @return array
     */
    public function getAllowedCurrencies(): array
    {
        return $this->allowedCurrencies;
    }

    /**
     * Set the list of allowed currencies.
     *
     * @param array $allowedCurrencies
     *
     * @return $this
     */
    public function setAllowedCurrencies(array $allowedCurrencies): AllowedCurrencies
    {
        $this->allowedCurrencies = $allowedCurrencies;

        return $this;
    }
}
