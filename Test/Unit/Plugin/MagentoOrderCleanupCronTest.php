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

namespace Buckaroo\Magento2\Test\Unit\Plugin;

use Buckaroo\Magento2\Model\MagentoOrderCleanupScope;
use Buckaroo\Magento2\Plugin\MagentoOrderCleanupCron;

class MagentoOrderCleanupCronTest extends \Buckaroo\Magento2\Test\BaseTest
{
    protected $instanceClass = 'Buckaroo\Magento2\Plugin\MagentoOrderCleanupCron';

    /**
     * @return \PHPUnit\Framework\MockObject\MockObject
     */
    private function cronMock()
    {
        return $this->getFakeMock('Magento\Sales\Model\CronJob\CleanExpiredOrders')
            ->disableOriginalConstructor()
            ->getMock();
    }

    public function testTheCronRunsInsideTheCleanupScope(): void
    {
        $scope = new MagentoOrderCleanupScope();
        $instance = new MagentoOrderCleanupCron($scope);

        $seenInside = null;
        $instance->aroundExecute($this->cronMock(), function () use ($scope, &$seenInside) {
            $seenInside = $scope->isRunning();
        });

        $this->assertTrue($seenInside, 'the cron must run with the scope open');
        $this->assertFalse($scope->isRunning(), 'the scope must close once the cron finishes');
    }

    public function testTheCronResultIsPassedBackUnchanged(): void
    {
        $scope = new MagentoOrderCleanupScope();
        $instance = new MagentoOrderCleanupCron($scope);

        $this->assertSame('untouched', $instance->aroundExecute($this->cronMock(), fn () => 'untouched'));
    }

    /**
     * If the cron throws, the scope must not stay open for the rest of the request.
     */
    public function testTheScopeClosesWhenTheCronThrows(): void
    {
        $scope = new MagentoOrderCleanupScope();
        $instance = new MagentoOrderCleanupCron($scope);

        try {
            $instance->aroundExecute($this->cronMock(), function () {
                throw new \RuntimeException('cleanup failed');
            });
            $this->fail('the exception should have been rethrown');
        } catch (\RuntimeException $exception) {
            $this->assertSame('cleanup failed', $exception->getMessage());
        }

        $this->assertFalse($scope->isRunning());
    }
}
