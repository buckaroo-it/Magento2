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

namespace Buckaroo\Magento2\Test\Unit\Gateway\Request\BasicParameter;

use Buckaroo\Magento2\Gateway\Request\BasicParameter\SaveOrderBeforeDataBuilder;
use Buckaroo\Magento2\Model\ConfigProvider\Account;
use Buckaroo\Magento2\Test\Unit\Gateway\Request\AbstractDataBuilderTest;
use Magento\Sales\Model\Order\Config as OrderConfig;
use PHPUnit\Framework\MockObject\MockObject;

class SaveOrderBeforeDataBuilderTest extends AbstractDataBuilderTest
{
    /**
     * @var SaveOrderBeforeDataBuilder
     */
    private $dataBuilder;

    /**
     * @var Account|MockObject
     */
    protected $configProviderAccountMock;

    protected function setUp(): void
    {
        parent::setUp();

        $this->configProviderAccountMock = $this->createMock(Account::class);

        $this->dataBuilder = new SaveOrderBeforeDataBuilder($this->configProviderAccountMock);
    }

    public function testBuildSetsNewStateAndStatusAndSavesOrder(): void
    {
        $store = 1;

        $this->configProviderAccountMock->expects($this->once())
            ->method('getCreateOrderBeforeTransaction')
            ->with($store)
            ->willReturn(true);

        $this->configProviderAccountMock->expects($this->once())
            ->method('getOrderStatusNew')
            ->with($store)
            ->willReturn('pending_custom');

        $this->orderMock->method('getStoreId')->willReturn($store);
        $this->orderMock->expects($this->never())->method('getConfig');
        $this->orderMock->expects($this->once())->method('setState')->with('new')->willReturnSelf();
        $this->orderMock->expects($this->once())->method('setStatus')->with('pending_custom')->willReturnSelf();
        $this->orderMock->expects($this->once())->method('save')->willReturnSelf();

        $this->assertSame([], $this->dataBuilder->build(['payment' => $this->getPaymentDOMock()]));
    }

    public function testBuildFallsBackToDefaultNewStatus(): void
    {
        $store = 2;

        $this->configProviderAccountMock->method('getCreateOrderBeforeTransaction')->with($store)->willReturn(true);
        $this->configProviderAccountMock->method('getOrderStatusNew')->with($store)->willReturn(null);

        $orderConfig = $this->createMock(OrderConfig::class);
        $orderConfig->expects($this->once())
            ->method('getStateDefaultStatus')
            ->with('new')
            ->willReturn('pending');

        $this->orderMock->method('getStoreId')->willReturn($store);
        $this->orderMock->method('getConfig')->willReturn($orderConfig);
        $this->orderMock->expects($this->once())->method('setState')->with('new')->willReturnSelf();
        $this->orderMock->expects($this->once())->method('setStatus')->with('pending')->willReturnSelf();
        $this->orderMock->expects($this->once())->method('save')->willReturnSelf();

        $this->assertSame([], $this->dataBuilder->build(['payment' => $this->getPaymentDOMock()]));
    }

    public function testBuildDoesNotTouchOrderWhenSaveBeforeTransactionIsDisabled(): void
    {
        $store = 1;

        $this->configProviderAccountMock->expects($this->once())
            ->method('getCreateOrderBeforeTransaction')
            ->with($store)
            ->willReturn(false);
        $this->configProviderAccountMock->expects($this->never())->method('getOrderStatusNew');

        $this->orderMock->method('getStoreId')->willReturn($store);
        $this->orderMock->expects($this->never())->method('setState');
        $this->orderMock->expects($this->never())->method('setStatus');
        $this->orderMock->expects($this->never())->method('save');

        $this->assertSame([], $this->dataBuilder->build(['payment' => $this->getPaymentDOMock()]));
    }
}
