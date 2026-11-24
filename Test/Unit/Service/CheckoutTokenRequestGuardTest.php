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

namespace Buckaroo\Magento2\Test\Unit\Service;

use Buckaroo\Magento2\Model\ConfigProvider\Account as AccountConfig;
use Buckaroo\Magento2\Service\CheckoutTokenRequestGuard;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\App\CacheInterface;
use Magento\Framework\App\Request\Http;
use Magento\Framework\Data\Form\FormKey\Validator as FormKeyValidator;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class CheckoutTokenRequestGuardTest extends TestCase
{
    /**
     * @var FormKeyValidator|MockObject
     */
    private $formKeyValidator;

    /**
     * @var CheckoutSession|MockObject
     */
    private $checkoutSession;

    /**
     * @var array<string, mixed> backing store for the checkout session mock
     */
    private array $sessionData = [];

    /**
     * @var array<string, string> backing store for the cache mock
     */
    private array $cacheData = [];

    /**
     * @var array<string, string> $_SERVER values seen by the client IP resolution
     */
    private array $server = ['REMOTE_ADDR' => '203.0.113.10'];

    private int $ipLimit = 200;

    private string $ipHeader = '';

    protected function setUp(): void
    {
        $this->formKeyValidator = $this->createMock(FormKeyValidator::class);

        $this->checkoutSession = $this->getMockBuilder(CheckoutSession::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getQuoteId', '__call'])
            ->getMock();
        // Session data accessors are magic (__call -> storage); back them with an array.
        $this->checkoutSession->method('__call')->willReturnCallback(function ($method, $args) {
            $key = substr($method, 3);
            if (strpos($method, 'set') === 0) {
                $this->sessionData[$key] = $args[0] ?? null;
                return $this->checkoutSession;
            }
            return $this->sessionData[$key] ?? null;
        });
    }

    public function testAuthorizesPostWithHeaderFormKeyAndQuote(): void
    {
        $this->formKeyValidator->method('validate')->willReturn(true);
        $this->checkoutSession->method('getQuoteId')->willReturn(42);

        $this->assertTrue($this->createGuard()->isAuthorized($this->createRequest()));
    }

    /**
     * The original vulnerability: header plus quote, but no valid form key.
     */
    public function testRejectsForgedHeaderWithoutValidFormKey(): void
    {
        $this->formKeyValidator->method('validate')->willReturn(false);
        $this->checkoutSession->method('getQuoteId')->willReturn(42);

        $this->assertFalse($this->createGuard()->isAuthorized($this->createRequest()));
    }

    public function testRejectsGetRequest(): void
    {
        $this->formKeyValidator->method('validate')->willReturn(true);
        $this->checkoutSession->method('getQuoteId')->willReturn(42);

        $this->assertFalse($this->createGuard()->isAuthorized($this->createRequest(false)));
    }

    public function testRejectsMissingOriginHeader(): void
    {
        $this->formKeyValidator->method('validate')->willReturn(true);
        $this->checkoutSession->method('getQuoteId')->willReturn(42);

        $this->assertFalse($this->createGuard()->isAuthorized($this->createRequest(true, null)));
    }

    public function testRejectsSessionWithoutQuote(): void
    {
        $this->formKeyValidator->method('validate')->willReturn(true);
        $this->checkoutSession->method('getQuoteId')->willReturn(null);

        $this->assertFalse($this->createGuard()->isAuthorized($this->createRequest()));
    }

    public function testSessionBudgetIsEnforcedPerScope(): void
    {
        $guard = $this->createGuard();

        for ($i = 0; $i < CheckoutTokenRequestGuard::SESSION_LIMIT; $i++) {
            $this->assertTrue($guard->consumeRateLimit('hostedfields'), 'request ' . ($i + 1));
        }
        $this->assertFalse($guard->consumeRateLimit('hostedfields'));

        $this->assertTrue($guard->consumeRateLimit('clicktopay'));
    }

    /**
     * A request rejected by the session budget must not use up the shared IP budget.
     */
    public function testSessionRejectionDoesNotConsumeIpBudget(): void
    {
        $guard = $this->createGuard();

        for ($i = 0; $i < CheckoutTokenRequestGuard::SESSION_LIMIT + 5; $i++) {
            $guard->consumeRateLimit('hostedfields');
        }

        $this->assertSame([(string) CheckoutTokenRequestGuard::SESSION_LIMIT], array_values($this->cacheData));
    }

    /**
     * Fresh sessions from one IP cannot get past the configured IP ceiling, and requests
     * over the ceiling are not counted.
     */
    public function testIpBudgetSpansSessionsAndIgnoresRejectedRequests(): void
    {
        $this->ipLimit = 3;
        $guard = $this->createGuard();

        for ($i = 0; $i < 3; $i++) {
            $this->sessionData = [];
            $this->assertTrue($guard->consumeRateLimit('hostedfields'), 'request ' . ($i + 1));
        }

        for ($i = 0; $i < 5; $i++) {
            $this->sessionData = [];
            $this->assertFalse($guard->consumeRateLimit('hostedfields'));
        }
        $this->assertSame(['3'], array_values($this->cacheData));
        $this->assertSame([], $this->sessionData, 'a rejected request must not count against the session');

        $this->server['REMOTE_ADDR'] = '198.51.100.7';
        $this->assertTrue($guard->consumeRateLimit('hostedfields'));
    }

    public function testIpLimitZeroDisablesIpBudget(): void
    {
        $this->ipLimit = 0;
        $guard = $this->createGuard();

        for ($i = 0; $i < 50; $i++) {
            $this->sessionData = [];
            $this->assertTrue($guard->consumeRateLimit('hostedfields'));
        }
        $this->assertSame([], $this->cacheData);
    }

    /**
     * Behind a proxy every request has the proxy's REMOTE_ADDR. With the merchant's
     * "Client IP Detection Headers" set, shoppers get separate budgets.
     */
    public function testUsesConfiguredClientIpHeaders(): void
    {
        $this->ipLimit = 1;
        $this->ipHeader = 'X-Forwarded-For';
        $this->server = ['REMOTE_ADDR' => '10.0.0.1', 'HTTP_X_FORWARDED_FOR' => '203.0.113.10'];
        $guard = $this->createGuard();

        $this->assertTrue($guard->consumeRateLimit('hostedfields'));
        $this->sessionData = [];
        $this->assertFalse($guard->consumeRateLimit('hostedfields'));

        $this->sessionData = [];
        $this->server['HTTP_X_FORWARDED_FOR'] = '198.51.100.7';
        $this->assertTrue($guard->consumeRateLimit('hostedfields'));
    }

    private function createGuard(): CheckoutTokenRequestGuard
    {
        $cache = $this->createMock(CacheInterface::class);
        $cache->method('load')->willReturnCallback(function ($key) {
            return $this->cacheData[$key] ?? false;
        });
        $cache->method('save')->willReturnCallback(function ($data, $key) {
            $this->cacheData[$key] = $data;
            return true;
        });

        $accountConfig = $this->createMock(AccountConfig::class);
        $accountConfig->method('getOauthTokenIpLimit')->willReturnCallback(function () {
            return $this->ipLimit;
        });
        $accountConfig->method('getIpHeader')->willReturnCallback(function () {
            return $this->ipHeader;
        });

        $httpRequest = $this->createMock(Http::class);
        $httpRequest->method('getServer')->willReturnCallback(function ($name = null, $default = null) {
            return $this->server[$name] ?? $default;
        });

        return new CheckoutTokenRequestGuard(
            $this->formKeyValidator,
            $this->checkoutSession,
            $cache,
            $accountConfig,
            $httpRequest
        );
    }

    /**
     * @param bool        $isPost
     * @param string|null $originHeader
     *
     * @return Http|MockObject
     */
    private function createRequest(bool $isPost = true, ?string $originHeader = 'MagentoFrontend')
    {
        $request = $this->createMock(Http::class);
        $request->method('isPost')->willReturn($isPost);
        $request->method('getHeader')->willReturnCallback(function ($name) use ($originHeader) {
            return $name === 'X-Requested-From' ? ($originHeader ?? false) : false;
        });

        return $request;
    }
}
