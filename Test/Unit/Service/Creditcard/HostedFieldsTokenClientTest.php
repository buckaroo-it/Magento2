<?php
declare(strict_types=1);

/**
 * NOTICE OF LICENSE
 *
 * This source file is subject to the MIT License
 * It is available through the world-wide-web at this URL:
 * https://tldrlegal.com/license/mit-license
 * If you are unable to obtain it through the world-wide-web, please email
 * to support@buckaroo.nl, so we can send you a copy immediately.
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
