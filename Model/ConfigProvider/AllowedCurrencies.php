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
     * @return array
     */
    public function getAllowedCurrencies(): array
    {
        return $this->allowedCurrencies;
    }

    /**
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
