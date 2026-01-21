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

namespace Buckaroo\Magento2\Service\Culture;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Sales\Model\Order;
use Magento\Store\Model\ScopeInterface;

/**
 * Service for mapping country codes to Buckaroo culture codes
 */
class CultureCodeMapper
{
    private const DEFAULT_CULTURE_CODE = 'en-US';

    /**
     * @var ScopeConfigInterface
     */
    private $scopeConfig;

    /**
     * Country code to culture code mapping
     *
     * @var array
     */
    private $countryCultureMap = [
        'US' => 'en-US',
        'GB' => 'en-GB',
        'CA' => 'en-CA',
        'AU' => 'en-AU',
        'NZ' => 'en-NZ',
        'IE' => 'en-IE',
        'IN' => 'en-IN',
        'ZA' => 'en-ZA',

        'NL' => 'nl-NL',
        'BE' => 'nl-BE',

        'FR' => 'fr-FR',
        'CD' => 'fr-CD',
        'CG' => 'fr-CG',
        'CI' => 'fr-CI',
        'SN' => 'fr-SN',
        'CM' => 'fr-CM',

        'DE' => 'de-DE',
        'AT' => 'de-AT',
        'CH' => 'de-CH',
        'LI' => 'de-LI',
        'LU' => 'de-LU',

        'ES' => 'es-ES',
        'MX' => 'es-MX',
        'AR' => 'es-AR',
        'CL' => 'es-CL',
        'CO' => 'es-CO',
        'PE' => 'es-PE',

        'PL' => 'pl-PL',
        'CZ' => 'cs-CZ',
        'SK' => 'sk-SK',
        'HU' => 'hu-HU',
        'RO' => 'ro-RO',
        'BG' => 'bg-BG',

        'GR' => 'el-GR',
        'TR' => 'tr-TR',
        'RU' => 'ru-RU',
        'SE' => 'sv-SE',
        'NO' => 'nb-NO',
        'DK' => 'da-DK',
        'FI' => 'fi-FI',
        'IT' => 'it-IT',
        'PT' => 'pt-PT',
        'BR' => 'pt-BR',

        'JP' => 'ja-JP',
        'CN' => 'zh-CN',
        'TW' => 'zh-TW',
        'KR' => 'ko-KR',
    ];

    /**
     * @param ScopeConfigInterface $scopeConfig
     */
    public function __construct(ScopeConfigInterface $scopeConfig)
    {
        $this->scopeConfig = $scopeConfig;
    }

    /**
     * Get culture code for a given order based on billing address country
     *
     * @param Order $order
     * @return string
     */
    public function getCultureCodeByOrder(Order $order): string
    {
        $countryCode = null;
        if ($order && $order->getBillingAddress()) {
            $countryCode = $order->getBillingAddress()->getCountryId();
        }

        return $this->getCultureCodeByCountry($countryCode, $order);
    }

    /**
     * Get culture code for a given country code with fallback to store locale
     *
     * @param string|null $countryCode
     * @param Order|null $order
     * @return string
     */
    public function getCultureCodeByCountry(?string $countryCode, ?Order $order = null): string
    {
        if ($countryCode && isset($this->countryCultureMap[$countryCode])) {
            return $this->countryCultureMap[$countryCode];
        }

        // Fallback to store locale
        if ($order && ($store = $order->getStore())) {
            $localeCode = $this->scopeConfig->getValue(
                'general/locale/code',
                ScopeInterface::SCOPE_STORE,
                $store
            );

            if ($localeCode) {
                // Convert en_US to en-US format
                return str_replace('_', '-', $localeCode);
            }
        }

        return self::DEFAULT_CULTURE_CODE;
    }
}
