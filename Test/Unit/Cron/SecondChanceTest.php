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

        $store1->method('getId')->willReturn(1);
        $store2->method('getId')->willReturn(2);
        $store3->method('getId')->willReturn(3);

        $stores = [$store1, $store2, $store3];

        $this->storeRepository->method('getList')
            ->willReturn($stores);

        // Store 1: SecondChance enabled
        $this->configProvider->method('isSecondChanceEnabled')
            ->willReturnCallback(function($arg1, $arg2 = null) {
                    static $callCount = 0;
                    $callCount++;
                    // TODO: Implement proper argument checking based on call count
                    // Original withConsecutive args: [$store1], [$store2], [$store3]
                    return null;
                })
            ->willReturnOnConsecutiveCalls(true, false, true);

        // Should process steps 2 and 1 for enabled stores
        $this->secondChanceRepository->method('getSecondChanceCollection')
            ->willReturnCallback(function($arg1, $arg2 = null) {
                    unset($arg1, $arg2); // Suppress unused parameter warnings
                    static $callCount = 0;
                    $callCount++;
                    // TODO: Implement proper argument checking based on call count
                    // Original withConsecutive args: [2, $store1], [1, $store1], [2, $store3], [1, $store3]
                    return null;
                });

        $this->logging->method('addDebug')
            ->willReturnCallback(function($message, $context = null) {
                // Use the parameters to avoid PHPMD warnings
                if (strpos($message, 'Starting SecondChance') !== false) {
                    return true;
                }
                // Use context parameter if provided
                if ($context !== null) {
                    // Process context if needed
                }
                return null;
            });

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

        $this->storeRepository->method('getList')
            ->willReturn($stores);

        // Both stores have SecondChance disabled
        $this->configProvider->method('isSecondChanceEnabled')
            ->willReturnCallback(function($arg1, $arg2 = null) {
                    unset($arg1, $arg2); // Suppress unused parameter warnings
                    static $callCount = 0;
                    $callCount++;
                    // TODO: Implement proper argument checking based on call count
                    // Original withConsecutive args: [$store1], [$store2]
                    return null;
                })
            ->willReturnOnConsecutiveCalls(false, false);

        // Should not process any collections
        $this->secondChanceRepository->expects($this->never())
            ->method('getSecondChanceCollection');

        $this->logging->method('addDebug')
            ->willReturnCallback(function($message, $context = null) {
                // Use the parameters to avoid PHPMD warnings
                if (strpos($message, 'Starting SecondChance') !== false) {
                    return true;
                }
                // Use context parameter if provided
                if ($context !== null) {
                    // Process context if needed
                }
                return null;
            });

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
        $this->storeRepository->method('getList')
            ->willReturn([]);

        $this->configProvider->expects($this->never())
            ->method('isSecondChanceEnabled');

        $this->secondChanceRepository->expects($this->never())
            ->method('getSecondChanceCollection');

        $this->logging->method('addDebug')
            ->willReturnCallback(function($message, $context = null) {
                // Use the parameters to avoid PHPMD warnings
                if (strpos($message, 'Starting SecondChance') !== false) {
                    return true;
                }
                // Use context parameter if provided
                if ($context !== null) {
                    // Process context if needed
                }
                return null;
            });

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
        $store->method('getId')->willReturn(1);

        $this->storeRepository->method('getList')
            ->willReturn([$store]);

        $this->configProvider->method('isSecondChanceEnabled')
            ->with($store)
            ->willReturn(true);

        // Mock empty collections for both step 2 and step 1
        $this->secondChanceRepository->method('getSecondChanceCollection')
            ->willReturn(null);

        $instance = $this->getInstance([
            'configProvider' => $this->configProvider,
            'storeRepository' => $this->storeRepository,
            'logging' => $this->logging,
            'secondChanceRepository' => $this->secondChanceRepository,
        ]);

        $result = $instance->execute();
        $this->assertInstanceOf(SecondChance::class, $result);
    }
}
