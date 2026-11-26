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

namespace Buckaroo\Magento2\Test\Unit\Gateway\Request\BasicParameter;


use PHPUnit\Framework\Attributes\DataProvider;
use Buckaroo\Magento2\Gateway\Request\BasicParameter\ClientIPDataBuilder;
use Buckaroo\Magento2\Model\ConfigProvider\Account;
use Buckaroo\Magento2\Test\Unit\Gateway\Request\AbstractDataBuilderTest;
use Magento\Framework\App\RequestInterface;
use Magento\Sales\Api\Data\OrderPaymentInterface;
use Magento\Sales\Model\Order;
use Magento\Store\Model\Store;
use PHPUnit\Framework\MockObject\MockObject;

class ClientIPDataBuilderTest extends AbstractDataBuilderTest
{
    /**
     * @var MockObject|Account
     */
    private $configProviderAccountMock;

    /**
     * @var MockObject|RequestInterface
     */
    private $httpRequestMock;

    /**
     * @var ClientIPDataBuilder
     */
    private $clientIPDataBuilder;

    protected function setUp(): void
    {
        parent::setUp();

        $this->configProviderAccountMock = $this->createMock(Account::class);
        $this->httpRequestMock = $this->getMockBuilder(RequestInterface::class)
            ->getMock();

        $this->clientIPDataBuilder = new ClientIPDataBuilder(
            $this->configProviderAccountMock,
            $this->httpRequestMock
        );
    }

    /**
     * Without an IP header configured, a non-Trustly order sends the order's remote IP, not the
     * X-Forwarded-For value.
     *
     * @param string $expectedAddress
     * @param int    $expectedType
     */
    #[DataProvider('getBuildDataProvider')]
    public function testBuild(string $expectedAddress, int $expectedType): void
    {
        $this->createOrderMock(
            $expectedAddress,
            'buckaroo_magento2_ideal',
            '203.0.113.50'
        );

        $this->configProviderAccountMock->method('getIpHeader')
            ->willReturn('');

        $result = $this->clientIPDataBuilder->build([
            'payment' => $this->getPaymentDOMock()
        ]);

        $this->assertSame(
            ['clientIP' => ['address' => $expectedAddress, 'type' => $expectedType]],
            $result
        );
    }

    public function testTrustlyOrderWithPrivateRemoteIpUsesTheForwardedIp(): void
    {
        $this->createOrderMock('10.0.0.5', 'buckaroo_magento2_trustly', '203.0.113.7');
        $this->configProviderAccountMock->method('getIpHeader')->willReturn('');

        $result = $this->clientIPDataBuilder->build(['payment' => $this->getPaymentDOMock()]);

        $this->assertSame('203.0.113.7', $result['clientIP']['address']);
    }

    public function testTrustlyOrderBehindTheShopsOwnProxiesUsesThePublicForwardedIp(): void
    {
        $this->createOrderMock('10.0.0.5', 'buckaroo_magento2_trustly', '203.0.113.7, 172.20.1.2');
        $this->configProviderAccountMock->method('getIpHeader')->willReturn('');

        $result = $this->clientIPDataBuilder->build(['payment' => $this->getPaymentDOMock()]);

        $this->assertSame('203.0.113.7', $result['clientIP']['address']);
    }

    public function testTrustlyOrderUsesTheAddressRecordedByTheNearestProxy(): void
    {
        $this->createOrderMock('10.0.0.5', 'buckaroo_magento2_trustly', '198.51.100.9, 203.0.113.7, 10.1.1.2');
        $this->configProviderAccountMock->method('getIpHeader')->willReturn('');

        $result = $this->clientIPDataBuilder->build(['payment' => $this->getPaymentDOMock()]);

        $this->assertSame('203.0.113.7', $result['clientIP']['address']);
    }

    public function testTrustlyOrderWithOnlyPrivateForwardedIpsKeepsTheRemoteIp(): void
    {
        $this->createOrderMock('10.0.0.5', 'buckaroo_magento2_trustly', '10.1.1.1, 172.16.0.2');
        $this->configProviderAccountMock->method('getIpHeader')->willReturn('');

        $result = $this->clientIPDataBuilder->build(['payment' => $this->getPaymentDOMock()]);

        $this->assertSame('10.0.0.5', $result['clientIP']['address']);
    }

    public function testOtherMethodKeepsThePrivateRemoteIp(): void
    {
        $this->createOrderMock('10.0.0.5', 'buckaroo_magento2_ideal', '203.0.113.7');
        $this->configProviderAccountMock->method('getIpHeader')->willReturn('');

        $result = $this->clientIPDataBuilder->build(['payment' => $this->getPaymentDOMock()]);

        $this->assertSame('10.0.0.5', $result['clientIP']['address']);
    }

    public static function getBuildDataProvider(): array
    {
        return [
            'ipv4' => [
                'expectedAddress' => '192.168.1.1',
                'expectedType'    => 0 // IPProtocolVersion::IPV4
            ],
            'ipv6' => [
                'expectedAddress' => '2001:0db8:85a3:0000:0000:8a2e:0370:7334',
                'expectedType'    => 1 // IPProtocolVersion::IPV6
            ],
        ];
    }

    private function createOrderMock(
        string $remoteIp,
        string $paymentMethod,
        ?string $xForwardedFor
    ): Order {
        $store = $this->createMock(Store::class);
        $this->orderMock->method('getRemoteIp')->willReturn($remoteIp);
        $this->orderMock->method('getStore')->willReturn($store);
        $orderPaymentMock = $this->createMock(OrderPaymentInterface::class);
        $orderPaymentMock->method('getMethod')
            ->willReturn($paymentMethod);
        $this->orderMock->method('getPayment')->willReturn($orderPaymentMock);
        $this->orderMock->method('getXForwardedFor')->willReturn($xForwardedFor);

        return $this->orderMock;
    }

    /**
     *
     * @param string $ip
     * @param bool   $expectedResult
     */
    #[DataProvider('isIpPrivateDataProvider')]
    public function testIsIpPrivate(string $ip, bool $expectedResult): void
    {
        $isIpPrivate = $this->invokeIsIpPrivateMethod($this->clientIPDataBuilder, $ip);

        $this->assertSame($expectedResult, $isIpPrivate);
    }

    /**
     * @return array[]
     */
    public static function isIpPrivateDataProvider(): array
    {
        return [
            ['ip' => '192.168.1.1', 'expectedResult' => true],
            ['ip' => '10.0.0.2', 'expectedResult' => true],
            ['ip' => '172.16.1.1', 'expectedResult' => true],
            ['ip' => '169.254.10.10', 'expectedResult' => true],
            ['ip' => '127.0.0.1', 'expectedResult' => true],
            ['ip' => '8.8.8.8', 'expectedResult' => false],
            ['ip' => '2001:0db8:85a3:0000:0000:8a2e:0370:7334', 'expectedResult' => false],
        ];
    }

    /**
     * Invoke the private isIpPrivate method using reflection.
     *
     * @param ClientIPDataBuilder $clientIPDataBuilder
     * @param string              $ip
     *
     * @throws \ReflectionException
     *
     * @return bool
     */
    private function invokeIsIpPrivateMethod(ClientIPDataBuilder $clientIPDataBuilder, string $ip): bool
    {
        $reflection = new \ReflectionClass(ClientIPDataBuilder::class);
        $method = $reflection->getMethod('isIpPrivate');

        return $method->invokeArgs($clientIPDataBuilder, [$ip]);
    }
}
