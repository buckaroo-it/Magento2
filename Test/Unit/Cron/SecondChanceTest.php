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

namespace Buckaroo\Magento2\Test\Unit\Cron;

use Buckaroo\Magento2\Cron\SecondChance;
use Buckaroo\Magento2\Model\ConfigProvider\SecondChance as ConfigProvider;
use Buckaroo\Magento2\Model\SecondChanceRepository;
use Buckaroo\Magento2\Logging\Log;
use Magento\Store\Api\StoreRepositoryInterface;
use Magento\Store\Model\Store;

class SecondChanceTest extends \Buckaroo\Magento2\Test\BaseTest
{
    protected $instanceClass = SecondChance::class;

    /** @var ConfigProvider|\PHPUnit\Framework\MockObject\MockObject */
    private $configProvider;
    
    /** @var StoreRepositoryInterface|\PHPUnit\Framework\MockObject\MockObject */
    private $storeRepository;
    
    /** @var Log|\PHPUnit\Framework\MockObject\MockObject */
    private $logging;
    
    /** @var SecondChanceRepository|\PHPUnit\Framework\MockObject\MockObject */
    private $secondChanceRepository;

    public function setUp(): void
    {
        parent::setUp();
        
        $this->configProvider = $this->getFakeMock(ConfigProvider::class)->getMock();
        $this->storeRepository = $this->getFakeMock(StoreRepositoryInterface::class)->getMock();
        $this->logging = $this->getFakeMock(Log::class)->getMock();
        $this->secondChanceRepository = $this->getFakeMock(SecondChanceRepository::class)->getMock();
    }

    public function testExecuteWithEnabledStores()
    {
        $store1 = $this->getFakeMock(Store::class)->getMock();
        $store2 = $this->getFakeMock(Store::class)->getMock();
        $store3 = $this->getFakeMock(Store::class)->getMock();
        
        $store1->expects($this->once())->method('getId')->willReturn(1);
        $store2->expects($this->once())->method('getId')->willReturn(2);
        $store3->expects($this->once())->method('getId')->willReturn(3);
        
        $stores = [$store1, $store2, $store3];
        
        $this->storeRepository->expects($this->once())
            ->method('getList')
            ->willReturn($stores);
        
        // Store 1: SecondChance enabled
        $this->configProvider->expects($this->exactly(3))
            ->method('isSecondChanceEnabled')
            ->withConsecutive([$store1], [$store2], [$store3])
            ->willReturnOnConsecutiveCalls(true, false, true);
        
        // Should process steps 2 and 1 for enabled stores
        $this->secondChanceRepository->expects($this->exactly(4))
            ->method('getSecondChanceCollection')
            ->withConsecutive(
                [2, $store1],
                [1, $store1],
                [2, $store3],
                [1, $store3]
            );
        
        $this->logging->expects($this->exactly(3))
            ->method('addDebug')
            ->withConsecutive(
                [$this->stringContains('Starting SecondChance cron execution')],
                [$this->stringContains('Processing store: 1')],
                [$this->stringContains('Processing store: 3')]
            );

        $instance = $this->getInstance([
            'configProvider' => $this->configProvider,
            'storeRepository' => $this->storeRepository,
            'logging' => $this->logging,
            'secondChanceRepository' => $this->secondChanceRepository,
        ]);

        $result = $instance->execute();
        $this->assertInstanceOf(SecondChance::class, $result);
    }

    public function testExecuteWithNoEnabledStores()
    {
        $store1 = $this->getFakeMock(Store::class)->getMock();
        $store2 = $this->getFakeMock(Store::class)->getMock();
        
        $stores = [$store1, $store2];
        
        $this->storeRepository->expects($this->once())
            ->method('getList')
            ->willReturn($stores);
        
        // Both stores have SecondChance disabled
        $this->configProvider->expects($this->exactly(2))
            ->method('isSecondChanceEnabled')
            ->withConsecutive([$store1], [$store2])
            ->willReturnOnConsecutiveCalls(false, false);
        
        // Should not process any collections
        $this->secondChanceRepository->expects($this->never())
            ->method('getSecondChanceCollection');

        $this->logging->expects($this->exactly(2))
            ->method('addDebug')
            ->withConsecutive(
                [$this->stringContains('Starting SecondChance cron execution')],
                [$this->stringContains('SecondChance cron execution completed')]
            );

        $instance = $this->getInstance([
            'configProvider' => $this->configProvider,
            'storeRepository' => $this->storeRepository,
            'logging' => $this->logging,
            'secondChanceRepository' => $this->secondChanceRepository,
        ]);

        $result = $instance->execute();
        $this->assertInstanceOf(SecondChance::class, $result);
    }

    public function testExecuteWithEmptyStoreList()
    {
        $this->storeRepository->expects($this->once())
            ->method('getList')
            ->willReturn([]);
        
        $this->configProvider->expects($this->never())
            ->method('isSecondChanceEnabled');
        
        $this->secondChanceRepository->expects($this->never())
            ->method('getSecondChanceCollection');

        $this->logging->expects($this->exactly(2))
            ->method('addDebug')
            ->withConsecutive(
                [$this->stringContains('Starting SecondChance cron execution')],
                [$this->stringContains('SecondChance cron execution completed')]
            );

        $instance = $this->getInstance([
            'configProvider' => $this->configProvider,
            'storeRepository' => $this->storeRepository,
            'logging' => $this->logging,
            'secondChanceRepository' => $this->secondChanceRepository,
        ]);

        $result = $instance->execute();
        $this->assertInstanceOf(SecondChance::class, $result);
    }

    public function testExecuteStepProcessingOrder()
    {
        $store = $this->getFakeMock(Store::class)->getMock();
        $store->expects($this->once())->method('getId')->willReturn(1);
        
        $this->storeRepository->expects($this->once())
            ->method('getList')
            ->willReturn([$store]);
        
        $this->configProvider->expects($this->once())
            ->method('isSecondChanceEnabled')
            ->with($store)
            ->willReturn(true);
        
        // Verify that step 2 is processed before step 1
        $this->secondChanceRepository->expects($this->exactly(2))
            ->method('getSecondChanceCollection')
            ->withConsecutive([2, $store], [1, $store]);

        $instance = $this->getInstance([
            'configProvider' => $this->configProvider,
            'storeRepository' => $this->storeRepository,
            'logging' => $this->logging,
            'secondChanceRepository' => $this->secondChanceRepository,
        ]);

        $instance->execute();
    }
} 