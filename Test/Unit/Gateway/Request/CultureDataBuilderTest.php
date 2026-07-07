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

use Buckaroo\Magento2\Gateway\Request\CultureDataBuilder;
use Buckaroo\Magento2\Service\Culture\CultureCodeResolver;
use Magento\Sales\Api\Data\OrderAddressInterface;
use Magento\Store\Model\Store;

class CultureDataBuilderTest extends AbstractDataBuilderTest
{
    /**
     * @var CultureDataBuilder
     */
    private $builder;

    protected function setUp(): void
    {
        parent::setUp();

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
        $store = $this->createMock(Store::class);
        $store->method('getConfig')->with('general/locale/code')->willReturn('nl_NL');
        $this->orderMock->method('getStore')->willReturn($store);
        $this->orderMock->method('getBillingAddress')->willReturn(null);

        $result = $this->builder->build(['payment' => $this->getPaymentDOMock()]);

        // No billing country: fall back to the (whitelisted) store locale verbatim.
        $this->assertSame(['buckaroo_culture' => 'nl-NL'], $result);
    }

    public function testBuildFallsBackToDefaultWhenNothingUsable(): void
    {
        $store = $this->createMock(Store::class);
        $store->method('getConfig')->with('general/locale/code')->willReturn('xx_YY');
        $this->orderMock->method('getStore')->willReturn($store);
        $this->orderMock->method('getBillingAddress')->willReturn(null);

        $result = $this->builder->build(['payment' => $this->getPaymentDOMock()]);

        $this->assertSame(['buckaroo_culture' => CultureCodeResolver::DEFAULT_CULTURE], $result);
    }

    public function testBuildToleratesStoreLocaleFailure(): void
    {
        $store = $this->createMock(Store::class);
        $store->method('getConfig')->willThrowException(new \RuntimeException('no store'));
        $this->orderMock->method('getStore')->willReturn($store);

        $billingAddress = $this->createMock(OrderAddressInterface::class);
        $billingAddress->method('getCountryId')->willReturn('BE');
        $this->orderMock->method('getBillingAddress')->willReturn($billingAddress);

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
        $store = $this->createMock(Store::class);
        $store->method('getConfig')->with('general/locale/code')->willReturn($storeLocale);
        $this->orderMock->method('getStore')->willReturn($store);

        $billingAddress = $this->createMock(OrderAddressInterface::class);
        $billingAddress->method('getCountryId')->willReturn($countryId);
        $this->orderMock->method('getBillingAddress')->willReturn($billingAddress);

        return $this->builder->build(['payment' => $this->getPaymentDOMock()]);
    }
}
