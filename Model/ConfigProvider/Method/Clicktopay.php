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

namespace Buckaroo\Magento2\Model\ConfigProvider\Method;

use Buckaroo\Magento2\Helper\PaymentFee;
use Buckaroo\Magento2\Model\ConfigProvider\AllowedCurrencies;
use Buckaroo\Magento2\Service\LogoService;
use Laminas\Uri\UriFactory;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Locale\Resolver;
use Magento\Framework\View\Asset\Repository;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;

class Clicktopay extends AbstractConfigProvider
{
    public const CODE = 'buckaroo_magento2_clicktopay';

    public const XPATH_CLICKTOPAY_CLIENT_ID           = 'client_id';
    public const XPATH_CLICKTOPAY_CLIENT_SECRET       = 'client_secret';
    public const XPATH_CLICKTOPAY_MERCHANT_IDENTIFIER = 'merchant_identifier';

    /**
     * @var StoreManagerInterface
     */
    private StoreManagerInterface $storeManager;

    /**
     * @var Resolver
     */
    private Resolver $localeResolver;

    /**
     * @param Repository            $assetRepo
     * @param ScopeConfigInterface  $scopeConfig
     * @param AllowedCurrencies     $allowedCurrencies
     * @param PaymentFee            $paymentFeeHelper
     * @param LogoService           $logoService
     * @param StoreManagerInterface $storeManager
     * @param Resolver              $localeResolver
     */
    public function __construct(
        Repository $assetRepo,
        ScopeConfigInterface $scopeConfig,
        AllowedCurrencies $allowedCurrencies,
        PaymentFee $paymentFeeHelper,
        LogoService $logoService,
        StoreManagerInterface $storeManager,
        Resolver $localeResolver
    ) {
        parent::__construct($assetRepo, $scopeConfig, $allowedCurrencies, $paymentFeeHelper, $logoService);

        $this->storeManager   = $storeManager;
        $this->localeResolver = $localeResolver;
    }

    /**
     * @inheritdoc
     */
    public function getConfig(): array
    {
        if (!$this->getActive()) {
            return [];
        }

        return $this->fullConfig([
            'merchantIdentifier' => $this->getMerchantIdentifier(),
            'storeName'          => $this->getStoreName(),
            'currency'           => $this->getStoreCurrency(),
            'country'            => $this->getDefaultCountry(),
            'locale'             => $this->getLocale(),
            'targetOrigins'      => [$this->getStoreBaseUrl()],
        ]);
    }

    /**
     * Get Click to Pay Client ID (Token API credential from Buckaroo Plaza)
     *
     * @param null|int|string $store
     *
     * @return mixed
     */
    public function getClientId($store = null)
    {
        return $this->getMethodConfigValue(self::XPATH_CLICKTOPAY_CLIENT_ID, $store);
    }

    /**
     * Get Click to Pay Client Secret (Token API credential from Buckaroo Plaza)
     *
     * @param null|int|string $store
     *
     * @return mixed
     */
    public function getClientSecret($store = null)
    {
        return $this->getMethodConfigValue(self::XPATH_CLICKTOPAY_CLIENT_SECRET, $store);
    }

    /**
     * Get Merchant Identifier (Buckaroo merchant GUID)
     *
     * @param null|int|string $store
     *
     * @return mixed
     */
    public function getMerchantIdentifier($store = null)
    {
        return $this->getMethodConfigValue(self::XPATH_CLICKTOPAY_MERCHANT_IDENTIFIER, $store);
    }

    /**
     * Get store name for the Drop-in UI display
     *
     * @return string
     * @throws NoSuchEntityException
     */
    public function getStoreName(): string
    {
        return (string) $this->storeManager->getStore()->getName();
    }

    /**
     * Get the current store currency code
     *
     * @return string
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function getStoreCurrency(): string
    {
        return (string) $this->storeManager->getStore()->getCurrentCurrency()->getCode();
    }

    /**
     * Get locale in language_COUNTRY format (e.g. en_US)
     *
     * @return string
     */
    public function getLocale(): string
    {
        return (string) $this->localeResolver->getLocale();
    }

    /**
     * Get default country ISO 3166 code
     *
     * @return mixed
     */
    public function getDefaultCountry()
    {
        return $this->scopeConfig->getValue(
            'general/country/default',
            ScopeInterface::SCOPE_WEBSITES
        );
    }

    /**
     * Get store base URL for TargetOrigins whitelisting
     *
     * @return string
     * @throws NoSuchEntityException
     */
    public function getStoreBaseUrl(): string
    {
        $baseUrl = (string) $this->storeManager->getStore()->getBaseUrl();

        try {
            $uri = UriFactory::factory($baseUrl);
        } catch (\InvalidArgumentException $e) {
            return rtrim($baseUrl, '/');
        }

        if (empty($uri->getScheme()) || empty($uri->getHost())) {
            return rtrim($baseUrl, '/');
        }

        $origin = $uri->getScheme() . '://' . $uri->getHost();

        if (!empty($uri->getPort())) {
            $origin .= ':' . $uri->getPort();
        }

        return $origin;
    }

    /**
     * @inheritdoc
     */
    public function getBaseAllowedCurrencies(): array
    {
        return [
            'EUR',
            'USD',
            'GBP',
            'DKK',
            'NOK',
            'SEK',
            'CHF',
            'PLN',
        ];
    }
}
