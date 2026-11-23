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

namespace Buckaroo\Magento2\Test\Unit\Service\Push;

use Buckaroo\Magento2\Service\Push\RequestUriResolver;
use Laminas\Uri\Http as HttpUri;
use Magento\Framework\Webapi\Request;
use PHPUnit\Framework\TestCase;

class RequestUriResolverTest extends TestCase
{
    /**
     * @param string               $uri     URI as PHP saw it (Host header + REQUEST_URI)
     * @param array<string,string> $headers
     * @param string               $scheme
     *
     * @return string[]
     */
    private function resolve(string $uri, array $headers = [], string $scheme = 'https'): array
    {
        $request = $this->createMock(Request::class);
        $request->method('getUri')->willReturn(new HttpUri($uri));
        $request->method('getScheme')->willReturn($scheme);
        $request->method('getHeader')->willReturnCallback(
            fn(string $name) => $headers[$name] ?? false
        );

        return (new RequestUriResolver($request))->getCandidateUris();
    }

    public function testDefaultStoreStorelessUrl(): void
    {
        $this->assertSame(
            ['https://shop.example/rest/V1/buckaroo/push'],
            $this->resolve('https://shop.example/rest/V1/buckaroo/push')
        );
    }

    public function testStoreCodeInPathIsKeptAsCalled(): void
    {
        $this->assertSame(
            ['https://shop.example/rest/second_store/V1/buckaroo/push'],
            $this->resolve('https://shop.example/rest/second_store/V1/buckaroo/push')
        );
    }

    /**
     * The drift that rejected 98 pushes a day: index.php in the configured URL with rewrites on.
     * Whatever the gateway called is what REQUEST_URI holds, so it must come through untouched.
     */
    public function testIndexPhpInTheCalledUrlIsKept(): void
    {
        $this->assertSame(
            ['https://shop.example/index.php/rest/default/V1/buckaroo/push'],
            $this->resolve('https://shop.example/index.php/rest/default/V1/buckaroo/push')
        );
    }

    public function testWebsiteOnItsOwnDomainUsesTheHostThatWasCalled(): void
    {
        $this->assertSame(
            ['https://second-website.example/rest/V1/buckaroo/push'],
            $this->resolve('https://second-website.example/rest/V1/buckaroo/push')
        );
    }

    public function testQueryStringIsPartOfTheUri(): void
    {
        $this->assertSame(
            ['https://shop.example/rest/V1/buckaroo/push?foo=bar'],
            $this->resolve('https://shop.example/rest/V1/buckaroo/push?foo=bar')
        );
    }

    public function testNonStandardPortIsKeptAndDefaultPortsAreDropped(): void
    {
        $this->assertSame(
            ['https://shop.example:8443/rest/V1/buckaroo/push'],
            $this->resolve('https://shop.example:8443/rest/V1/buckaroo/push')
        );
        $this->assertSame(
            ['https://shop.example/rest/V1/buckaroo/push'],
            $this->resolve('https://shop.example:443/rest/V1/buckaroo/push')
        );
        $this->assertSame(
            ['http://shop.example/rest/V1/buckaroo/push'],
            $this->resolve('http://shop.example:80/rest/V1/buckaroo/push', [], 'http')
        );
    }

    /**
     * ngrok with --host-header=rewrite (and most load balancers): PHP sees the internal host,
     * the gateway called the public one.
     */
    public function testForwardedHostComesFirstBehindAProxy(): void
    {
        $this->assertSame(
            [
                'https://abc123.ngrok-free.app/rest/V1/buckaroo/push',
                'https://localhost:8080/rest/V1/buckaroo/push',
            ],
            $this->resolve(
                'http://localhost:8080/rest/V1/buckaroo/push',
                ['X-Forwarded-Host' => 'abc123.ngrok-free.app', 'X-Forwarded-Proto' => 'https'],
                'https'
            )
        );
    }

    public function testFirstEntryOfAChainedForwardedHostIsUsed(): void
    {
        $this->assertSame(
            'https://public.example/rest/V1/buckaroo/push',
            $this->resolve(
                'https://internal.lan/rest/V1/buckaroo/push',
                ['X-Forwarded-Host' => 'Public.Example, cdn-edge.internal']
            )[0]
        );
    }

    public function testForwardedHostKeepsItsOwnPort(): void
    {
        $this->assertSame(
            'https://public.example:8443/rest/V1/buckaroo/push',
            $this->resolve(
                'http://backend:8080/rest/V1/buckaroo/push',
                ['X-Forwarded-Host' => 'public.example:8443', 'X-Forwarded-Port' => '9999']
            )[0]
        );
    }

    public function testForwardedPortAppliesToAForwardedHostWithoutPort(): void
    {
        $this->assertSame(
            'https://public.example:8443/rest/V1/buckaroo/push',
            $this->resolve(
                'http://backend:8080/rest/V1/buckaroo/push',
                ['X-Forwarded-Host' => 'public.example', 'X-Forwarded-Port' => '8443']
            )[0]
        );
    }

    /**
     * A proxy that terminates TLS on 443 and passes the original Host plus its backend port.
     */
    public function testForwardedPortAppliesToTheDirectHostWhenNoForwardedHost(): void
    {
        $this->assertSame(
            [
                'https://shop.example:8080/rest/V1/buckaroo/push',
                'https://shop.example/rest/V1/buckaroo/push',
            ],
            $this->resolve('http://shop.example:8080/rest/V1/buckaroo/push', ['X-Forwarded-Port' => '443'])
        );
    }

    public function testGarbageForwardedHeadersAreIgnored(): void
    {
        $this->assertSame(
            ['https://shop.example/rest/V1/buckaroo/push'],
            $this->resolve(
                'https://shop.example/rest/V1/buckaroo/push',
                ['X-Forwarded-Host' => 'evil.example/path', 'X-Forwarded-Port' => 'abc']
            )
        );
    }

    public function testNoHostGivesNoCandidates(): void
    {
        $this->assertSame([], $this->resolve('/rest/V1/buckaroo/push'));
    }
}
