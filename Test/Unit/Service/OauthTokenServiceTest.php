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

use Buckaroo\Magento2\Logging\BuckarooLoggerInterface;
use Buckaroo\Magento2\Service\OauthTokenService;
use Buckaroo\Magento2\Test\BaseTest;
use Magento\Framework\App\CacheInterface;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Framework\HTTP\Client\Curl;

class OauthTokenServiceTest extends BaseTest
{
    protected $instanceClass = OauthTokenService::class;

    /**
     * A valid cached token must be returned without contacting the auth endpoint.
     */
    public function testGetTokenReturnsCachedTokenWithoutAuthRequest(): void
    {
        $expiresAt = time() + 600;
        $cachedPayload = json_encode(['access_token' => 'cached-token', 'expires_at' => $expiresAt]);

        $encryptor = $this->createMock(EncryptorInterface::class);
        $encryptor->method('decrypt')->with('encrypted-cache')->willReturn($cachedPayload);

        $cache = $this->createMock(CacheInterface::class);
        $cache->expects($this->once())->method('load')->willReturn('encrypted-cache');

        $curl = $this->createMock(Curl::class);
        $curl->expects($this->never())->method('post');

        $instance = $this->getInstance([
            'curl'      => $curl,
            'cache'     => $cache,
            'encryptor' => $encryptor,
            'logger'    => $this->createMock(BuckarooLoggerInterface::class),
        ]);

        $result = $instance->getToken('client-id', 'client-secret', 'clicktopay:save');

        $this->assertSame('cached-token', $result['access_token']);
        $this->assertGreaterThan(0, $result['expires_in']);
        $this->assertLessThanOrEqual(600, $result['expires_in']);
    }

    /**
     * On a cache miss the token is fetched with a timeout set and cached for its
     * lifetime minus the safety margin.
     */
    public function testGetTokenFetchesAndCachesTokenOnCacheMiss(): void
    {
        $encryptor = $this->createMock(EncryptorInterface::class);
        $encryptor->method('encrypt')->willReturn('encrypted-cache-payload');

        $cache = $this->createMock(CacheInterface::class);
        $cache->expects($this->once())->method('load')->willReturn(false);
        $cache->expects($this->once())
            ->method('save')
            ->with('encrypted-cache-payload', $this->callback('is_string'), [], 3540);

        $curl = $this->createMock(Curl::class);
        $curl->expects($this->once())->method('setTimeout');
        $curl->expects($this->once())->method('setCredentials')->with('client-id', 'client-secret');
        $curl->expects($this->once())->method('post');
        $curl->method('getBody')->willReturn(json_encode([
            'access_token' => 'fresh-token',
            'expires_in'   => 3600,
        ]));

        $instance = $this->getInstance([
            'curl'      => $curl,
            'cache'     => $cache,
            'encryptor' => $encryptor,
            'logger'    => $this->createMock(BuckarooLoggerInterface::class),
        ]);

        $result = $instance->getToken('client-id', 'client-secret', 'hostedfields:save');

        $this->assertSame('fresh-token', $result['access_token']);
        $this->assertSame(3540, $result['expires_in']);
    }

    /**
     * An expired cache entry must be ignored and a fresh token fetched.
     */
    public function testGetTokenIgnoresExpiredCachedToken(): void
    {
        $cachedPayload = json_encode(['access_token' => 'stale-token', 'expires_at' => time() - 10]);

        $encryptor = $this->createMock(EncryptorInterface::class);
        $encryptor->method('decrypt')->willReturn($cachedPayload);
        $encryptor->method('encrypt')->willReturn('encrypted-cache-payload');

        $cache = $this->createMock(CacheInterface::class);
        $cache->expects($this->once())->method('load')->willReturn('encrypted-cache');

        $curl = $this->createMock(Curl::class);
        $curl->expects($this->once())->method('post');
        $curl->method('getBody')->willReturn(json_encode([
            'access_token' => 'fresh-token',
            'expires_in'   => 3600,
        ]));

        $instance = $this->getInstance([
            'curl'      => $curl,
            'cache'     => $cache,
            'encryptor' => $encryptor,
            'logger'    => $this->createMock(BuckarooLoggerInterface::class),
        ]);

        $result = $instance->getToken('client-id', 'client-secret', 'clicktopay:save');

        $this->assertSame('fresh-token', $result['access_token']);
    }

    /**
     * A malformed auth response must produce null instead of a broken token array.
     */
    public function testGetTokenReturnsNullOnUnexpectedResponse(): void
    {
        $cache = $this->createMock(CacheInterface::class);
        $cache->method('load')->willReturn(false);
        $cache->expects($this->never())->method('save');

        $curl = $this->createMock(Curl::class);
        $curl->method('getBody')->willReturn('not-json');

        $instance = $this->getInstance([
            'curl'      => $curl,
            'cache'     => $cache,
            'encryptor' => $this->createMock(EncryptorInterface::class),
            'logger'    => $this->createMock(BuckarooLoggerInterface::class),
        ]);

        $this->assertNull($instance->getToken('client-id', 'client-secret', 'clicktopay:save'));
    }

    /**
     * A transport exception must produce null, not an uncaught exception.
     */
    public function testGetTokenReturnsNullOnTransportException(): void
    {
        $cache = $this->createMock(CacheInterface::class);
        $cache->method('load')->willReturn(false);

        $curl = $this->createMock(Curl::class);
        $curl->method('post')->willThrowException(new \Exception('connection timed out'));

        $logger = $this->createMock(BuckarooLoggerInterface::class);
        $logger->expects($this->once())->method('addError');

        $instance = $this->getInstance([
            'curl'      => $curl,
            'cache'     => $cache,
            'encryptor' => $this->createMock(EncryptorInterface::class),
            'logger'    => $logger,
        ]);

        $this->assertNull($instance->getToken('client-id', 'client-secret', 'clicktopay:save'));
    }

    /**
     * With caching disabled the cache must be neither read nor written, and every
     * call must mint a brand new token. Hosted Fields relies on this: the Buckaroo
     * hosted-fields API derives one session from one access token, so a reused
     * token hands the shopper an already-consumed session.
     */
    public function testGetTokenBypassesCacheWhenCachingDisabled(): void
    {
        $cache = $this->createMock(CacheInterface::class);
        $cache->expects($this->never())->method('load');
        $cache->expects($this->never())->method('save');

        $curl = $this->createMock(Curl::class);
        $curl->expects($this->once())->method('post');
        $curl->method('getBody')->willReturn(json_encode([
            'access_token' => 'fresh-token',
            'expires_in'   => 900,
        ]));

        $instance = $this->getInstance([
            'curl'      => $curl,
            'cache'     => $cache,
            'encryptor' => $this->createMock(EncryptorInterface::class),
            'logger'    => $this->createMock(BuckarooLoggerInterface::class),
        ]);

        $result = $instance->getToken('client-id', 'client-secret', 'hostedfields:save', false);

        $this->assertSame('fresh-token', $result['access_token']);
        $this->assertSame(840, $result['expires_in']);
    }

    /**
     * Different credential/scope combinations must not share a cache entry.
     */
    public function testGetTokenUsesScopeSpecificCacheKeys(): void
    {
        $capturedKeys = [];

        $cache = $this->createMock(CacheInterface::class);
        $cache->method('load')->willReturnCallback(function ($key) use (&$capturedKeys) {
            $capturedKeys[] = $key;
            return false;
        });

        $curl = $this->createMock(Curl::class);
        $curl->method('getBody')->willReturn(json_encode([
            'access_token' => 'fresh-token',
            'expires_in'   => 3600,
        ]));

        $encryptor = $this->createMock(EncryptorInterface::class);
        $encryptor->method('encrypt')->willReturn('encrypted-cache-payload');

        $instance = $this->getInstance([
            'curl'      => $curl,
            'cache'     => $cache,
            'encryptor' => $encryptor,
            'logger'    => $this->createMock(BuckarooLoggerInterface::class),
        ]);

        $instance->getToken('client-id', 'client-secret', 'clicktopay:save');
        $instance->getToken('client-id', 'client-secret', 'hostedfields:save');

        $this->assertCount(2, $capturedKeys);
        $this->assertNotSame($capturedKeys[0], $capturedKeys[1]);
    }
}
