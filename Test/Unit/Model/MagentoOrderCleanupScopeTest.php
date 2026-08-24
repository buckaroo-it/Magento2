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

namespace Buckaroo\Magento2\Test\Unit\Model;

use Buckaroo\Magento2\Model\MagentoOrderCleanupScope;

class MagentoOrderCleanupScopeTest extends \Buckaroo\Magento2\Test\BaseTest
{
    protected $instanceClass = 'Buckaroo\Magento2\Model\MagentoOrderCleanupScope';

    public function testTheScopeIsClosedUntilSomethingOpensIt(): void
    {
        $this->assertFalse((new MagentoOrderCleanupScope())->isRunning());
    }

    public function testTheScopeIsOpenForTheDurationOfTheCallback(): void
    {
        $scope = new MagentoOrderCleanupScope();

        $seenInside = $scope->run(fn () => $scope->isRunning());

        $this->assertTrue($seenInside, 'the callback must see the scope as open');
        $this->assertFalse($scope->isRunning(), 'the scope must close again afterwards');
    }

    public function testTheCallbackResultIsPassedBack(): void
    {
        $scope = new MagentoOrderCleanupScope();

        $this->assertSame('cleaned', $scope->run(fn () => 'cleaned'));
    }

    /**
     * A cron that throws must not leave the scope open, or every later cancellation in the same
     * request would be treated as if the cleanup cron had asked for it.
     */
    public function testTheScopeClosesEvenWhenTheCallbackThrows(): void
    {
        $scope = new MagentoOrderCleanupScope();

        try {
            $scope->run(function () {
                throw new \RuntimeException('cron blew up');
            });
            $this->fail('the exception should have been rethrown');
        } catch (\RuntimeException $exception) {
            $this->assertSame('cron blew up', $exception->getMessage());
        }

        $this->assertFalse($scope->isRunning());
    }

    /**
     * A nested call must not close the scope early for the outer one.
     */
    public function testANestedRunLeavesTheOuterScopeOpen(): void
    {
        $scope = new MagentoOrderCleanupScope();
        $stillOpenAfterInner = null;

        $scope->run(function () use ($scope, &$stillOpenAfterInner) {
            $scope->run(fn () => true);
            $stillOpenAfterInner = $scope->isRunning();
        });

        $this->assertTrue($stillOpenAfterInner, 'the inner call must not close the outer scope');
        $this->assertFalse($scope->isRunning());
    }
}
