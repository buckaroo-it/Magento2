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

namespace Buckaroo\Magento2\Test\Unit\Model\Export;

use Buckaroo\Magento2\Helper\PaymentGroupTransaction;
use Buckaroo\Magento2\Model\ConfigProvider\Method\Giftcards;
use Buckaroo\Magento2\Model\Export\ConvertToCsv;
use Buckaroo\Magento2\Model\ResourceModel\Giftcard\Collection;
use Magento\Framework\Api\AttributeValueFactory;
use Magento\Framework\Api\Search\DocumentInterface;
use Magento\Framework\Api\Search\SearchCriteria;
use Magento\Framework\Api\Search\SearchResultInterface;
use Magento\Framework\Filesystem;
use Magento\Framework\Filesystem\Directory\WriteInterface;
use Magento\Framework\Filesystem\File\WriteInterface as FileWriteInterface;
use Magento\Framework\View\Element\UiComponent\ContextInterface;
use Magento\Framework\View\Element\UiComponent\DataProvider\DataProviderInterface;
use Magento\Framework\View\Element\UiComponent\DataProvider\Document;
use Magento\Framework\View\Element\UiComponentInterface;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Ui\Component\MassAction\Filter;
use Magento\Ui\Model\Export\MetadataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * The export needs its full collaborator set (filesystem, UI component, data provider, metadata, order lookup)
 *
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class ConvertToCsvTest extends TestCase
{
    /** @var Filter|MockObject */
    private $filter;

    /** @var Giftcards|MockObject */
    private $giftcardConfig;

    /** @var OrderRepositoryInterface|MockObject */
    private $orderRepository;

    /** @var PaymentGroupTransaction|MockObject */
    private $groupTransaction;

    /** @var array[] rows written to the CSV stream */
    private array $writtenRows = [];

    private ConvertToCsv $converter;

    protected function setUp(): void
    {
        $stream = $this->createMock(FileWriteInterface::class);
        $stream->method('writeCsv')->willReturnCallback(function (array $row) {
            $this->writtenRows[] = $row;
            return 0;
        });
        $directory = $this->createMock(WriteInterface::class);
        $directory->method('openFile')->willReturn($stream);
        $filesystem = $this->createMock(Filesystem::class);
        $filesystem->method('getDirectoryWrite')->willReturn($directory);

        $metadataProvider = $this->createMock(MetadataProvider::class);
        $metadataProvider->method('getFields')->willReturn(['payment_method']);
        $metadataProvider->method('getOptions')->willReturn([]);
        $metadataProvider->method('getHeaders')->willReturn(['Payment Method']);
        $metadataProvider->method('getRowData')->willReturnCallback(
            fn(DocumentInterface $document) => [$document->getData('payment_method')]
        );

        $this->filter = $this->createMock(Filter::class);
        $this->giftcardConfig = $this->createMock(Giftcards::class);
        $this->orderRepository = $this->createMock(OrderRepositoryInterface::class);
        $this->groupTransaction = $this->createMock(PaymentGroupTransaction::class);

        $this->converter = new ConvertToCsv(
            $filesystem,
            $this->filter,
            $metadataProvider,
            $this->orderRepository,
            $this->groupTransaction,
            $this->createMock(Collection::class),
            $this->createMock(StoreManagerInterface::class),
            $this->giftcardConfig
        );
    }

    public function testOrderGridRowsUseTheirOwnStoreSetting(): void
    {
        $this->mockComponent('sales_order_grid', [
            $this->createDocument(10, 1, 'buckaroo_magento2_giftcards'),
            $this->createDocument(11, 2, 'buckaroo_magento2_giftcards'),
        ]);
        $this->giftcardConfig->expects($this->exactly(2))
            ->method('hasAdvancedExportGiftcards')
            ->willReturnCallback(fn($storeId) => (int)$storeId === 2);

        $order = $this->createMock(OrderInterface::class);
        $order->method('getIncrementId')->willReturn('000000011');
        $this->orderRepository->expects($this->once())->method('get')->with(11)->willReturn($order);
        $this->groupTransaction->method('getGroupTransactionItems')
            ->with('000000011')
            ->willReturn([['servicecode' => 'boekenbon']]);

        $this->converter->getCsvFile();

        $this->assertSame([
            ['Payment Method'],
            ['buckaroo_magento2_giftcards'],
            ['buckaroo_magento2_giftcards,boekenbon'],
        ], $this->writtenRows);
    }

    public function testOtherGridsAreNotConverted(): void
    {
        $this->mockComponent('customer_listing', [$this->createDocument(10, 1, 'n/a')]);
        $this->giftcardConfig->expects($this->never())->method('hasAdvancedExportGiftcards');
        $this->orderRepository->expects($this->never())->method('get');

        $this->converter->getCsvFile();

        $this->assertSame([['Payment Method'], ['n/a']], $this->writtenRows);
    }

    private function mockComponent(string $name, array $documents): void
    {
        $searchResult = $this->createMock(SearchResultInterface::class);
        $searchResult->method('getTotalCount')->willReturn(count($documents));
        $searchResult->method('getItems')->willReturn($documents);

        $dataProvider = $this->createMock(DataProviderInterface::class);
        $dataProvider->method('getSearchCriteria')->willReturn(new SearchCriteria());
        $dataProvider->method('getSearchResult')->willReturn($searchResult);

        $context = $this->createMock(ContextInterface::class);
        $context->method('getDataProvider')->willReturn($dataProvider);

        $component = $this->createMock(UiComponentInterface::class);
        $component->method('getName')->willReturn($name);
        $component->method('getContext')->willReturn($context);

        $this->filter->method('getComponent')->willReturn($component);
    }

    private function createDocument(int $entityId, int $storeId, string $paymentMethod): Document
    {
        $document = new Document($this->createMock(AttributeValueFactory::class));
        $document->setData([
            'entity_id'      => $entityId,
            'store_id'       => $storeId,
            'payment_method' => $paymentMethod,
        ]);

        return $document;
    }
}
