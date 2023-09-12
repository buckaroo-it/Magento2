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
