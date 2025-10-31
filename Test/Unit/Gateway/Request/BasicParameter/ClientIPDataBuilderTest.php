<?php
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
declare(strict_types=1);

namespace Buckaroo\Magento2\Test\Unit\Gateway\Request\BasicParameter;

use Buckaroo\Magento2\Gateway\Request\BasicParameter\ClientIPDataBuilder;
use Buckaroo\Magento2\Model\ConfigProvider\Account;
use Buckaroo\Magento2\Test\Unit\Gateway\Request\AbstractDataBuilderTest;
use Buckaroo\Resources\Constants\IPProtocolVersion;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\HTTP\PhpEnvironment\RemoteAddress;
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
     * @dataProvider getBuildDataProvider
     *
     * @param string $expectedAddress
     * @param int    $expectedType
     */
    public function testBuild(string $expectedAddress, int $expectedType): void
    {
        $this->createOrderMock(
            $expectedAddress,
            'buckaroo_magento2_ideal',
            $expectedAddress
        );

        $this->configProviderAccountMock->method('getIpHeader')
            ->willReturn('');

        $remoteAddressMock = $this->createMock(RemoteAddress::class);
        $remoteAddressMock->method('getRemoteAddress')
            ->willReturn($expectedAddress);

        $result = $this->clientIPDataBuilder->build([
            'payment' => $this->getPaymentDOMock()
        ]);

        $this->assertSame($expectedAddress, $result['clientIP']['address']);
        $this->assertSame($expectedType, $result['clientIP']['type']);
    }

    public static function getBuildDataProvider(): array
    {
        return [
            [
                'expectedAddress' => '192.168.1.1',
                'expectedType'    => IPProtocolVersion::IPV4
            ],
            [
                'expectedAddress' => '2001:0db8:85a3:0000:0000:8a2e:0370:7334',
                'expectedType'    => IPProtocolVersion::IPV6
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
        $orderPaymentMock = $this->getMockForAbstractClass(
            OrderPaymentInterface::class,
            [],
            '',
            false,
            false,
            true,
            []
        );
        $orderPaymentMock->method('getMethod')
            ->willReturn($paymentMethod);
        $this->orderMock->method('getPayment')->willReturn($orderPaymentMock);
        $this->orderMock->method('getXForwardedFor')->willReturn($xForwardedFor);

        return $this->orderMock;
    }

    /**
     * @dataProvider isIpPrivateDataProvider
     *
     * @param string $ip
     * @param bool   $expectedResult
     */
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
        $method->setAccessible(true);

        return $method->invokeArgs($clientIPDataBuilder, [$ip]);
    }
}
