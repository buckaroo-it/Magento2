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


namespace Buckaroo\Magento2\Test\Unit\Service\Store;

use Buckaroo\Magento2\Service\Store\StoreEmulator;
use Magento\Framework\App\Area;
use Magento\Store\Model\App\Emulation;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\TestCase;

class StoreEmulatorTest extends TestCase
{
    /**
     * @var Emulation|\PHPUnit\Framework\MockObject\MockObject
     */
    private $emulation;

    /**
     * @var StoreManagerInterface|\PHPUnit\Framework\MockObject\MockObject
     */
    private $storeManager;

    /**
     * @var StoreEmulator
     */
    private $storeEmulator;

    protected function setUp(): void
    {
        $this->emulation = $this->createMock(Emulation::class);

        // Ambient store 1 throughout, so the tests below exercise the cross-store path. The
        // "already current" case sets its own expectation.
        $this->storeManager = $this->createMock(StoreManagerInterface::class);
        $this->storeManager->method('getStore')->willReturn($this->storeWithId(1));

        $this->storeEmulator = new StoreEmulator($this->emulation, $this->storeManager);
    }

    private function storeWithId(int $id): \Magento\Store\Model\Store
    {
        $store = $this->createMock(\Magento\Store\Model\Store::class);
        $store->method('getId')->willReturn($id);

        return $store;
    }

    public function testEmulatesTheFrontendAreaForTheGivenStoreAndStopsAfterwards(): void
    {
        $this->emulation->expects($this->once())
            ->method('startEnvironmentEmulation')
            ->with(2, Area::AREA_FRONTEND, true);
        $this->emulation->expects($this->once())->method('stopEnvironmentEmulation');

        $this->assertSame('result', $this->storeEmulator->emulate(2, fn() => 'result'));
    }

    public function testNormalisesTheStoreBeforeEmulating(): void
    {
        $this->emulation->expects($this->once())
            ->method('startEnvironmentEmulation')
            ->with(2, Area::AREA_FRONTEND, true);

        $this->storeEmulator->emulate('2', fn() => null);
    }

    /**
     * A push whose order has no usable store must still be processed, just without emulation -
     * callers should never have to branch on that themselves.
     */
    public function testRunsTheCallbackWithoutEmulatingWhenNoStoreIsGiven(): void
    {
        $this->emulation->expects($this->never())->method('startEnvironmentEmulation');
        $this->emulation->expects($this->never())->method('stopEnvironmentEmulation');

        $this->assertSame('ran', $this->storeEmulator->emulate(null, fn() => 'ran'));
    }

    /**
     * The emulator wraps third party observers and plugins. If one of them throws, the store scope
     * still has to be restored or every later request in the process runs in the wrong store.
     */
    public function testRestoresTheEnvironmentWhenTheCallbackThrows(): void
    {
        $this->emulation->expects($this->once())->method('startEnvironmentEmulation');
        $this->emulation->expects($this->once())->method('stopEnvironmentEmulation');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('boom');

        $this->storeEmulator->emulate(2, function () {
            throw new \RuntimeException('boom');
        });
    }

    /**
     * Emulation re-initialises store, locale, design and translations. On a single-store install
     * the target is always the ambient store, so doing it on every push is cost and risk for no
     * behaviour change.
     */
    public function testSkipsEmulationWhenTheAmbientStoreIsAlreadyTheTarget(): void
    {
        $this->emulation->expects($this->never())->method('startEnvironmentEmulation');
        $this->emulation->expects($this->never())->method('stopEnvironmentEmulation');

        $this->assertSame('ran', $this->storeEmulator->emulate(1, fn() => 'ran'));
    }

    /**
     * If we cannot tell what the ambient store is, emulating is the safe answer.
     */
    public function testEmulatesWhenTheAmbientStoreCannotBeResolved(): void
    {
        $storeManager = $this->createMock(StoreManagerInterface::class);
        $storeManager->method('getStore')->willThrowException(new \RuntimeException('no store'));
        $storeEmulator = new StoreEmulator($this->emulation, $storeManager);

        $this->emulation->expects($this->once())
            ->method('startEnvironmentEmulation')
            ->with(2, Area::AREA_FRONTEND, true);
        $this->emulation->expects($this->once())->method('stopEnvironmentEmulation');

        $this->assertSame('ran', $storeEmulator->emulate(2, fn() => 'ran'));
    }
}
