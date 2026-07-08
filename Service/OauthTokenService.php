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

declare(strict_types=1);

namespace Buckaroo\Magento2\Service;

use Buckaroo\Magento2\Logging\BuckarooLoggerInterface;
use Magento\Framework\App\CacheInterface;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Framework\HTTP\Client\Curl;

/**
 * Fetches OAuth client-credentials access tokens from Buckaroo's auth API with a
 * server-side encrypted cache.
 *
 * Tokens are merchant-level (not bound to a shopper), so one cached token per
 * credential pair and scope can serve every checkout visitor until it expires.
 * Used by both Click to Pay and the Hosted Fields (credit cards) token proxies.
 */
class OauthTokenService
{
    private const AUTH_ENDPOINT = 'https://auth.buckaroo.io/oauth/token';

    /**
     * The margin keeps a safety window so a token handed to the browser never
     * expires mid-flow.
     */
    private const TOKEN_CACHE_ID      = 'buckaroo_oauth_access_token';
    private const TOKEN_EXPIRY_MARGIN = 60;

    /**
     * Magento's Curl client defaults to a 300s timeout; a hung auth endpoint would
     * hold the checkout XHR and a PHP-FPM worker for that long on every cache miss.
     */
    private const REQUEST_TIMEOUT = 10;

    /**
     * @var Curl
     */
    private Curl $curl;

    /**
     * @var CacheInterface
     */
    private CacheInterface $cache;

    /**
     * @var EncryptorInterface
     */
    private EncryptorInterface $encryptor;

    /**
     * @var BuckarooLoggerInterface
     */
    private BuckarooLoggerInterface $logger;

    /**
     * @param Curl                    $curl
     * @param CacheInterface          $cache
     * @param EncryptorInterface      $encryptor
     * @param BuckarooLoggerInterface $logger
     */
    public function __construct(
        Curl $curl,
        CacheInterface $cache,
        EncryptorInterface $encryptor,
        BuckarooLoggerInterface $logger
    ) {
        $this->curl      = $curl;
        $this->cache     = $cache;
        $this->encryptor = $encryptor;
        $this->logger    = $logger;
    }

    /**
     * Get an access token for the given credentials and scope.
     *
     * Served from cache when a still-valid token exists, otherwise freshly fetched and cached.
     * The returned expires_in is the remaining lifetime (margin already applied),
     * so callers can hand it straight to the browser.
     *
     * @param string $clientId
     * @param string $clientSecret
     * @param string $scope
     *
     * @return array{access_token: string, expires_in: int}|null null when the token could not be obtained
     */
    public function getToken(string $clientId, string $clientSecret, string $scope): ?array
    {
        $cacheKey = self::TOKEN_CACHE_ID . '_' . hash('sha256', $clientId . ':' . $clientSecret . ':' . $scope);

        $cachedToken = $this->loadCachedToken($cacheKey);
        if ($cachedToken !== null) {
            return $cachedToken;
        }

        $data = $this->requestToken($clientId, $clientSecret, $scope);
        if ($data === null) {
            return null;
        }

        $expiresIn = (int) ($data['expires_in'] ?? 0);
        $this->saveCachedToken($cacheKey, (string) $data['access_token'], $expiresIn);

        return [
            'access_token' => (string) $data['access_token'],
            'expires_in'   => max($expiresIn - self::TOKEN_EXPIRY_MARGIN, 0),
        ];
    }

    /**
     * Request a fresh token from the auth endpoint.
     *
     * @param string $clientId
     * @param string $clientSecret
     * @param string $scope
     *
     * @return array|null decoded response containing access_token, or null on failure
     */
    private function requestToken(string $clientId, string $clientSecret, string $scope): ?array
    {
        try {
            $this->curl->setTimeout(self::REQUEST_TIMEOUT);
            $this->curl->setCredentials($clientId, $clientSecret);
            $this->curl->addHeader('Content-Type', 'application/x-www-form-urlencoded');

            $this->curl->post(
                self::AUTH_ENDPOINT,
                http_build_query([
                    'grant_type' => 'client_credentials',
                    'scope'      => $scope,
                ])
            );

            $body = $this->curl->getBody();
            $data = json_decode($body, true);

            if (!is_array($data) || !isset($data['access_token'])) {
                $this->logger->addError('[Buckaroo OAuth] Unexpected token response', ['body' => $body]);
                return null;
            }

            return $data;
        } catch (\Exception $e) {
            $this->logger->addError('[Buckaroo OAuth] Token request failed: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Load a still-valid access token from cache, or null when absent/expired.
     *
     * @param string $cacheKey
     *
     * @return array{access_token: string, expires_in: int}|null
     */
    private function loadCachedToken(string $cacheKey): ?array
    {
        $cached = $this->cache->load($cacheKey);
        if ($cached === false || $cached === '') {
            return null;
        }

        try {
            $data = json_decode((string) $this->encryptor->decrypt($cached), true);
        } catch (\Exception $e) {
            $this->logger->addError('[Buckaroo OAuth] Failed to decrypt cached token: ' . $e->getMessage());
            return null;
        }

        if (!is_array($data) || !isset($data['access_token'], $data['expires_at'])) {
            return null;
        }

        $remaining = (int) $data['expires_at'] - time();
        if ($remaining <= 0) {
            return null;
        }

        return [
            'access_token' => (string) $data['access_token'],
            'expires_in'   => $remaining,
        ];
    }

    /**
     * Cache the access token (encrypted) for its lifetime minus a safety margin.
     *
     * @param string $cacheKey
     * @param string $accessToken
     * @param int    $expiresIn
     *
     * @return void
     */
    private function saveCachedToken(string $cacheKey, string $accessToken, int $expiresIn): void
    {
        $lifetime = $expiresIn - self::TOKEN_EXPIRY_MARGIN;
        if ($lifetime <= 0) {
            return;
        }

        try {
            $payload = $this->encryptor->encrypt(json_encode([
                'access_token' => $accessToken,
                'expires_at'   => time() + $lifetime,
            ]));

            $this->cache->save($payload, $cacheKey, [], $lifetime);
        } catch (\Exception $e) {
            // Caching is an optimization; a failure here must never break the token response.
            $this->logger->addError('[Buckaroo OAuth] Failed to cache token: ' . $e->getMessage());
        }
    }
}
