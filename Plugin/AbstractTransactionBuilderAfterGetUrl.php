<?php
declare(strict_types=1);

namespace Buckaroo\Magento2\Plugin;

use Buckaroo\Magento2\Model\ConfigProvider\Analytics as AnalyticsConfigProvider;
use Magento\Framework\Stdlib\CookieManagerInterface;

class AbstractTransactionBuilderAfterGetUrl
{
    public function __construct(
        CookieManagerInterface $cookieManager,
        AnalyticsConfigProvider $configProvider
    ) {
        $this->cookieManager = $cookieManager;
        $this->configProvider = $configProvider;
    }
    public function afterGetReturnUrl(
        $subject,
        $result
    ) {
        //check if this feature is enabled
        if (!$this->configProvider->isClientIdTrackingEnabled()) {
            return $result;
        }
        
        try {
            $ga_cookie = $this->cookieManager->getCookie(
                '_ga'
            );
            $parts = explode(".", $ga_cookie);
            if ($parts) {
                array_shift($parts);
            }
            if ($parts) {
                array_shift($parts);
            }
            $clientId = implode(".", $parts);

            if (strpos($result, '?') !== false) {
                $result .= "&clientId=" . $clientId;
            } else {
                $result .= "?clientId=" . $clientId;
            }
        } catch (\Exception $e) {
            //@todo log
        }
        return $result;
    }
}
