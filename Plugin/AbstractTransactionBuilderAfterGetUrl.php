<?php
declare(strict_types=1);

namespace Buckaroo\Magento2\Plugin;

use Buckaroo\Magento2\Service\CookieParamService;

class AbstractTransactionBuilderAfterGetUrl
{
    private $cookieParamService;

    public function __construct(
        CookieParamService $cookieParamService
    ) {
        $this->cookieParamService = $cookieParamService;
    }
    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     *
     * @param mixed $subject
     * @param mixed $result
     */
    public function afterGetReturnUrl(
        $subject,
        $result
    ) {
        try {
            if (strpos($result, '?') !== false) {
                $result .= "&" . $this->cookieParamService->getUrlParamsByCookies();
            } else {
                $result .= "?" . $this->cookieParamService->getUrlParamsByCookies();
            }
            //phpcs:ignore
        } catch (\Exception $e) {
            //@todo log
        }
        return $result;
    }
}
