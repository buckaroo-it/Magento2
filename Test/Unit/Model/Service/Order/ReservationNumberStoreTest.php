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

namespace Buckaroo\Magento2\Test\Unit\Model\Service\Order;

use Buckaroo\Magento2\Logging\BuckarooLoggerInterface;
use Buckaroo\Magento2\Model\Service\Order\ReservationNumberStore;
use Magento\Framework\Api\SearchCriteria;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Sales\Api\Data\TransactionSearchResultInterface;
use Magento\Sales\Api\OrderPaymentRepositoryInterface;
use Magento\Sales\Api\TransactionRepositoryInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Payment;
use Magento\Sales\Model\Order\Payment\Transaction;
use Magento\Sales\Model\ResourceModel\Order as OrderResource;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * The reservation number reaches us on the push, long after the order row is visible to other
 * processes, and any full save of a stale order silently reverts it. These tests pin the two
 * behaviours that protect the capture: the number is written to three independent rows, and it is
 * read back from whichever row still holds it.
 *
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class ReservationNumberStoreTest extends TestCase
{
    private const NUMBER = '73c4913d-ceb6-48da-8128-2a35e909ed83';

    /**
     * @var OrderResource|MockObject
     */
    private $orderResource;

    /**
     * @var OrderPaymentRepositoryInterface|MockObject
     */
    private $paymentRepository;

    /**
     * @var TransactionRepositoryInterface|MockObject
     */
    private $transactionRepository;

    /**
     * @var SearchCriteriaBuilder|MockObject
     */
    private $searchCriteriaBuilder;

    /**
     * @var ReservationNumberStore
     */
    private ReservationNumberStore $store;

    protected function setUp(): void
    {
        $this->orderResource = $this->createMock(OrderResource::class);
        $this->paymentRepository = $this->createMock(OrderPaymentRepositoryInterface::class);
        $this->transactionRepository = $this->createMock(TransactionRepositoryInterface::class);
        $this->searchCriteriaBuilder = $this->createMock(SearchCriteriaBuilder::class);

        $this->searchCriteriaBuilder->method('addFilter')->willReturnSelf();
        $this->searchCriteriaBuilder->method('setPageSize')->willReturnSelf();
        $this->searchCriteriaBuilder->method('create')->willReturn($this->createMock(SearchCriteria::class));

        $this->store = new ReservationNumberStore(
            $this->orderResource,
            $this->paymentRepository,
            $this->transactionRepository,
            $this->searchCriteriaBuilder,
            $this->createMock(BuckarooLoggerInterface::class)
        );
    }

    public function testSaveWritesTheNumberToAllThreeLocations(): void
    {
        $transaction = $this->createMock(Transaction::class);
        $payment = $this->createMock(Payment::class);
        $order = $this->makeOrder($payment);

        $this->givenTransactionsFound([$transaction]);

        $transaction->expects($this->once())
            ->method('setAdditionalInformation')
            ->with(ReservationNumberStore::KEY, self::NUMBER);
        $this->transactionRepository->expects($this->once())->method('save')->with($transaction);

        $payment->expects($this->once())
            ->method('setAdditionalInformation')
            ->with(ReservationNumberStore::KEY, self::NUMBER);
        $this->paymentRepository->expects($this->once())->method('save')->with($payment);

        $order->expects($this->once())->method('setData')->with(ReservationNumberStore::KEY, self::NUMBER);

        // A full save would rewrite every column of sales_order from this in-memory copy, which is
        // exactly the behaviour that loses the number. Only the one column may be written.
        $this->orderResource->expects($this->once())
            ->method('saveAttribute')
            ->with($order, 'buckaroo_reservation_number');

        $this->store->save($order, self::NUMBER);
    }

    public function testSaveStillWritesTheOtherCopiesWhenOneLocationFails(): void
    {
        $transaction = $this->createMock(Transaction::class);
        $payment = $this->createMock(Payment::class);
        $order = $this->makeOrder($payment);

        $this->givenTransactionsFound([$transaction]);

        $this->transactionRepository->method('save')->willThrowException(new \RuntimeException('gone'));

        $this->paymentRepository->expects($this->once())->method('save')->with($payment);
        $this->orderResource->expects($this->once())->method('saveAttribute');

        $this->store->save($order, self::NUMBER);
    }

    public function testResolvePrefersTheOrderColumn(): void
    {
        $payment = $this->createMock(Payment::class);
        $order = $this->makeOrder($payment, self::NUMBER);

        $payment->expects($this->never())->method('getAdditionalInformation');
        $this->transactionRepository->expects($this->never())->method('getList');

        $this->assertSame(self::NUMBER, $this->store->resolve($order));
    }

    public function testResolveFallsBackToThePaymentWhenTheOrderColumnWasOverwritten(): void
    {
        $payment = $this->createMock(Payment::class);
        $payment->method('getAdditionalInformation')
            ->with(ReservationNumberStore::KEY)
            ->willReturn(self::NUMBER);

        $order = $this->makeOrder($payment, null);

        $this->transactionRepository->expects($this->never())->method('getList');

        $this->assertSame(self::NUMBER, $this->store->resolve($order));
    }

    public function testResolveFallsBackToTheTransactionWhenOrderAndPaymentWereBothOverwritten(): void
    {
        $transaction = $this->createMock(Transaction::class);
        $transaction->method('getAdditionalInformation')
            ->with(ReservationNumberStore::KEY)
            ->willReturn(self::NUMBER);

        $payment = $this->createMock(Payment::class);
        $payment->method('getAdditionalInformation')->willReturn(null);

        $order = $this->makeOrder($payment, null);
        $this->givenTransactionsFound([$transaction]);

        $this->assertSame(self::NUMBER, $this->store->resolve($order));
    }

    public function testResolveReturnsNullWhenNoCopySurvived(): void
    {
        $payment = $this->createMock(Payment::class);
        $payment->method('getAdditionalInformation')->willReturn(null);

        $order = $this->makeOrder($payment, null);
        $this->givenTransactionsFound([]);

        $this->assertNull($this->store->resolve($order));
    }

    /**
     * @param Payment|MockObject $payment
     * @param string|null        $reservationNumber
     *
     * @return Order|MockObject
     */
    private function makeOrder($payment, ?string $reservationNumber = null)
    {
        // The store reads and writes the column with getData()/setData(), which are real methods
        // on the model - no magic accessors to stub, so a plain Order mock is enough.
        $order = $this->getMockBuilder(Order::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getId', 'getIncrementId', 'getPayment', 'getData', 'setData'])
            ->getMock();

        $order->method('getId')->willReturn(1);
        $order->method('getIncrementId')->willReturn('100000001');
        $order->method('getPayment')->willReturn($payment);
        $order->method('getData')->with(ReservationNumberStore::KEY)->willReturn($reservationNumber);

        return $order;
    }

    /**
     * @param array $transactions
     *
     * @return void
     */
    private function givenTransactionsFound(array $transactions): void
    {
        $result = $this->createMock(TransactionSearchResultInterface::class);
        $result->method('getItems')->willReturn($transactions);
        $this->transactionRepository->method('getList')->willReturn($result);
    }
}
