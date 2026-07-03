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

namespace Buckaroo\Magento2\Service\Creditcard;

use Buckaroo\Magento2\Service\OauthTokenService;

/**
 * Fetches OAuth access tokens for Hosted Fields (credit card) from Buckaroo's auth API.
 *
 * Thin adapter around the shared OauthTokenService, which adds server-side
 * (encrypted) token caching and a request timeout. Kept as a separate class so
 * existing integrations against this contract keep working.
 */
class HostedFieldsTokenClient
{
    private const SCOPE = 'hostedfields:save';

    /**
     * @var OauthTokenService
     */
    private OauthTokenService $tokenService;

    /**
     * @param OauthTokenService $tokenService
     */
    public function __construct(OauthTokenService $tokenService)
    {
        $this->tokenService = $tokenService;
    }

    /**
     * Fetch an OAuth access token using the client-credentials grant.
     *
     * Served from the shared server-side cache when a still-valid token exists;
     * expires_in is the remaining lifetime, so the frontend refresh scheduling
     * keeps working with cached tokens.
     *
     * @param string $clientId
     * @param string $clientSecret
     *
     * @return array{access_token: string, expires_in: int}|null null when the token could not be obtained
     */
    public function fetchToken(string $clientId, string $clientSecret): ?array
    {
        return $this->tokenService->getToken($clientId, $clientSecret, self::SCOPE);
    }
}
