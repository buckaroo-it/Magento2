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

namespace Buckaroo\Magento2\Gateway\Request\BasicParameter;

use Buckaroo\Magento2\Gateway\Helper\SubjectReader;
use Buckaroo\Magento2\Model\ConfigProvider\Account;
use Buckaroo\Magento2\Model\ConfigProvider\Method\Trustly;
use Buckaroo\Resources\Constants\IPProtocolVersion;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\HTTP\PhpEnvironment\RemoteAddress;
use Magento\Payment\Gateway\Request\BuilderInterface;
use Magento\Sales\Model\Order;

class ClientIPDataBuilder implements BuilderInterface
{
    /**
     * @var RequestInterface
     */
    protected $httpRequest;
    /**
     * @var Account
     */
    private $configProviderAccount;

    /**
     * Constructor
     *
     * @param Account          $configProviderAccount
     * @param RequestInterface $httpRequest
     */
    public function __construct(
        Account $configProviderAccount,
        RequestInterface $httpRequest
    ) {
        $this->configProviderAccount = $configProviderAccount;
        $this->httpRequest = $httpRequest;
    }

    /**
     * @inheritdoc
     */
    public function build(array $buildSubject): array
    {
        $paymentDO = SubjectReader::readPayment($buildSubject);
        $order = $paymentDO->getOrder()->getOrder();

        $ip = $this->getIp($order);

        return [
            'clientIP' => [
                'address' => $ip,
                'type'    => !str_contains($ip, ':') ? IPProtocolVersion::IPV4 : IPProtocolVersion::IPV6
            ]
        ];
    }

    /**
     * Get client ip
     *
     * @param Order $order
     *
     * @return string
     */
    public function getIp(Order $order): string
    {
        $ip = $order->getRemoteIp();
        $store = $order->getStore();

        $ipHeaders = $this->configProviderAccount->getIpHeader($store);

        $headers = [];
        if ($ipHeaders) {
            $ipHeaders = explode(',', strtoupper($ipHeaders));
            foreach ($ipHeaders as $ipHeader) {
                $headers[] = 'HTTP_' . str_replace('-', '_', (string)$ipHeader);
            }

            $remoteAddress = new RemoteAddress(
                $this->httpRequest,
                $headers
            );

            $remoteIp = $remoteAddress->getRemoteAddress();
            return $remoteIp ?: '0.0.0.0';
        }

        $ip = $this->getTrustlyIp($order, (string)$ip);

        if (!$ip) {
            $remoteAddress = new RemoteAddress(
                $this->httpRequest,
                $headers
            );

            $ip = $remoteAddress->getRemoteAddress();
        }

        return (string)$ip ?: '0.0.0.0';
    }

    /**
     * Check if it is private IP
     *
     * @param string $ip
     *
     * @return bool
     */
    private function isIpPrivate(string $ip): bool
    {
        if (!$ip) {
            return false;
        }

        $priAddrs = [
            '10.0.0.0|10.255.255.255', // single class A network
            '172.16.0.0|172.31.255.255', // 16 contiguous class B network
            '192.168.0.0|192.168.255.255', // 256 contiguous class C network
            '169.254.0.0|169.254.255.255', // Link-local address also referred to as Automatic Private IP Addressing
            '127.0.0.0|127.255.255.255' // localhost
        ];

        $longIp = ip2long($ip);
        if ($longIp != -1) {
            foreach ($priAddrs as $priAddr) {
                list($start, $end) = explode('|', $priAddr);

                if ($longIp >= ip2long($start) && $longIp <= ip2long($end)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Trustly needs the shopper's public address: behind a proxy, take it from X-Forwarded-For
     *
     * @param Order  $order
     * @param string $ip
     *
     * @return string
     */
    private function getTrustlyIp(Order $order, string $ip): string
    {
        if ($order->getPayment()->getMethod() !== Trustly::CODE
            || !$this->isIpPrivate($ip)
            || !$order->getXForwardedFor()
        ) {
            return $ip;
        }

        return $this->getFirstPublicIp((string)$order->getXForwardedFor()) ?: $ip;
    }

    /**
     * First public address in an X-Forwarded-For value ("client, proxy1, proxy2")
     *
     * @param string $forwardedFor
     *
     * @return string|null
     */
    private function getFirstPublicIp(string $forwardedFor): ?string
    {
        foreach (explode(',', $forwardedFor) as $candidate) {
            $candidate = trim($candidate);
            if (filter_var($candidate, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                return $candidate;
            }
        }

        return null;
    }
}
