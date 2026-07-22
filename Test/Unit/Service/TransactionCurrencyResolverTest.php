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

namespace Buckaroo\Magento2\Test\Unit\Service;


use PHPUnit\Framework\Attributes\DataProvider;
use Buckaroo\Magento2\Exception;
use Buckaroo\Magento2\Model\ConfigProvider\Factory;
use Buckaroo\Magento2\Model\ConfigProvider\Method\AbstractConfigProvider;
use Buckaroo\Magento2\Service\TransactionCurrencyResolver;
use Magento\Payment\Model\MethodInterface;
use Magento\Sales\Model\Order;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class TransactionCurrencyResolverTest extends TestCase
{
    /**
     * @var Factory|MockObject
     */
    private $configProviderMethodFactoryMock;

    /**
     * @var MethodInterface|MockObject
     */
    private $methodInstanceMock;

    /**
     * @var Order|MockObject
     */
    private $orderMock;

    /**
     * @var TransactionCurrencyResolver
     */
    private $resolver;

    protected function setUp(): void
    {
        $this->configProviderMethodFactoryMock = $this->createMock(Factory::class);
        $this->methodInstanceMock = $this->createMock(MethodInterface::class);
        $this->orderMock = $this->createMock(Order::class);

        $this->resolver = new TransactionCurrencyResolver($this->configProviderMethodFactoryMock);
    }

    /**
     *
     * @param string      $orderCurrency
     * @param array       $allowedCurrencies
     * @param string|null $expectedCurrency
     *
     * @throws Exception
     */
    #[DataProvider('resolveDataProvider')]
    public function testResolve(
        string $orderCurrency,
        array $allowedCurrencies,
        ?string $expectedCurrency
    ): void {
        $this->orderMock->method('getOrderCurrencyCode')->willReturn($orderCurrency);

        $this->mockAllowedCurrencies($allowedCurrencies);

        $this->assertSame(
            $expectedCurrency,
            $this->resolver->resolve($this->orderMock, $this->methodInstanceMock)
        );
    }

    public static function resolveDataProvider(): array
    {
        $klarnaCurrencies = ['EUR', 'GBP', 'DKK', 'SEK', 'NOK', 'CHF', 'PLN'];

        return [
            'PLN order currency supported by Klarna'      => ['PLN', $klarnaCurrencies, 'PLN'],
            'SEK order currency supported by Klarna'      => ['SEK', $klarnaCurrencies, 'SEK'],
            'unsupported order currency resolves to null' => ['CZK', $klarnaCurrencies, null],
            'empty allowed currencies resolves to null'   => ['EUR', [], null],
        ];
    }

    /**
     * @throws Exception
     */
    public function testIsCurrencyAllowed(): void
    {
        $this->mockAllowedCurrencies(['EUR', 'PLN']);

        $this->assertTrue($this->resolver->isCurrencyAllowed('PLN', $this->methodInstanceMock));
    }

    /**
     * @throws Exception
     */
    public function testIsCurrencyAllowedRejectsUnsupportedCurrency(): void
    {
        $this->mockAllowedCurrencies(['EUR', 'PLN']);

        $this->assertFalse($this->resolver->isCurrencyAllowed('CZK', $this->methodInstanceMock));
    }

    /**
     * @throws Exception
     */
    public function testIsCurrencyAllowedRejectsNullCurrency(): void
    {
        $this->assertFalse($this->resolver->isCurrencyAllowed(null, $this->methodInstanceMock));
    }

    /**
     * @throws Exception
     */
    public function testResolveThrowsWhenMethodCodeMissing(): void
    {
        $this->methodInstanceMock->method('getCode')->willReturn('');
        $this->orderMock->method('getOrderCurrencyCode')->willReturn('EUR');

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('The payment method code it is not set.');

        $this->resolver->resolve($this->orderMock, $this->methodInstanceMock);
    }

    /**
     * Mock the config provider chain to return the given allowed currencies.
     *
     * @param array $allowedCurrencies
     */
    private function mockAllowedCurrencies(array $allowedCurrencies): void
    {
        $this->methodInstanceMock->method('getCode')->willReturn('buckaroo_magento2_klarna');

        $configProviderMock = $this->getMockBuilder(AbstractConfigProvider::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getAllowedCurrencies'])
            ->getMock();
        $configProviderMock->method('getAllowedCurrencies')->willReturn($allowedCurrencies);

        $this->configProviderMethodFactoryMock->method('get')
            ->with('buckaroo_magento2_klarna')
            ->willReturn($configProviderMock);
    }
}
