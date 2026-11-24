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

namespace Buckaroo\Magento2\Service;

use Buckaroo\Magento2\Model\ConfigProvider\Account as AccountConfig;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\App\CacheInterface;
use Magento\Framework\App\Request\Http;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Data\Form\FormKey\Validator as FormKeyValidator;
use Magento\Framework\HTTP\PhpEnvironment\RemoteAddress;

/**
 * Gatekeeper for the storefront endpoints that mint Buckaroo OAuth tokens on the
 * merchant's credentials (Hosted Fields, Click to Pay).
 *
 * A request is only served when it is a POST that carries a valid form key, which is
 * bound to the caller's own session, and that session holds an active checkout quote.
 *
 * Token minting is also rate limited:
 * - per checkout session (fixed, low): the main control for a single shopper;
 * - per client IP (configurable, high, 0 = off): an abuse ceiling against callers that
 *   rotate sessions. The client IP is resolved with the merchant's "Client IP Detection
 *   Headers" setting, as elsewhere in the module, so shops behind a proxy can count real
 *   shopper IPs instead of the proxy's.
 *
 * @SuppressWarnings(PHPMD.CookieAndSessionMisuse) The per-session budget has to live in the checkout session
 */
class CheckoutTokenRequestGuard
{
    private const FRONTEND_ORIGIN_HEADER = 'X-Requested-From';
    private const FRONTEND_ORIGIN_VALUE  = 'MagentoFrontend';

    /**
     * Fixed rate-limit window, in seconds.
     */
    private const RATE_LIMIT_WINDOW = 600;

    /**
     * Maximum tokens per checkout session and per window. A normal checkout needs a
     * handful (first mount, re-mounts after switching methods, one refresh every ~15 min).
     */
    public const SESSION_LIMIT = 10;

    private const IP_CACHE_PREFIX = 'buckaroo_oauth_token_rl_';

    /**
     * @var FormKeyValidator
     */
    private FormKeyValidator $formKeyValidator;

    /**
     * @var CheckoutSession
     */
    private CheckoutSession $checkoutSession;

    /**
     * @var CacheInterface
     */
    private CacheInterface $cache;

    /**
     * @var AccountConfig
     */
    private AccountConfig $accountConfig;

    /**
     * @var Http
     */
    private Http $httpRequest;

    /**
     * @param FormKeyValidator $formKeyValidator
     * @param CheckoutSession  $checkoutSession
     * @param CacheInterface   $cache
     * @param AccountConfig    $accountConfig
     * @param Http             $httpRequest
     */
    public function __construct(
        FormKeyValidator $formKeyValidator,
        CheckoutSession $checkoutSession,
        CacheInterface $cache,
        AccountConfig $accountConfig,
        Http $httpRequest
    ) {
        $this->formKeyValidator = $formKeyValidator;
        $this->checkoutSession  = $checkoutSession;
        $this->cache            = $cache;
        $this->accountConfig    = $accountConfig;
        $this->httpRequest      = $httpRequest;
    }

    /**
     * Check that the request has a valid, session-bound form key.
     *
     * Controllers use this for CsrfAwareActionInterface::validateForCsrf(). Without it,
     * Magento's default CSRF check skips the form key for any request carrying
     * `X-Requested-With: XMLHttpRequest`, and any client can set that header.
     *
     * @param RequestInterface $request
     *
     * @return bool
     */
    public function hasValidFormKey(RequestInterface $request): bool
    {
        return $this->formKeyValidator->validate($request);
    }

    /**
     * Check that this is a genuine storefront checkout request from the owner of the session.
     *
     * @param RequestInterface $request
     *
     * @return bool
     */
    public function isAuthorized(RequestInterface $request): bool
    {
        if (!$request instanceof Http || !$request->isPost()) {
            return false;
        }

        if ($request->getHeader(self::FRONTEND_ORIGIN_HEADER) !== self::FRONTEND_ORIGIN_VALUE) {
            return false;
        }

        if (!$this->hasValidFormKey($request)) {
            return false;
        }

        return (bool) $this->checkoutSession->getQuoteId();
    }

    /**
     * Count one token request against the session and IP budgets.
     *
     * Both budgets are checked before either is counted, and a rejected request is not
     * counted, so a caller that is over the limit cannot keep a shared (IP) budget pinned.
     * Call this only after isAuthorized(), so unauthenticated noise does not use up a real
     * shopper's budget.
     *
     * @param string $scope Separate budget per endpoint, e.g. 'hostedfields' or 'clicktopay'
     *
     * @return bool true when the request may proceed
     */
    public function consumeRateLimit(string $scope): bool
    {
        $window = (int) floor(time() / self::RATE_LIMIT_WINDOW);

        $storage = $this->getSessionStorage();
        $sessionCount = $this->getSessionCount($storage, $scope, $window);
        if ($sessionCount >= self::SESSION_LIMIT) {
            return false;
        }

        $ipLimit = $this->accountConfig->getOauthTokenIpLimit();
        $ipCacheKey = null;
        $ipCount = 0;
        if ($ipLimit > 0) {
            $ipCacheKey = $this->getIpCacheKey($scope, $window);
            $ipCount = (int) $this->cache->load($ipCacheKey);
            if ($ipCount >= $ipLimit) {
                return false;
            }
        }

        $storage[$scope] = ['window' => $window, 'count' => $sessionCount + 1];
        $this->checkoutSession->setBuckarooOauthTokenRateLimit($storage);

        if ($ipCacheKey !== null) {
            // Not atomic: parallel requests from different sessions can overshoot the
            // ceiling slightly. Requests within one session are serialized by session locking.
            $this->cache->save((string) ($ipCount + 1), $ipCacheKey, [], self::RATE_LIMIT_WINDOW);
        }

        return true;
    }

    /**
     * Rate-limit state kept in the checkout session.
     *
     * @return array
     */
    private function getSessionStorage(): array
    {
        $storage = $this->checkoutSession->getBuckarooOauthTokenRateLimit();

        return is_array($storage) ? $storage : [];
    }

    /**
     * Requests already counted for this scope in the current window.
     *
     * @param array  $storage
     * @param string $scope
     * @param int    $window
     *
     * @return int
     */
    private function getSessionCount(array $storage, string $scope, int $window): int
    {
        $entry = $storage[$scope] ?? null;
        if (!is_array($entry) || (int) ($entry['window'] ?? -1) !== $window) {
            return 0;
        }

        return (int) ($entry['count'] ?? 0);
    }

    /**
     * Cache key for the client IP budget of this scope and window.
     *
     * @param string $scope
     * @param int    $window
     *
     * @return string
     */
    private function getIpCacheKey(string $scope, int $window): string
    {
        return self::IP_CACHE_PREFIX . $scope . '_' . $window . '_' . hash('sha256', $this->getClientIp());
    }

    /**
     * Client IP, honouring the merchant's "Client IP Detection Headers" setting.
     *
     * Same resolution as the rest of the module (see ClientIPDataBuilder). Without that
     * setting this is REMOTE_ADDR, which behind a proxy is the proxy's address.
     *
     * @return string
     */
    private function getClientIp(): string
    {
        $headers = [];
        $ipHeaders = (string) $this->accountConfig->getIpHeader();
        if ($ipHeaders !== '') {
            foreach (explode(',', strtoupper($ipHeaders)) as $ipHeader) {
                $ipHeader = trim($ipHeader);
                if ($ipHeader !== '') {
                    $headers[] = 'HTTP_' . str_replace('-', '_', $ipHeader);
                }
            }
        }

        // getRemoteAddress() returns false when no address can be determined; that casts to ''.
        return (string) (new RemoteAddress($this->httpRequest, $headers))->getRemoteAddress();
    }
}
