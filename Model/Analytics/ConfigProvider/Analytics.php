<?php
declare(strict_types=1);

namespace Buckaroo\Magento2\Model\Analytics\ConfigProvider;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Store\Model\ScopeInterface;

class Analytics
{
    private const XML_PATH_ANALYTICS_ENABLE_GA_CLIENT_ID_TRACKING
        = 'buckaroo_magento2/analytics/enable_ga_client_id_tracking';
    private const XML_PATH_ANALYTICS_COOKIE_PARAM_PAIRS
        = 'buckaroo_magento2/analytics/cookie_param_pair';

    /**
     * @var Json
     */
    protected $serialize;

    /**
     * @var ScopeConfigInterface
     */
    private $storeConfig;

    public function __construct(
        ScopeConfigInterface $storeConfig,
        Json $serialize
    ) {
        $this->storeConfig = $storeConfig;
        $this->serialize = $serialize;
    }

    public function isClientIdTrackingEnabled(): bool
    {
        $config = $this->storeConfig->getValue(
            static::XML_PATH_ANALYTICS_ENABLE_GA_CLIENT_ID_TRACKING,
            ScopeInterface::SCOPE_STORES
        );
        return (bool)$config;
    }

    public function getCookieParamPairs(): array
    {
        $configValue = $this->storeConfig->getValue(
            static::XML_PATH_ANALYTICS_COOKIE_PARAM_PAIRS,
            ScopeInterface::SCOPE_STORES
        );

        if (is_array($configValue)) {
            return $configValue;
        }

        try {
            return $this->serialize->unserialize($configValue);
        } catch (\Exception $e) {
            return [];
        }
    }
}
