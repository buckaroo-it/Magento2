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

namespace Buckaroo\Magento2\Test\Unit\Helper;

use Buckaroo\Magento2\Helper\PaymentGroupTransaction;
use Buckaroo\Magento2\Logging\BuckarooLoggerInterface;
use Buckaroo\Magento2\Model\GroupTransaction;
use Buckaroo\Magento2\Model\GroupTransactionFactory;
use Buckaroo\Magento2\Model\ResourceModel\GroupTransaction as GroupTransactionResource;
use Buckaroo\Magento2\Model\ResourceModel\GroupTransaction\Collection;
use Buckaroo\Magento2\Model\ResourceModel\GroupTransaction\CollectionFactory;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\DB\Select;
use Magento\Framework\Stdlib\DateTime\DateTime;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 * @SuppressWarnings(PHPMD.TooManyPublicMethods)
 */
class PaymentGroupTransactionTest extends TestCase
{
    /**
     * @var GroupTransactionFactory|MockObject
     */
    private $factory;

    /**
     * @var CollectionFactory|MockObject
     */
    private $collectionFactory;

    /**
     * @var GroupTransactionResource|MockObject
     */
    private $resource;

    /**
     * @var ResourceConnection|MockObject
     */
    private $resourceConnection;

    /**
     * @var DateTime|MockObject
     */
    private $dateTime;

    /**
     * @var PaymentGroupTransaction
     */
    private $helper;

    /**
     * @var array filters applied to the collection, as [field, condition]
     */
    private $filters = [];

    protected function setUp(): void
    {
        $this->factory = $this->createMock(GroupTransactionFactory::class);
        $this->collectionFactory = $this->createMock(CollectionFactory::class);
        $this->resource = $this->createMock(GroupTransactionResource::class);
        $this->resourceConnection = $this->createMock(ResourceConnection::class);
        $this->dateTime = $this->createMock(DateTime::class);
        $this->dateTime->method('gmtDate')->willReturn('2026-11-25 10:00:00');
        $this->filters = [];

        $this->helper = new PaymentGroupTransaction(
            $this->createMock(Context::class),
            $this->factory,
            $this->dateTime,
            $this->createMock(BuckarooLoggerInterface::class),
            $this->collectionFactory,
            $this->resource,
            $this->resourceConnection
        );
    }

    /**
     * Fully refunded transactions are left out; the remaining keep their position
     */
    public function testGroupTransactionItemsAreSuccessfulOnesWithAnAmountLeft(): void
    {
        $open = $this->transaction(['amount' => '10.00', 'refunded_amount' => null, 'status' => '190']);
        $refunded = $this->transaction(['amount' => '5.00', 'refunded_amount' => '5.00', 'status' => '190']);
        $partly = $this->transaction(['amount' => '8.00', 'refunded_amount' => '7.99', 'status' => '190']);
        $this->modelCollection([24 => $open, 25 => $refunded, 31 => $partly]);

        $items = $this->helper->getGroupTransactionItems('000000042');

        $this->assertSame([0 => $open, 2 => $partly], $items);
        $this->assertSame([['order_id', ['eq' => '000000042']], ['status', ['eq' => '190']]], $this->filters);
    }

    /**
     * Failed attempts are included as well: only the order filter applies
     */
    public function testAnyGroupTransactionItemsAreNotFilteredOnStatus(): void
    {
        $failed = $this->transaction(['amount' => '10.00', 'refunded_amount' => null, 'status' => '490']);
        $refunded = $this->transaction(['amount' => '5.00', 'refunded_amount' => '5.00', 'status' => '190']);
        $this->modelCollection([25 => $refunded, 26 => $failed]);

        $this->assertSame([1 => $failed], $this->helper->getAnyGroupTransactionItems('000000042'));
        $this->assertSame([['order_id', ['eq' => '000000042']]], $this->filters);
    }

    public function testIsGroupTransactionWhenAnAmountIsLeft(): void
    {
        $this->modelCollection([$this->transaction(['amount' => '1.00', 'refunded_amount' => '0.50'])]);

        $this->assertTrue($this->helper->isGroupTransaction('000000042'));
    }

    public function testIsNoGroupTransactionWhenEverythingIsRefunded(): void
    {
        $this->modelCollection([$this->transaction(['amount' => '1.00', 'refunded_amount' => '1.00'])]);

        $this->assertFalse($this->helper->isGroupTransaction('000000042'));
    }

    public function testIsAnyGroupTransaction(): void
    {
        $this->modelCollection([$this->transaction(['amount' => '1.00', 'refunded_amount' => null])]);

        $this->assertTrue($this->helper->isAnyGroupTransaction('000000042'));
    }

    public function testIsNoAnyGroupTransactionWithoutRows(): void
    {
        $this->modelCollection([]);

        $this->assertFalse($this->helper->isAnyGroupTransaction('000000042'));
    }

    /**
     * Paid amount is what is left on successful transactions: 10.00 + (8.00 - 2.50)
     */
    public function testGroupTransactionAmountSumsWhatIsLeftOnSuccessfulTransactions(): void
    {
        $this->modelCollection([
            $this->transaction(['amount' => '10.00', 'refunded_amount' => null, 'status' => '190']),
            $this->transaction(['amount' => '8.00', 'refunded_amount' => '2.50', 'status' => 190]),
            $this->transaction(['amount' => '4.00', 'refunded_amount' => null, 'status' => '490']),
        ]);

        $this->assertSame(15.5, $this->helper->getGroupTransactionAmount('000000042'));
    }

    public function testAlreadyPaidIsTheGroupTransactionAmount(): void
    {
        $this->modelCollection([$this->transaction(['amount' => '6.25', 'refunded_amount' => null, 'status' => '190'])]);

        $this->assertSame(6.25, $this->helper->getAlreadyPaid('000000042'));
    }

    public function testAlreadyPaidWithoutOrderIsZero(): void
    {
        $this->factory->expects($this->never())->method('create');

        $this->assertSame(0, $this->helper->getAlreadyPaid(null));
    }

    public function testExistingTransactionIsNotSavedTwice(): void
    {
        $existing = $this->transaction(['entity_id' => 3, 'transaction_id' => 'KEY1']);
        $this->modelCollection([], $existing);
        $this->resource->expects($this->never())->method('save');

        $this->assertSame($existing, $this->helper->saveGroupTransaction(['Key' => 'KEY1']));
        $this->assertSame([['transaction_id', 'KEY1']], $this->filters);
    }

    public function testNewTransactionIsSavedFromThePushResponse(): void
    {
        $new = $this->transaction([]);
        $this->modelCollection([], $this->transaction([]), $new);
        $this->resource->expects($this->once())->method('save')->with($this->identicalTo($new));

        $result = $this->helper->saveGroupTransaction([
            'Invoice'         => '000000042',
            'Key'             => 'KEY2',
            'RequiredAction'  => ['PayRemainderDetails' => ['GroupTransaction' => 'GROUP1']],
            'RelatedTransactions' => [['RelatedTransactionKey' => 'OTHER', 'RelationType' => 'partialpayment']],
            'ServiceCode'     => 'fashioncheque',
            'Currency'        => 'EUR',
            'AmountDebit'     => '12.5',
            'Status'          => ['Code' => ['Code' => 190]],
        ]);

        $this->assertSame($new, $result);
        $this->assertSame([
            'order_id'           => '000000042',
            'transaction_id'     => 'KEY2',
            'relatedtransaction' => 'GROUP1',
            'servicecode'        => 'fashioncheque',
            'currency'           => 'EUR',
            'amount'             => '12.50',
            'type'               => 'partialpayment',
            'status'             => 190,
            'created_at'         => '2026-11-25 10:00:00',
        ], $new->getData());
    }

    /**
     * Without a pay-remainder group the related transaction key is used
     */
    public function testNewTransactionFallsBackToTheRelatedTransaction(): void
    {
        $new = $this->transaction([]);
        $this->modelCollection([], $this->transaction([]), $new);

        $this->helper->saveGroupTransaction([
            'Invoice'     => '000000042',
            'Key'         => 'KEY3',
            'RelatedTransactions' => [['RelatedTransactionKey' => 'GROUP2']],
            'ServiceCode' => 'ideal',
            'Currency'    => 'EUR',
            'AmountDebit' => 1234.567,
            'Status'      => ['Code' => ['Code' => 190]],
        ]);

        $this->assertSame('GROUP2', $new->getData('relatedtransaction'));
        $this->assertNull($new->getData('type'));
        $this->assertSame('1234.57', $new->getData('amount'));
    }

    public function testCreateGroupTransactionReturnsTheExistingOne(): void
    {
        $existing = $this->transaction(['entity_id' => 3]);
        $this->modelCollection([], $existing);
        $this->resource->expects($this->never())->method('save');

        $this->assertSame(
            $existing,
            $this->helper->createGroupTransaction('000000042', 'KEY1', 'ideal', 'EUR', 10.0, 190)
        );
    }

    public function testCreateGroupTransactionSavesANewOne(): void
    {
        $new = $this->transaction([]);
        $this->modelCollection([], $this->transaction([]), $new);
        $this->resource->expects($this->once())->method('save')->with($this->identicalTo($new));

        $result = $this->helper->createGroupTransaction('000000042', 'KEY4', 'vvvgiftcard', 'EUR', 7.5, 190, 'GROUP3');

        $this->assertSame($new, $result);
        $this->assertSame([
            'order_id'           => '000000042',
            'transaction_id'     => 'KEY4',
            'relatedtransaction' => 'GROUP3',
            'servicecode'        => 'vvvgiftcard',
            'currency'           => 'EUR',
            'amount'             => '7.50',
            'type'               => 'partialpayment',
            'status'             => 190,
            'created_at'         => '2026-11-25 10:00:00',
        ], $new->getData());
    }

    public function testUpdateGroupTransactionLoadsAndSavesTheRow(): void
    {
        $model = $this->transaction([]);
        $this->factory->method('create')->willReturn($model);
        $this->resource->expects($this->once())->method('load')->with($this->identicalTo($model), 12);
        $this->resource->expects($this->once())->method('save')->with($this->identicalTo($model));

        $result = $this->helper->updateGroupTransaction(['entity_id' => 12, 'refunded_amount' => '3.00']);

        $this->assertSame($model, $result);
        $this->assertSame('3.00', $model->getData('refunded_amount'));
    }

    public function testOriginalTransactionKeyIsTheNewestRelatedTransaction(): void
    {
        $collection = $this->collection([], $this->transaction(['relatedtransaction' => 'GROUP9']));
        $collection->expects($this->once())->method('setOrder')->with('entity_id', 'DESC')->willReturnSelf();
        $this->collectionFactory->method('create')->willReturn($collection);

        $this->assertSame('GROUP9', $this->helper->getGroupTransactionOriginalTransactionKey('000000042'));
        $this->assertSame([['order_id', ['eq' => '000000042']], ['status', ['eq' => '190']]], $this->filters);
    }

    public function testOriginalTransactionKeyIsNullWithoutRows(): void
    {
        $collection = $this->collection([], $this->transaction([]));
        $collection->method('setOrder')->willReturnSelf();
        $this->collectionFactory->method('create')->willReturn($collection);

        $this->assertNull($this->helper->getGroupTransactionOriginalTransactionKey('000000042'));
    }

    public function testOriginalTransactionKeyIsNullWithoutOrder(): void
    {
        $this->collectionFactory->expects($this->never())->method('create');

        $this->assertNull($this->helper->getGroupTransactionOriginalTransactionKey(null));
    }

    public function testItemsNotRefundedAreReindexed(): void
    {
        $first = $this->transaction(['entity_id' => 4]);
        $this->modelCollection([7 => $first]);

        $this->assertSame([$first], $this->helper->getGroupTransactionItemsNotRefunded('000000042'));
        $this->assertSame(
            [['order_id', '000000042'], ['status', '190'], ['refunded_amount', ['null' => true]]],
            $this->filters
        );
    }

    public function testGroupTransactionById(): void
    {
        $item = $this->transaction(['entity_id' => 4]);
        $this->modelCollection([4 => $item]);

        $this->assertSame([4 => $item], $this->helper->getGroupTransactionById(4));
        $this->assertSame([['entity_id', ['eq' => 4]]], $this->filters);
    }

    public function testActiveItemsWithNameJoinTheGiftcardLabel(): void
    {
        $item = $this->transaction(['entity_id' => 4]);
        $collection = $this->collection([4 => $item]);
        $collection->method('getSelect')->willReturn($this->labelJoin());
        $this->collectionFactory->method('create')->willReturn($collection);

        $this->assertSame([4 => $item], $this->helper->getActiveItemsWithName('000000042'));
        $this->assertSame([['order_id', ['eq' => '000000042']], ['status', ['eq' => '190']]], $this->filters);
    }

    public function testActiveItemsWithNameWithoutOrderIsEmpty(): void
    {
        $this->collectionFactory->expects($this->never())->method('create');

        $this->assertSame([], $this->helper->getActiveItemsWithName(null));
    }

    public function testByTransactionIdWithName(): void
    {
        $item = $this->transaction(['entity_id' => 4]);
        $collection = $this->collection([], $item);
        $collection->method('getSelect')->willReturn($this->labelJoin());
        $this->collectionFactory->method('create')->willReturn($collection);

        $this->assertSame($item, $this->helper->getByTransactionIdWithName('KEY1'));
        $this->assertSame([['transaction_id', ['eq' => 'KEY1']]], $this->filters);
    }

    public function testGroupStatusIsSetThroughTheResource(): void
    {
        $this->resource->expects($this->once())
            ->method('updateStatusByRelatedTransaction')
            ->with('GROUP1', '890');

        $this->helper->setGroupTransactionsStatus('GROUP1', '890');
    }

    public function testAdditionalDataOfTheNewestPayment(): void
    {
        $select = $this->createMock(Select::class);
        $select->expects($this->once())->method('from')->with('sales_order_payment')->willReturnSelf();
        $select->expects($this->once())->method('where')->with('parent_id = ?', 42)->willReturnSelf();
        $select->expects($this->once())->method('order')->with('entity_id DESC')->willReturnSelf();
        $select->expects($this->once())->method('limit')->with(1)->willReturnSelf();
        $connection = $this->createMock(AdapterInterface::class);
        $connection->method('select')->willReturn($select);
        $connection->method('fetchRow')->with($select)->willReturn(['additional_information' => '{"a":"b"}']);
        $this->resourceConnection->method('getConnection')->willReturn($connection);
        $this->resourceConnection->method('getTableName')->with('sales_order_payment')->willReturn('sales_order_payment');

        $this->assertSame(['a' => 'b'], $this->helper->getAdditionalData(42));
    }

    public function testAdditionalDataWithoutPaymentIsEmpty(): void
    {
        $select = $this->createMock(Select::class);
        $select->method($this->anything())->willReturnSelf();
        $connection = $this->createMock(AdapterInterface::class);
        $connection->method('select')->willReturn($select);
        $connection->method('fetchRow')->willReturn(false);
        $this->resourceConnection->method('getConnection')->willReturn($connection);

        $this->assertSame([], $this->helper->getAdditionalData(42));
    }

    /**
     * @return Select|MockObject
     */
    private function labelJoin()
    {
        $select = $this->createMock(Select::class);
        $select->expects($this->once())
            ->method('joinLeft')
            ->with(
                ['buckaroo_magento2_giftcard'],
                'main_table.servicecode = buckaroo_magento2_giftcard.servicecode',
                ['buckaroo_magento2_giftcard.label']
            )
            ->willReturnSelf();

        return $select;
    }

    /**
     * The factory gives a model whose collection holds $items, then $created for a new row
     *
     * @param array                 $items
     * @param GroupTransaction|null $firstItem
     * @param GroupTransaction|null $created
     */
    private function modelCollection(array $items, ?GroupTransaction $firstItem = null, ?GroupTransaction $created = null): void
    {
        $model = $this->getMockBuilder(GroupTransaction::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getCollection'])
            ->getMock();
        $model->method('getCollection')->willReturn($this->collection($items, $firstItem));

        $this->factory->method('create')->willReturnOnConsecutiveCalls($model, $created);
    }

    /**
     * @param array                 $items
     * @param GroupTransaction|null $firstItem
     *
     * @return Collection|MockObject
     */
    private function collection(array $items, ?GroupTransaction $firstItem = null)
    {
        $collection = $this->getMockBuilder(Collection::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['addFieldToFilter', 'getItems', 'getFirstItem', 'setOrder', 'getSelect'])
            ->getMock();
        $collection->method('addFieldToFilter')->willReturnCallback(function ($field, $condition) use (&$collection) {
            $this->filters[] = [$field, $condition];
            return $collection;
        });
        $collection->method('getItems')->willReturn($items);
        $collection->method('getFirstItem')->willReturn($firstItem ?? $this->transaction([]));

        return $collection;
    }

    /**
     * @param array $data
     *
     * @return GroupTransaction
     */
    private function transaction(array $data): GroupTransaction
    {
        $transaction = $this->getMockBuilder(GroupTransaction::class)
            ->disableOriginalConstructor()
            ->onlyMethods([])
            ->getMock();
        $transaction->setData($data);

        return $transaction;
    }
}
