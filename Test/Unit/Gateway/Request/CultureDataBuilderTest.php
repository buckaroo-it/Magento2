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

namespace Buckaroo\Magento2\Test\Unit\Gateway\Request;

use Buckaroo\Magento2\Gateway\Data\Order\OrderAdapter;
use Buckaroo\Magento2\Gateway\Request\CultureDataBuilder;
use Buckaroo\Magento2\Service\Culture\CultureCodeResolver;
use Magento\Payment\Gateway\Data\PaymentDataObjectInterface;
use Magento\Payment\Model\InfoInterface;
use Magento\Sales\Api\Data\OrderAddressInterface;
use Magento\Sales\Model\Order;
use Magento\Store\Model\Store;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class CultureDataBuilderTest extends TestCase
{
    /**
     * @var MockObject|Order
     */
    private $orderMock;

    /**
     * @var CultureDataBuilder
     */
    private $builder;

    protected function setUp(): void
    {
        $this->orderMock = $this->createMock(Order::class);

        // Real resolver: this test verifies the builder feeds it the right inputs.
        $this->builder = new CultureDataBuilder(new CultureCodeResolver());
    }

    public function testBuildResolvesBelgiumToDutchOnDutchStore(): void
    {
        $result = $this->buildWith('BE', 'nl_NL');

        $this->assertSame(['buckaroo_culture' => 'nl-BE'], $result);
    }

    public function testBuildResolvesBelgiumFrenchStoreToFrench(): void
    {
        $result = $this->buildWith('BE', 'fr_BE');

        $this->assertSame(['buckaroo_culture' => 'fr-BE'], $result);
    }

    public function testBuildWithoutBillingAddressHonoursStoreLocale(): void
    {
        $this->mockStoreLocale('nl_NL');
        $this->orderMock->method('getBillingAddress')->willReturn(null);

        $result = $this->builder->build(['payment' => $this->getPaymentDOMock()]);

        // No billing country: fall back to the (whitelisted) store locale verbatim.
        $this->assertSame(['buckaroo_culture' => 'nl-NL'], $result);
    }

    public function testBuildFallsBackToDefaultWhenNothingUsable(): void
    {
        $this->mockStoreLocale('xx_YY');
        $this->orderMock->method('getBillingAddress')->willReturn(null);

        $result = $this->builder->build(['payment' => $this->getPaymentDOMock()]);

        $this->assertSame(['buckaroo_culture' => CultureCodeResolver::DEFAULT_CULTURE], $result);
    }

    public function testBuildToleratesStoreLocaleFailure(): void
    {
        $store = $this->createMock(Store::class);
        $store->method('getConfig')->willThrowException(new \RuntimeException('no store'));
        $this->orderMock->method('getStore')->willReturn($store);
        $this->mockBillingCountry('BE');

        $result = $this->builder->build(['payment' => $this->getPaymentDOMock()]);

        // Locale hint unavailable -> primary culture for the country.
        $this->assertSame(['buckaroo_culture' => 'nl-BE'], $result);
    }

    /**
     * @param string $countryId
     * @param string $storeLocale
     *
     * @return array
     */
    private function buildWith(string $countryId, string $storeLocale): array
    {
        $this->mockStoreLocale($storeLocale);
        $this->mockBillingCountry($countryId);

        return $this->builder->build(['payment' => $this->getPaymentDOMock()]);
    }

    /**
     * @param string $storeLocale
     *
     * @return void
     */
    private function mockStoreLocale(string $storeLocale): void
    {
        $store = $this->createMock(Store::class);
        $store->method('getConfig')->with('general/locale/code')->willReturn($storeLocale);
        $this->orderMock->method('getStore')->willReturn($store);
    }

    /**
     * @param string $countryId
     *
     * @return void
     */
    private function mockBillingCountry(string $countryId): void
    {
        $billingAddress = $this->createMock(OrderAddressInterface::class);
        $billingAddress->method('getCountryId')->willReturn($countryId);
        $this->orderMock->method('getBillingAddress')->willReturn($billingAddress);
    }

    /**
     * Build a payment data object mock exposing the order.
     *
     * @return MockObject|PaymentDataObjectInterface
     */
    private function getPaymentDOMock()
    {
        $paymentDOMock = $this->createMock(PaymentDataObjectInterface::class);

        $orderAdapter = $this->createMock(OrderAdapter::class);
        $orderAdapter->method('getOrder')->willReturn($this->orderMock);
        $paymentDOMock->method('getOrder')->willReturn($orderAdapter);

        $paymentDOMock->method('getPayment')->willReturn($this->createMock(InfoInterface::class));

        return $paymentDOMock;
    }
}
