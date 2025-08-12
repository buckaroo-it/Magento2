<?php
declare(strict_types=1);

namespace Buckaroo\Magento2\Service;

use Buckaroo\Magento2\Model\Analytics\ConfigProvider\Analytics as AnalyticsConfigProvider;
use Magento\Framework\Stdlib\CookieManagerInterface;

class CookieParamService
{
    /**
     * @var CookieManagerInterface
     */
    private CookieManagerInterface $cookieManager;

    /**
     * @var AnalyticsConfigProvider
     */
    private AnalyticsConfigProvider $configProvider;

    /**
     * @param CookieManagerInterface $cookieManager
     * @param AnalyticsConfigProvider $configProvider
     */
    public function __construct(
        CookieManagerInterface $cookieManager,
        AnalyticsConfigProvider $configProvider
    ) {
        $this->cookieManager = $cookieManager;
        $this->configProvider = $configProvider;
    }

    /**
     * Get Url Params based on the settings from Analytics configuration
     *
     * @return string
     */
    public function getUrlParamsByCookies(): string
    {
        if (!$this->configProvider->isClientIdTrackingEnabled()) {
            return '';
        }

        $pairs = $this->configProvider->getCookieParamPairs();

        if (empty($pairs)) {
            return '';
        }

        $urlParams = '';

        foreach ($pairs as $pair) {
            $cookieValue = $this->cookieManager->getCookie($pair['cookie']);
            if (empty($cookieValue)) {
                continue;
            }
            $urlParamValue = $this->getUrlParamValueByCookie($cookieValue, $pair['replace_regex']);
            $urlParams .= $pair['url_param'] . '=' . $urlParamValue . '&';
        }

        return substr($urlParams, 0, -1);
    }

    /**
     * Return only params that are also in configuration
     *
     * @param array $queryParams
     * @return array
     */
    public function getQueryArgumentsByCookies(array $queryParams): array
    {
        if (!$this->configProvider->isClientIdTrackingEnabled()) {
            return [];
        }

        $pairs = $this->configProvider->getCookieParamPairs();

        if (empty($pairs)) {
            return [];
        }

        $urlParamNames = array_column($pairs, 'url_param');

        foreach ($queryParams as $key => $queryParam) {
            if (!in_array($key, $urlParamNames)) {
                unset($queryParams[$key]);
            }
        }

        return $queryParams;
    }

    /**
     * Prepare the cookie into url param needed
     *
     * @param string $cookieValue
     * @param string $regex
     * @return string
     */
    public function getUrlParamValueByCookie(string $cookieValue, string $regex): string
    {
        if (!empty($regex)) {
            $cookieValue = preg_replace($regex, "", $cookieValue);
        }

        return $cookieValue;
    }
}
