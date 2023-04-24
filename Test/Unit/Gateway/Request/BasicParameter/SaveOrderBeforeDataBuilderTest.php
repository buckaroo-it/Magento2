<?php

declare(strict_types=1);

namespace Buckaroo\Magento2\Test\Unit\Gateway\Request\BasicParameter;

use Buckaroo\Magento2\Gateway\Request\BasicParameter\SaveOrderBeforeDataBuilder;
use Buckaroo\Magento2\Model\ConfigProvider\Account;
use Buckaroo\Magento2\Test\Unit\Gateway\Request\AbstractDataBuilderTest;

class SaveOrderBeforeDataBuilderTest extends AbstractDataBuilderTest
{
    /**
     * @var Account|MockObject
     */
    protected $configProviderAccountMock;

    protected function setUp(): void
    {
        parent::setUp();

        $this->configProviderAccountMock = $this->getMockBuilder(Account::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->dataBuilder = new SaveOrderBeforeDataBuilder($this->configProviderAccountMock);
    }

    public function testBuild(): void
    {
        $store = 1;
        $newStatus = 'pending';

        $this->configProviderAccountMock->expects($this->once())
            ->method('getCreateOrderBeforeTransaction')
            ->with($store)
            ->willReturn(true);

        $this->configProviderAccountMock->expects($this->once())
            ->method('getOrderStatusNew')
            ->with($store)
            ->willReturn($newStatus);

        $this->orderMock->expects($this->once())
            ->method('getStoreId')
            ->willReturn($store);

        $this->orderMock->expects($this->once())
            ->method('setStatus')
            ->with($newStatus)
            ->willReturnSelf();

        $this->orderMock->expects($this->once())
            ->method('save')
            ->willReturnSelf();

        $result = $this->dataBuilder->build(['payment' => $this->getPaymentDOMock()]);

        $this->assertEquals([], $result);
    }
}