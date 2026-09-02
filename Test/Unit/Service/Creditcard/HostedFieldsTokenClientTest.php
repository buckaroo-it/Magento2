<?php
declare(strict_types=1);

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

namespace Buckaroo\Magento2\Test\Unit\Service\Creditcard;

use Buckaroo\Magento2\Service\Creditcard\HostedFieldsTokenClient;
use Buckaroo\Magento2\Service\OauthTokenService;
use Buckaroo\Magento2\Test\BaseTest;

class HostedFieldsTokenClientTest extends BaseTest
{
    protected $instanceClass = HostedFieldsTokenClient::class;

    /**
     * Every Hosted Fields checkout must receive its own access token.
     *
     * The Buckaroo hosted-fields API derives exactly one session per access token
     * (POST /v1/sessions returns the same session id for the same bearer), so a
     * shared or cached token means a shared - and after the first payment,
     * consumed - card entry session.
     */
    public function testFetchTokenRequestsAnUncachedTokenForTheHostedFieldsScope(): void
    {
        $tokenService = $this->createMock(OauthTokenService::class);
        $tokenService->expects($this->once())
            ->method('getToken')
            ->with('client-id', 'client-secret', 'hostedfields:save', false)
            ->willReturn(['access_token' => 'fresh-token', 'expires_in' => 840]);

        $instance = $this->getInstance(['tokenService' => $tokenService]);

        $this->assertSame(
            ['access_token' => 'fresh-token', 'expires_in' => 840],
            $instance->fetchToken('client-id', 'client-secret')
        );
    }

    /**
     * A failed token request must surface as null so the controller can return an
     * error payload instead of a half-built response.
     */
    public function testFetchTokenReturnsNullWhenTheTokenCannotBeObtained(): void
    {
        $tokenService = $this->createMock(OauthTokenService::class);
        $tokenService->method('getToken')->willReturn(null);

        $instance = $this->getInstance(['tokenService' => $tokenService]);

        $this->assertNull($instance->fetchToken('client-id', 'client-secret'));
    }
}
