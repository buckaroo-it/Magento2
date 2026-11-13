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

namespace Buckaroo\Magento2\Test\Unit\Cron;

use Buckaroo\Magento2\Cron\SecondChance;
use Buckaroo\Magento2\Model\ResourceModel\SecondChance\Collection;
use Buckaroo\Magento2\Logging\Log;
use Buckaroo\Magento2\Model\SecondChance\EnabledStoresProvider;
use Buckaroo\Magento2\Model\SecondChance\WorkChecker;
use Buckaroo\Magento2\Model\SecondChanceRepository;
use Magento\Store\Api\Data\StoreInterface;
use PHPUnit\Framework\TestCase;

class SecondChanceTest extends TestCase
{
    /**
     * @var EnabledStoresProvider|\PHPUnit\Framework\MockObject\MockObject
     */
    private $enabledStoresProvider;

    /**
     * @var WorkChecker|\PHPUnit\Framework\MockObject\MockObject
     */
    private $workChecker;

    /**
     * @var Log|\PHPUnit\Framework\MockObject\MockObject
     */
    private $logging;

    /**
     * @var SecondChanceRepository|\PHPUnit\Framework\MockObject\MockObject
     */
    private $secondChanceRepository;

    protected function setUp(): void
    {
        $this->enabledStoresProvider = $this->createMock(EnabledStoresProvider::class);
        $this->workChecker = $this->createMock(WorkChecker::class);
        $this->logging = $this->createMock(Log::class);
        $this->secondChanceRepository = $this->createMock(SecondChanceRepository::class);
    }

    public function testEmptyEnabledStoreListExitsImmediately(): void
    {
        $this->enabledStoresProvider->method('getEnabledStores')->willReturn([]);
        $this->workChecker->expects($this->never())->method('hasProcessableItems');
        $this->secondChanceRepository->expects($this->never())->method('getSecondChanceCollection');
        $this->logging->expects($this->never())->method('addDebug');
        $this->logging->expects($this->never())->method('addError');

        $instance = $this->createInstance();

        $this->assertSame($instance, $instance->execute());
    }

    public function testIdleRunDoesNotProcessOrLog(): void
    {
        $store = $this->createMock(StoreInterface::class);
        $stores = [$store];

        $this->enabledStoresProvider->method('getEnabledStores')->willReturn($stores);
        $this->workChecker->expects($this->once())
            ->method('hasProcessableItems')
            ->with($stores)
            ->willReturn(false);
        $this->secondChanceRepository->expects($this->never())->method('getSecondChanceCollection');
        $this->logging->expects($this->never())->method('addDebug');
        $this->logging->expects($this->never())->method('addError');

        $instance = $this->createInstance();

        $this->assertSame($instance, $instance->execute());
    }

    public function testProcessesSecondStepBeforeFirstStep(): void
    {
        $store = $this->createMock(StoreInterface::class);
        $stores = [$store];
        $calls = [];

        $this->enabledStoresProvider->method('getEnabledStores')->willReturn($stores);
        $this->workChecker->method('hasProcessableItems')->with($stores)->willReturn(true);
        $this->secondChanceRepository->expects($this->exactly(2))
            ->method('getSecondChanceCollection')
            ->willReturnCallback(
                function ($step, $processedStore) use (&$calls): void {
                    $calls[] = [$step, $processedStore];
                }
            );
        $this->logging->expects($this->never())->method('addError');

        $instance = $this->createInstance();

        $this->assertSame($instance, $instance->execute());
        $this->assertSame([
            [Collection::STEP_SECOND_EMAIL, $store],
            [Collection::STEP_FIRST_EMAIL, $store],
        ], $calls);
    }

    public function testProcessingErrorIsLoggedAndNextStepStillRuns(): void
    {
        $store = $this->createMock(StoreInterface::class);
        $store->method('getId')->willReturn(1);

        $this->enabledStoresProvider->method('getEnabledStores')->willReturn([$store]);
        $this->workChecker->method('hasProcessableItems')->willReturn(true);
        $this->secondChanceRepository->expects($this->exactly(2))
            ->method('getSecondChanceCollection')
            ->willReturnCallback(
                function ($step): void {
                    if ($step === Collection::STEP_SECOND_EMAIL) {
                        throw new \RuntimeException('Processing failed');
                    }
                }
            );
        $this->logging->expects($this->once())
            ->method('addError')
            ->with($this->stringContains('Processing failed'));

        $instance = $this->createInstance();

        $this->assertSame($instance, $instance->execute());
    }

    private function createInstance(): SecondChance
    {
        return new SecondChance(
            $this->enabledStoresProvider,
            $this->workChecker,
            $this->logging,
            $this->secondChanceRepository
        );
    }
}
