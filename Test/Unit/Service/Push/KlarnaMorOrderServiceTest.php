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

namespace Buckaroo\Magento2\Test\Unit\Service\Push;

use Buckaroo\Magento2\Logging\BuckarooLoggerInterface;
use Buckaroo\Magento2\Service\Push\KlarnaMorOrderService;
use Magento\Framework\Api\SearchCriteria;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\DB\Select;
use Magento\Framework\TestFramework\Unit\Helper\ObjectManager;
use Magento\Sales\Api\Data\OrderSearchResultInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class KlarnaMorOrderServiceTest extends TestCase
{
    /**
     * @var KlarnaMorOrderService
     */
    private KlarnaMorOrderService $service;

    /**
     * @var OrderRepositoryInterface|MockObject
     */
    private $orderRepositoryMock;

    /**
     * @var SearchCriteriaBuilder|MockObject
     */
    private $searchCriteriaBuilderMock;

    /**
     * @var ResourceConnection|MockObject
     */
    private $resourceConnectionMock;

    /**
     * @inheritdoc
     */
    protected function setUp(): void
    {
        $this->orderRepositoryMock = $this->createMock(OrderRepositoryInterface::class);
        $this->searchCriteriaBuilderMock = $this->createMock(SearchCriteriaBuilder::class);
        $this->resourceConnectionMock = $this->createMock(ResourceConnection::class);

        $objectManager = new ObjectManager($this);
        $this->service = $objectManager->getObject(KlarnaMorOrderService::class, [
            'orderRepository' => $this->orderRepositoryMock,
            'searchCriteriaBuilder' => $this->searchCriteriaBuilderMock,
            'resourceConnection' => $this->resourceConnectionMock,
            'logger' => $this->createMock(BuckarooLoggerInterface::class),
        ]);
    }

    public function testGetOrderByDataRequestKeyReturnsOrder(): void
    {
        $orderMock = $this->createMock(Order::class);
        $searchCriteriaMock = $this->createMock(SearchCriteria::class);
        $searchResultMock = $this->createMock(OrderSearchResultInterface::class);

        $this->searchCriteriaBuilderMock->expects($this->once())
            ->method('addFilter')
            ->with('buckaroo_datarequest_key', 'DR-123')
            ->willReturnSelf();
        $this->searchCriteriaBuilderMock->expects($this->once())
            ->method('setPageSize')
            ->with(1)
            ->willReturnSelf();
        $this->searchCriteriaBuilderMock->expects($this->once())
            ->method('create')
            ->willReturn($searchCriteriaMock);

        $this->orderRepositoryMock->expects($this->once())
            ->method('getList')
            ->with($searchCriteriaMock)
            ->willReturn($searchResultMock);
        $searchResultMock->expects($this->once())
            ->method('getItems')
            ->willReturn([$orderMock]);

        $this->assertSame($orderMock, $this->service->getOrderByDataRequestKey('DR-123'));
    }

    public function testGetOrderByPendingDataRequestPushKeyReturnsOrder(): void
    {
        $connectionMock = $this->createMock(AdapterInterface::class);
        $selectMock = $this->createMock(Select::class);
        $orderMock = $this->createMock(Order::class);

        $this->resourceConnectionMock->method('getConnection')->willReturn($connectionMock);
        $this->resourceConnectionMock->method('getTableName')
            ->willReturnCallback(static fn (string $table) => $table);

        $connectionMock->expects($this->once())->method('select')->willReturn($selectMock);
        $selectMock->expects($this->once())->method('from')->willReturnSelf();
        $selectMock->expects($this->once())->method('join')->willReturnSelf();
        $selectMock->expects($this->once())->method('where')->willReturnSelf();
        $selectMock->expects($this->once())->method('limit')->with(1)->willReturnSelf();
        $connectionMock->expects($this->once())->method('fetchOne')->with($selectMock)->willReturn('42');

        $this->orderRepositoryMock->expects($this->once())
            ->method('get')
            ->with(42)
            ->willReturn($orderMock);

        $this->assertSame($orderMock, $this->service->getOrderByPendingDataRequestPushKey('PUSH-123'));
    }
}
