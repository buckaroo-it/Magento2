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

namespace Buckaroo\Magento2\Test\Unit\Model\SecondChance;

use Buckaroo\Magento2\Model\ConfigProvider\SecondChance as SecondChanceConfig;
use Buckaroo\Magento2\Model\SecondChance\EnabledStoresProvider;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Api\StoreRepositoryInterface;
use PHPUnit\Framework\TestCase;

class EnabledStoresProviderTest extends TestCase
{
    public function testAdminStoreStringIdIsExcluded(): void
    {
        $adminStore = $this->createMock(StoreInterface::class);
        $enabledStore = $this->createMock(StoreInterface::class);
        $disabledStore = $this->createMock(StoreInterface::class);

        $adminStore->method('getId')->willReturn('0');
        $enabledStore->method('getId')->willReturn('1');
        $disabledStore->method('getId')->willReturn('2');

        $storeRepository = $this->createMock(StoreRepositoryInterface::class);
        $storeRepository->method('getList')->willReturn([
            $adminStore,
            $enabledStore,
            $disabledStore,
        ]);

        $configProvider = $this->createMock(SecondChanceConfig::class);
        $configProvider->expects($this->exactly(2))
            ->method('isSecondChanceEnabled')
            ->willReturnCallback(
                function ($store) use ($enabledStore): bool {
                    return $store === $enabledStore;
                }
            );

        $provider = new EnabledStoresProvider($configProvider, $storeRepository);

        $this->assertSame([$enabledStore], $provider->getEnabledStores());
    }
}
