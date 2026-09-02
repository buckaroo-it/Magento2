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

    public function testBuild(): void
    {
        $store = 1;
        $newStatus = 'pending';

        $this->configProviderAccountMock->method('getCreateOrderBeforeTransaction')
            ->with($store)
            ->willReturn(true);

        $this->configProviderAccountMock->method('getOrderStatusNew')
            ->with($store)
            ->willReturn($newStatus);

        $this->orderMock->method('getStoreId')
            ->willReturn($store);

        $this->orderMock->method('setStatus')
            ->with($newStatus)
            ->willReturnSelf();

        $this->orderMock->method('save')
            ->willReturnSelf();

        $result = $this->dataBuilder->build(['payment' => $this->getPaymentDOMock()]);

        $this->assertEquals([], $result);
    }
}
