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

namespace Buckaroo\Magento2\Service\Push;

use Magento\Framework\Webapi\Request;

/**
 * Works out the URI the gateway called for the current push, from the request itself.
 *
 * Buckaroo signs the body plus the URI it called. Reading that URI back from the request keeps
 * signature verification independent of how the push URL was built (base URLs, rewrites with or
 * without index.php, store codes in the URL, per-website domains).
 *
 * The SDK strips the scheme before hashing and lowercases the rest, so only host, port, path and
 * query matter. Behind a proxy (ngrok, load balancer, CDN) the Host header PHP sees may be the
 * internal one, so the forwarded host and port are offered as well. Every candidate is still
 * checked against the HMAC, so a candidate that is not the called URI simply fails to verify.
 */
class RequestUriResolver
{
    private const DEFAULT_PORTS = [80, 443];

    /**
     * @var Request
     */
    private Request $request;

    /**
     * @param Request $request
     */
    public function __construct(Request $request)
    {
        $this->request = $request;
    }

    /**
     * Get the URIs the current push may have been signed against, most specific first
     *
     * The forwarded host comes first when a proxy reports one, since it is what the gateway
     * called; the host PHP received directly follows. Returns an empty list when the request
     * carries no usable host.
     *
     * @return string[]
     */
    public function getCandidateUris(): array
    {
        $uri = $this->request->getUri();
        $path = (string)$uri->getPath();
        if ($path === '') {
            return [];
        }

        $query = (string)$uri->getQuery();
        $pathAndQuery = ($path[0] === '/' ? '' : '/') . $path . ($query !== '' ? '?' . $query : '');
        $forwardedPort = $this->parsePort($this->firstHeaderValue('X-Forwarded-Port'));

        $authorities = [];

        $forwardedHost = $this->firstHeaderValue('X-Forwarded-Host');
        if ($forwardedHost !== null) {
            [$name, $port] = $this->splitAuthority($forwardedHost);
            $authorities[] = $this->authority($name, $port ?? $forwardedPort);
        }

        $directName = (string)$uri->getHost();
        $directPort = $this->parsePort((string)$uri->getPort());
        $authorities[] = $this->authority($directName, $directPort);
        if ($forwardedPort !== null) {
            // A proxy that keeps the Host header but listens on another port than the backend.
            $authorities[] = $this->authority($directName, $forwardedPort);
        }

        $scheme = $this->request->getScheme();
        $candidates = [];
        foreach ($authorities as $authority) {
            if ($authority !== null) {
                $candidates[] = $scheme . '://' . $authority . $pathAndQuery;
            }
        }

        return array_values(array_unique($candidates));
    }

    /**
     * Join a host name and port, leaving default ports out as the gateway's URL would
     *
     * @param string   $name
     * @param int|null $port
     *
     * @return string|null
     */
    private function authority(string $name, ?int $port): ?string
    {
        if ($name === '') {
            return null;
        }

        if ($port === null || in_array($port, self::DEFAULT_PORTS, true)) {
            return $name;
        }

        return $name . ':' . $port;
    }

    /**
     * Split "host[:port]" (including "[ipv6]:port") into name and port
     *
     * @param string $authority
     *
     * @return array{0: string, 1: int|null}
     */
    private function splitAuthority(string $authority): array
    {
        if (preg_match('/^(\[[0-9a-f:.]+\]|[a-z0-9._-]+)(?::(\d+))?$/', $authority, $matches) !== 1) {
            return ['', null];
        }

        return [$matches[1], isset($matches[2]) ? $this->parsePort($matches[2]) : null];
    }

    /**
     * Parse a port number, rejecting anything that is not one
     *
     * @param string|null $value
     *
     * @return int|null
     */
    private function parsePort(?string $value): ?int
    {
        if ($value === null || !ctype_digit($value)) {
            return null;
        }

        $port = (int)$value;

        return $port > 0 && $port <= 65535 ? $port : null;
    }

    /**
     * Get the first entry of a header that proxies may append to ("client, proxy1, proxy2")
     *
     * The first entry is the one set by the proxy closest to the client, i.e. the value the
     * gateway used.
     *
     * @param string $name
     *
     * @return string|null
     */
    private function firstHeaderValue(string $name): ?string
    {
        $value = $this->request->getHeader($name);
        if (!is_string($value)) {
            return null;
        }

        $first = trim(explode(',', $value)[0]);

        return $first === '' ? null : strtolower($first);
    }
}
