<?php
declare(strict_types=1);

namespace Buckaroo\Magento2\Plugin;

use Buckaroo\Magento2\Service\CookieParamService;

class AbstractTransactionBuilderAfterGetUrl
{
    /**
     * @var CookieParamService
     */
    private $cookieParamService;

    /**
     * Constructor.
     *
     * @param CookieParamService $cookieParamService
     */
    public function __construct(
        CookieParamService $cookieParamService
    ) {
        $this->cookieParamService = $cookieParamService;
    }

    /**
     * Append cookie-based url parameters to the transaction return url.
     *
     * @param mixed $subject
     * @param mixed $result
     * @return mixed
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
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
