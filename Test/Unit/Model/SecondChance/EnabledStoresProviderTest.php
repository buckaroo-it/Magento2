<?php
/**
 * NOTICE OF LICENSE
 *
 * This source file is subject to the MIT License
 * It is available through the world-wide-web at this URL:
 * https://tldrlegal.com/license/mit-license
 * If you are unable to obtain it through the world-wide-web, please send an email
 * to support@buckaroo.nl so we can send you a copy immediately.
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
