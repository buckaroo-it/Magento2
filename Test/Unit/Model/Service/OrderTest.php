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

namespace Buckaroo\Magento2\Test\Unit\Model\Service;

use Buckaroo\Magento2\Helper\Data;
use Buckaroo\Magento2\Logging\BuckarooLoggerInterface;
use Buckaroo\Magento2\Model\ConfigProvider\Account;
use Buckaroo\Magento2\Model\ConfigProvider\Factory;
use Buckaroo\Magento2\Model\ConfigProvider\Method\PayPerEmail;
use Buckaroo\Magento2\Model\ConfigProvider\Method\Transfer;
use Buckaroo\Magento2\Model\ConfigProvider\States;
use Buckaroo\Magento2\Model\OrderStatusFactory;
use Buckaroo\Magento2\Model\Service\Order as OrderService;
use Buckaroo\Magento2\Service\Order\CancelGuard;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Select;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Payment;
use Magento\Sales\Model\ResourceModel\Order\Collection;
use Magento\Sales\Model\ResourceModel\Order\CollectionFactory;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Api\StoreRepositoryInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Cancelling failed or expired orders, and the expiry crons for transfer and Pay Per Email.
 *
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class OrderTest extends TestCase
{
    /**
     * @var Account|MockObject
     */
    private $accountConfig;

    /**
     * @var Factory|MockObject
     */
    private $methodFactory;

    /**
     * @var Factory|MockObject
     */
    private $configProviderFactory;

    /**
     * @var StoreRepositoryInterface|MockObject
     */
    private $storeRepository;

    /**
     * @var CollectionFactory|MockObject
     */
    private $collectionFactory;

    /**
     * @var OrderStatusFactory|MockObject
     */
    private $orderStatusFactory;

    /**
     * @var OrderRepositoryInterface|MockObject
     */
    private $orderRepository;

    protected function setUp(): void
    {
        $this->accountConfig = $this->createMock(Account::class);
        $this->methodFactory = $this->createMock(Factory::class);
        $this->configProviderFactory = $this->createMock(Factory::class);
        $this->storeRepository = $this->createMock(StoreRepositoryInterface::class);
        $this->collectionFactory = $this->createMock(CollectionFactory::class);
        $this->orderStatusFactory = $this->createMock(OrderStatusFactory::class);
        $this->orderRepository = $this->createMock(OrderRepositoryInterface::class);
        RequestOnVoidMethod::$requestOnVoid = true;
    }

    public function testACancellableOrderIsCancelledWithTheFailedStatus(): void
    {
        $order = $this->makeOrder('buckaroo_magento2_ideal', 'TX-KEY');
        $order->expects($this->once())->method('cancel');
        $order->expects($this->once())->method('setState')->with(Order::STATE_CANCELED);
        $order->expects($this->once())->method('setStatus')->with('buckaroo_failed');
        $order->expects($this->once())->method('addCommentToStatusHistory')
            ->with('Transfer expired', 'buckaroo_failed', false);

        $this->accountConfig->method('getCancelOnFailed')->willReturn(true);
        $this->orderStatusFactory->expects($this->once())->method('get')->with(690, $order)
            ->willReturn('buckaroo_failed');
        $this->orderRepository->expects($this->once())->method('save')->with($order);

        $this->assertTrue($this->service()->cancel($order, 690, 'Transfer expired'));
    }

    public function testWithoutAMessageTheDefaultCommentIsUsed(): void
    {
        $order = $this->makeOrder('buckaroo_magento2_ideal', 'TX-KEY');
        $order->expects($this->once())->method('addCommentToStatusHistory')
            ->with('Order canceled due to payment failure', 'canceled', false);

        $this->accountConfig->method('getCancelOnFailed')->willReturn(true);
        $this->orderStatusFactory->method('get')->willReturn('canceled');

        $this->assertTrue($this->service()->cancel($order, 490));
    }

    public function testWithoutAFailedStatusTheOrderIsCancelledAndSavedAsIs(): void
    {
        $order = $this->makeOrder('buckaroo_magento2_ideal', 'TX-KEY');
        $order->expects($this->once())->method('cancel');
        $order->expects($this->never())->method('setState');
        $order->expects($this->never())->method('setStatus');
        $order->expects($this->never())->method('addCommentToStatusHistory');

        $this->accountConfig->method('getCancelOnFailed')->willReturn(true);
        $this->orderStatusFactory->method('get')->willReturn(false);
        $this->orderRepository->expects($this->once())->method('save')->with($order);

        $this->assertTrue($this->service()->cancel($order, null));
    }

    public function testAnAlreadyCancelledOrderIsLeftAsItIs(): void
    {
        $order = $this->makeOrder('buckaroo_magento2_ideal', 'TX-KEY', Order::STATE_CANCELED);
        $order->expects($this->never())->method('cancel');
        $this->accountConfig->expects($this->never())->method('getCancelOnFailed');
        $this->orderRepository->expects($this->never())->method('save');

        $this->assertTrue($this->service()->cancel($order, 690));
    }

    public function testWithCancelOnFailedOffTheOrderStaysOpen(): void
    {
        $order = $this->makeOrder('buckaroo_magento2_ideal', 'TX-KEY');
        $order->expects($this->never())->method('cancel');
        $this->accountConfig->expects($this->once())->method('getCancelOnFailed')
            ->with($order->getStore())
            ->willReturn(false);
        $this->orderRepository->expects($this->never())->method('save');

        $this->assertTrue($this->service()->cancel($order, 690));
    }

    public function testAnOrderThatCannotBeCancelledIsReported(): void
    {
        $order = $this->makeOrder('buckaroo_magento2_ideal', 'TX-KEY', Order::STATE_PROCESSING, false);
        $order->expects($this->never())->method('cancel');
        $this->accountConfig->method('getCancelOnFailed')->willReturn(true);
        $this->orderRepository->expects($this->never())->method('save');

        $this->assertFalse($this->service()->cancel($order, 690));
    }

    /**
     * A Pay Per Email order is cancelled even when Magento would not allow it.
     */
    public function testAPayPerEmailOrderIsCancelledEvenWhenMagentoRefuses(): void
    {
        $order = $this->makeOrder('buckaroo_magento2_payperemail', 'TX-KEY', Order::STATE_NEW, false);
        $order->expects($this->once())->method('cancel');
        $this->accountConfig->method('getCancelOnFailed')->willReturn(true);
        $this->orderRepository->expects($this->once())->method('save');

        $this->assertTrue($this->service()->cancel($order, 690));
    }

    public static function voidRequestProvider(): array
    {
        return [
            'transaction to void'          => ['buckaroo_magento2_ideal', 'TX-KEY', true],
            'no transaction key'           => ['buckaroo_magento2_ideal', '', false],
            'klarna kp is never voided'    => ['buckaroo_magento2_klarnakp', 'TX-KEY', false],
        ];
    }

    /**
     * The gateway is only asked to void a transaction that exists; the flag is put back after.
     */
    #[DataProvider('voidRequestProvider')]
    public function testTheVoidRequestIsOnlySentForAnExistingTransaction(
        string $method,
        string $transactionKey,
        bool $expectedDuringCancel
    ): void {
        $order = $this->makeOrder($method, $transactionKey);
        $during = null;
        $order->method('cancel')->willReturnCallback(function () use (&$during, $order) {
            $during = RequestOnVoidMethod::$requestOnVoid;
            return $order;
        });
        $this->accountConfig->method('getCancelOnFailed')->willReturn(true);

        $this->service()->cancel($order, 690);

        $this->assertSame($expectedDuringCancel, $during);
        $this->assertTrue(RequestOnVoidMethod::$requestOnVoid);
    }

    public function testTheVoidFlagIsRestoredWhenCancellingFails(): void
    {
        $order = $this->makeOrder('buckaroo_magento2_klarnakp', 'TX-KEY');
        $order->method('cancel')->willThrowException(new \RuntimeException('cancel failed'));
        $this->accountConfig->method('getCancelOnFailed')->willReturn(true);

        try {
            $this->service()->cancel($order, 690);
            $this->fail('The cancel failure must reach the caller');
        } catch (\RuntimeException $e) {
            $this->assertSame('cancel failed', $e->getMessage());
        }

        $this->assertTrue(RequestOnVoidMethod::$requestOnVoid);
    }

    /**
     * A saved order is cancelled under the order lock, on the fresh copy.
     */
    public function testASavedOrderIsCancelledUnderTheLockOnTheFreshOrder(): void
    {
        $stale = $this->createMock(Order::class);
        $stale->method('getId')->willReturn('42');
        $stale->expects($this->never())->method('cancel');

        $fresh = $this->makeOrder('buckaroo_magento2_ideal', 'TX-KEY');
        $fresh->expects($this->once())->method('cancel');
        $this->accountConfig->method('getCancelOnFailed')->willReturn(true);

        $guard = $this->makeGuard($fresh, null);
        $guard->expects($this->once())->method('runLocked')->with(42, 'payment failed or expired');

        $this->assertTrue($this->service($guard)->cancel($stale, 690));
    }

    public function testAnOrderAPushHasPaidMeanwhileIsNotCancelled(): void
    {
        $stale = $this->createMock(Order::class);
        $stale->method('getId')->willReturn(42);

        $fresh = $this->makeOrder('buckaroo_magento2_ideal', 'TX-KEY');
        $fresh->expects($this->never())->method('cancel');
        $this->accountConfig->method('getCancelOnFailed')->willReturn(true);
        $this->orderRepository->expects($this->never())->method('save');

        $this->assertFalse($this->service($this->makeGuard($fresh, 'the order has been paid'))->cancel($stale, 690));
    }

    public function testAPayPerEmailOrderIsCancelledDespiteABlocker(): void
    {
        $stale = $this->createMock(Order::class);
        $stale->method('getId')->willReturn(42);

        $fresh = $this->makeOrder('buckaroo_magento2_payperemail', 'TX-KEY');
        $fresh->expects($this->once())->method('cancel');
        $this->accountConfig->method('getCancelOnFailed')->willReturn(true);

        $this->assertTrue($this->service($this->makeGuard($fresh, 'the order has been paid'))->cancel($stale, 690));
    }

    public function testAFreshOrderAlreadyCancelledNeedsNoBlockerCheck(): void
    {
        $stale = $this->createMock(Order::class);
        $stale->method('getId')->willReturn(42);

        $fresh = $this->makeOrder('buckaroo_magento2_ideal', 'TX-KEY', Order::STATE_CANCELED);
        $guard = $this->makeGuard($fresh, 'the order has been paid');
        $guard->expects($this->never())->method('getCancelBlocker');

        $this->assertTrue($this->service($guard)->cancel($stale, 690));
    }

    public function testALockThatCouldNotBeTakenReportsNotCancelled(): void
    {
        $stale = $this->createMock(Order::class);
        $stale->method('getId')->willReturn(42);

        $guard = $this->createMock(CancelGuard::class);
        $guard->method('runLocked')->willReturn(null);

        $this->assertFalse($this->service($guard)->cancel($stale, 690));
    }

    public function testExpiredTransferOrdersWithinTheWindowAreCancelled(): void
    {
        $store = $this->makeStore(3);
        $this->storeRepository->method('getList')->willReturn([$store]);

        $transfer = $this->createMock(Transfer::class);
        $transfer->method('getDueDate')->with($store)->willReturn('-5');
        $this->methodFactory->method('get')->with('transfer')->willReturn($transfer);
        $this->mockStates('new', 'pending_payment');

        $first = $this->createMock(Order::class);
        $second = $this->createMock(Order::class);
        [$collection, $filters, $where] = $this->makeCollection([$first, $second]);
        $this->collectionFactory->expects($this->once())->method('create')->willReturn($collection);

        $service = $this->serviceExpectingCancels([
            [$first, 690, 'Order automatically canceled due to expired transfer payment'],
            [$second, 690, 'Order automatically canceled due to expired transfer payment'],
        ]);

        $this->assertSame($service, $service->cancelExpiredTransferOrders());
        $this->assertSame(
            [
                ['state', ['in' => ['new', 'pending_payment']]],
                ['store_id', ['eq' => 3]],
                ['created_at', ['lt' => 'NOW() - INTERVAL 5 DAY']],
                ['created_at', ['gt' => 'NOW() - INTERVAL 12 DAY']],
            ],
            $filters->getArrayCopy()
        );
        $this->assertSame([['p.method = ?', 'buckaroo_magento2_transfer']], $where->getArrayCopy());
    }

    public function testATransferWithoutDueDaysCancelsNothing(): void
    {
        $this->storeRepository->method('getList')->willReturn([$this->makeStore(1)]);
        $transfer = $this->createMock(Transfer::class);
        $transfer->method('getDueDate')->willReturn('0');
        $this->methodFactory->method('get')->willReturn($transfer);
        $this->collectionFactory->expects($this->never())->method('create');

        $this->serviceExpectingCancels([])->cancelExpiredTransferOrders();
    }

    public function testNoExpiredOrdersMeansNothingIsCancelled(): void
    {
        $this->storeRepository->method('getList')->willReturn([$this->makeStore(1)]);
        $transfer = $this->createMock(Transfer::class);
        $transfer->method('getDueDate')->willReturn(5);
        $this->methodFactory->method('get')->willReturn($transfer);
        $this->mockStates('new', 'new');

        [$collection, $filters] = $this->makeCollection([]);
        $this->collectionFactory->method('create')->willReturn($collection);

        $this->serviceExpectingCancels([])->cancelExpiredTransferOrders();

        $this->assertSame(['state', ['in' => ['new']]], $filters[0]);
    }

    public function testExpiredPayPerEmailOrdersAreCancelledPerStore(): void
    {
        $storeA = $this->makeStore(1);
        $storeB = $this->makeStore(2);
        $this->storeRepository->method('getList')->willReturn([$storeA, $storeB]);

        $ppe = $this->createMock(PayPerEmail::class);
        $ppe->method('getEnabledCronCancelPPE')->willReturnMap([[$storeA, true], [$storeB, false]]);
        $ppe->method('getExpireDays')->willReturn('10');
        $this->methodFactory->method('get')->with('payperemail')->willReturn($ppe);
        $this->mockStates(null, 'pending_payment');

        $order = $this->createMock(Order::class);
        [$collection, $filters, $where] = $this->makeCollection([$order]);
        $this->collectionFactory->expects($this->once())->method('create')->willReturn($collection);

        $service = $this->serviceExpectingCancels([
            [$order, 690, 'Order automatically canceled due to expired Pay Per Email payment'],
        ]);

        $this->assertSame($service, $service->cancelExpiredPPEOrders());
        $this->assertSame(
            [
                ['state', ['in' => ['pending_payment']]],
                ['store_id', ['eq' => 1]],
                ['created_at', ['lt' => 'NOW() - INTERVAL 10 DAY']],
                ['created_at', ['gt' => 'NOW() - INTERVAL 17 DAY']],
            ],
            $filters->getArrayCopy()
        );
        $this->assertSame(
            [['p.additional_information like "%isPayPerEmail%" OR p.method ="buckaroo_magento2_payperemail"', null]],
            $where->getArrayCopy()
        );
    }

    public function testPayPerEmailWithoutExpireDaysCancelsNothing(): void
    {
        $this->storeRepository->method('getList')->willReturn([$this->makeStore(1)]);
        $ppe = $this->createMock(PayPerEmail::class);
        $ppe->method('getEnabledCronCancelPPE')->willReturn(true);
        $ppe->method('getExpireDays')->willReturn(null);
        $this->methodFactory->method('get')->willReturn($ppe);
        $this->collectionFactory->expects($this->never())->method('create');

        $this->serviceExpectingCancels([])->cancelExpiredPPEOrders();
    }

    /**
     * @param CancelGuard|null $guard
     *
     * @return OrderService
     */
    private function service(?CancelGuard $guard = null): OrderService
    {
        $service = new OrderService(...$this->constructorArgs());
        $this->injectGuard($service, $guard);

        return $service;
    }

    /**
     * A service whose cancel() is replaced, to see which orders the crons cancel.
     *
     * @param array $expected list of [order, status code, message]
     *
     * @return OrderService|MockObject
     */
    private function serviceExpectingCancels(array $expected)
    {
        $service = $this->getMockBuilder(OrderService::class)
            ->setConstructorArgs($this->constructorArgs())
            ->onlyMethods(['cancel'])
            ->getMock();

        $calls = [];
        $service->expects($this->exactly(count($expected)))->method('cancel')
            ->willReturnCallback(function ($order, $code, $message) use (&$calls) {
                $calls[] = [$order, $code, $message];
                return true;
            });
        $this->registerAssertion(static function () use (&$calls, $expected) {
            TestCase::assertSame($expected, $calls);
        });

        return $service;
    }

    /**
     * @var callable[]
     */
    private array $deferredAssertions = [];

    /**
     * @param callable $assertion
     *
     * @return void
     */
    private function registerAssertion(callable $assertion): void
    {
        $this->deferredAssertions[] = $assertion;
    }

    protected function assertPostConditions(): void
    {
        foreach ($this->deferredAssertions as $assertion) {
            $assertion();
        }
    }

    /**
     * @return array
     */
    private function constructorArgs(): array
    {
        $helper = $this->createMock(Data::class);
        $helper->method('getStatusCode')->willReturnMap([['BUCKAROO_MAGENTO2_STATUSCODE_REJECTED', 690]]);

        $resource = $this->createMock(ResourceConnection::class);
        $resource->method('getTableName')->with('sales_order_payment')->willReturn('sales_order_payment');

        return [
            $this->accountConfig,
            $this->methodFactory,
            $this->configProviderFactory,
            $this->storeRepository,
            $this->collectionFactory,
            $this->orderStatusFactory,
            $helper,
            $this->createMock(BuckarooLoggerInterface::class),
            $resource,
            $this->orderRepository,
        ];
    }

    /**
     * @param OrderService     $service
     * @param CancelGuard|null $guard
     *
     * @return void
     */
    private function injectGuard(OrderService $service, ?CancelGuard $guard): void
    {
        if ($guard === null) {
            return;
        }
        $property = new \ReflectionProperty(OrderService::class, 'cancelGuard');
        $property->setAccessible(true);
        $property->setValue($service, $guard);
    }

    /**
     * @param Order       $fresh
     * @param string|null $blocker
     *
     * @return CancelGuard|MockObject
     */
    private function makeGuard(Order $fresh, ?string $blocker)
    {
        $guard = $this->createMock(CancelGuard::class);
        $guard->method('runLocked')->willReturnCallback(
            static fn(...$args) => $args[2]($fresh)
        );
        $guard->method('getCancelBlocker')->willReturn($blocker);

        return $guard;
    }

    /**
     * An unsaved order (no id), so cancel() works on it directly.
     *
     * @param string $method
     * @param string $transactionKey
     * @param string $state
     * @param bool   $canCancel
     *
     * @return Order|MockObject
     */
    private function makeOrder(
        string $method,
        string $transactionKey,
        string $state = Order::STATE_NEW,
        bool $canCancel = true
    ) {
        $payment = $this->createMock(Payment::class);
        $payment->method('getMethod')->willReturn($method);
        $payment->method('getMethodInstance')->willReturn(new RequestOnVoidMethod());
        $payment->method('getAdditionalInformation')->willReturnMap([
            ['buckaroo_original_transaction_key', $transactionKey],
        ]);

        $order = $this->createMock(Order::class);
        $order->method('getId')->willReturn(null);
        $order->method('getPayment')->willReturn($payment);
        $order->method('getState')->willReturn($state);
        $order->method('canCancel')->willReturn($canCancel);
        $order->method('getStore')->willReturn($this->makeStore(1));

        return $order;
    }

    /**
     * @param int $storeId
     *
     * @return StoreInterface|MockObject
     */
    private function makeStore(int $storeId)
    {
        $store = $this->createMock(StoreInterface::class);
        $store->method('getId')->willReturn($storeId);

        return $store;
    }

    /**
     * @param string|null $new
     * @param string|null $pending
     *
     * @return void
     */
    private function mockStates(?string $new, ?string $pending): void
    {
        $states = $this->createMock(States::class);
        $states->method('getOrderStateNew')->willReturn($new);
        $states->method('getOrderStatePending')->willReturn($pending);
        $this->configProviderFactory->method('get')->with('states')->willReturn($states);
    }

    /**
     * A collection that records its filters and where clause.
     *
     * @param Order[] $orders
     *
     * @return array{0: Collection|MockObject, 1: \ArrayObject, 2: \ArrayObject}
     */
    private function makeCollection(array $orders): array
    {
        $filters = new \ArrayObject();
        $where = new \ArrayObject();

        $select = $this->createMock(Select::class);
        $select->method('join')->with(
            ['p' => 'sales_order_payment'],
            'main_table.entity_id = p.parent_id',
            ['method']
        )->willReturnSelf();
        $select->method('where')->willReturnCallback(function ($condition, $value = null) use ($where, $select) {
            $where[] = [$condition, $value];
            return $select;
        });

        $collection = $this->createMock(Collection::class);
        $collection->method('addFieldToSelect')->with(['*'])->willReturnSelf();
        $collection->method('addFieldToFilter')->willReturnCallback(
            function ($field, $condition) use ($filters, $collection) {
                $filters[] = [$field, array_map(
                    static fn($value) => $value instanceof \Zend_Db_Expr || $value instanceof \Stringable
                        ? (string)$value
                        : $value,
                    $condition
                )];
                return $collection;
            }
        );
        $collection->method('getSelect')->willReturn($select);
        $collection->method('count')->willReturn(count($orders));
        $collection->method('getIterator')->willReturn(new \ArrayIterator($orders));

        return [$collection, $filters, $where];
    }
}

/**
 * Payment method double with the static void flag the service toggles.
 */
// phpcs:ignore PSR1.Classes.ClassDeclaration.MultipleClasses
class RequestOnVoidMethod
{
    /**
     * @var bool
     */
    public static $requestOnVoid = true;
}
