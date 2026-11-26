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

namespace Buckaroo\Magento2\Test\Unit\Service;

use Buckaroo\Magento2\Helper\PaymentGroupTransaction;
use Buckaroo\Magento2\Logging\BuckarooLoggerInterface;
use Buckaroo\Magento2\Model\Adapter\BuckarooAdapter;
use Buckaroo\Magento2\Model\GroupTransaction;
use Buckaroo\Magento2\Model\ResourceModel\Giftcard\Collection as GiftcardCollection;
use Buckaroo\Magento2\Model\ResourceModel\GroupTransaction as GroupTransactionResource;
use Buckaroo\Magento2\Service\RefundGroupTransactionService;
use Buckaroo\Transaction\Response\TransactionResponse;
use Magento\Framework\App\ObjectManager as AppObjectManager;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\DataObject;
use Magento\Framework\ObjectManagerInterface;
use Magento\Payment\Gateway\Http\ClientException;
use Buckaroo\Magento2\Test\Unit\Stubs\OrderAdapterInterfaceStub;
use Magento\Payment\Gateway\Data\PaymentDataObjectInterface;
use Magento\Payment\Gateway\Http\ClientInterface;
use Magento\Payment\Gateway\Http\TransferFactoryInterface;
use Magento\Payment\Gateway\Http\TransferInterface;
use Magento\Payment\Gateway\Request\BuilderInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Payment;
use PHPUnit\Framework\MockObject\MockObject;

/**
 * `PaymentGroupTransaction::getGroupTransactionByTrxId()` returns ONE model. Iterating it with
 * foreach walks the model's (non-existent) public properties, so a successful giftcard refund
 * never recorded `refunded_amount`, and a later partial refund offered the same giftcard again.
 *
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class RefundGroupTransactionServiceTest extends \Buckaroo\Magento2\Test\BaseTest
{
    protected $instanceClass = RefundGroupTransactionService::class;

    /**
     * @var PaymentGroupTransaction|MockObject
     */
    private $paymentGroupTransaction;

    /**
     * @var GroupTransactionResource|MockObject
     */
    private $groupTransactionResource;

    /**
     * @var RefundGroupTransactionService
     */
    private $service;

    /**
     * @var array Refund requests sent, as [payment_method, amountCredit, originalTransactionKey]
     */
    private $sent = [];

    /**
     * @var array Refund requests sent, in full
     */
    private $requests = [];

    /**
     * @var RequestInterface|MockObject
     */
    private $request;

    /**
     * @var bool whether Buckaroo accepts the refund requests
     */
    private $refundAccepted = true;

    /**
     * @var bool[] per request whether Buckaroo accepts it, before falling back to $refundAccepted
     */
    private $refundResults = [];

    /**
     * @var mixed object manager instance before the test
     */
    private $previousObjectManager;

    public function setUp(): void
    {
        parent::setUp();

        $this->paymentGroupTransaction = $this->getFakeMock(PaymentGroupTransaction::class)->getMock();
        $this->groupTransactionResource = $this->getFakeMock(GroupTransactionResource::class)->getMock();

        $requestDataBuilder = $this->getFakeMock(BuilderInterface::class)->getMock();
        $requestDataBuilder->method('build')->willReturn([]);

        $transferFactory = $this->getFakeMock(TransferFactoryInterface::class)->getMock();
        $this->sent = [];
        $this->requests = [];
        $this->refundAccepted = true;
        $this->refundResults = [];
        $transferFactory->method('create')->willReturnCallback(function (array $request) {
            $this->sent[] = [$request['payment_method'], (float)$request['amountCredit'], $request['originalTransactionKey']];
            $this->requests[] = $request;
            return $this->getFakeMock(TransferInterface::class)->getMock();
        });

        $client = $this->getFakeMock(ClientInterface::class)->getMock();
        $client->method('placeRequest')->willReturnCallback(function () {
            $accepted = $this->refundResults ? array_shift($this->refundResults) : $this->refundAccepted;
            $response = $this->getFakeMock(TransactionResponse::class)->getMock();
            $response->method('isSuccess')->willReturn($accepted);
            $response->method('getStatusCode')->willReturn($accepted ? 190 : 490);
            $response->method('getSomeError')->willReturn('Refund rejected');
            return ['object' => $response];
        });

        $this->request = $this->getFakeMock(RequestInterface::class)->getMock();
        $giftcardCollection = $this->getFakeMock(GiftcardCollection::class)->getMock();
        $giftcardCollection->method('getItemByColumnValue')->willReturnCallback(
            static function ($column, $value) {
                $giftcards = ['fashioncheque', 'FashionCheque', 'vvvgiftcard'];
                return $column === 'servicecode' && in_array($value, $giftcards, true)
                    ? new DataObject(['label' => $value])
                    : null;
            }
        );

        $this->previousObjectManager = $this->getObjectManagerInstance();

        $this->service = new RefundGroupTransactionService(
            $this->paymentGroupTransaction,
            $this->getFakeMock(BuckarooLoggerInterface::class)->getMock(),
            $this->request,
            $requestDataBuilder,
            $transferFactory,
            $client,
            $giftcardCollection,
            $this->groupTransactionResource
        );
        $this->service->setAmountLeftToRefund(40.05);
    }

    protected function tearDown(): void
    {
        $property = new \ReflectionProperty(AppObjectManager::class, '_instance');
        $property->setValue(null, $this->previousObjectManager);
        parent::tearDown();
    }

    public function testASuccessfulGiftcardRefundIsRecordedOnItsGroupTransaction(): void
    {
        $groupTransaction = $this->groupTransaction(null);

        $this->groupTransactionResource->expects($this->once())->method('save')->with($groupTransaction);

        $this->service->createRefundGroupRequest([], 'B1DA75BE2400480B86D1D37817B81DB1|fashioncheque|10.00', 10.00);

        $this->assertSame(10.0, $groupTransaction->getRefundedAmount());
        $this->assertEqualsWithDelta(30.05, $this->service->getAmountLeftToRefund(), 0.001);
    }

    public function testAPartialGiftcardRefundAddsToWhatWasRefundedBefore(): void
    {
        $groupTransaction = $this->groupTransaction(4.00);

        $this->groupTransactionResource->expects($this->once())->method('save')->with($groupTransaction);

        $this->service->createRefundGroupRequest([], 'B1DA75BE2400480B86D1D37817B81DB1|fashioncheque|10.00', 6.00);

        $this->assertSame(10.0, $groupTransaction->getRefundedAmount());
    }

    /**
     * A posted amount above what is left on the transaction (a form opened before an earlier
     * partial refund) is credited only up to the remaining amount
     */
    public function testARefundIsCappedAtWhatIsLeftOnTheTransaction(): void
    {
        $groupTransaction = $this->groupTransaction(5.00);

        $this->service->createRefundGroupRequest([], 'B1DA75BE2400480B86D1D37817B81DB1|fashioncheque|10.00', 10.00);

        $this->assertSame(10.0, $groupTransaction->getRefundedAmount());
        $this->assertEqualsWithDelta(35.05, $this->service->getAmountLeftToRefund(), 0.001);
    }

    /**
     * A voucher has no row in the giftcard table. When it was not recognised here, an automatic
     * refund skipped it and took its amount off the remaining refund, so it was never paid back.
     */
    public function testAVoucherIsRefundedOnItsOwnGroupTransaction(): void
    {
        $voucher = $this->getFakeMock(GroupTransaction::class)->onlyMethods([])->getMock();
        $voucher->setData([
            'entity_id'       => 31,
            'transaction_id'  => 'C0FFEE00C0FFEE00C0FFEE00C0FFEE00',
            'servicecode'     => 'buckaroovoucher',
            'amount'          => 10.00,
            'refunded_amount' => null,
            'status'          => '190',
        ]);
        // No credit memo rows are posted (API refund), so the group transactions are read
        $this->paymentGroupTransaction->method('getAnyGroupTransactionItems')->with('000000077')->willReturn([$voucher]);
        $this->paymentGroupTransaction->method('getGroupTransactionByTrxId')
            ->with('C0FFEE00C0FFEE00C0FFEE00C0FFEE00')
            ->willReturn($voucher);
        $this->groupTransactionResource->expects($this->once())->method('save')->with($voucher);

        $buildSubject = ['payment' => $this->createPaymentDataObject(10.00), 'amount' => 10.00];
        $leftForPrimaryMethod = $this->service->refundGroupTransactions($buildSubject);

        $this->assertSame([['buckaroovoucher', 10.0, 'C0FFEE00C0FFEE00C0FFEE00C0FFEE00']], $this->sent);
        $this->assertSame(10.0, $voucher->getRefundedAmount());
        $this->assertSame(0, $leftForPrimaryMethod);
        $this->assertTrue($buildSubject['response']['group_transaction_refund_complete']);
    }

    /**
     * Credit memo form flow: the posted giftcard amount is refunded on the giftcard, the rest on
     * the other payment of the order (service code stored in upper case).
     */
    public function testPostedGiftcardIsRefundedAndRestGoesToTheOtherPayment(): void
    {
        $this->groupTransaction(null);
        $this->postGiftcards(['B1DA75BE2400480B86D1D37817B81DB1|fashioncheque|10.00' => '10.00']);
        $this->paymentGroupTransaction->method('getAnyGroupTransactionItems')
            ->willReturn([$this->row('IDEAL', 'IDKEY', 30.05)]);

        $buildSubject = ['payment' => $this->createPaymentDataObject(40.05), 'amount' => '40.05'];
        $left = $this->service->refundGroupTransactions($buildSubject);

        $this->assertSame(0, $left);
        $this->assertTrue($buildSubject['response']['group_transaction_refund_complete']);
        $this->assertSentRefunds([
            ['fashioncheque', 10.00, 'B1DA75BE2400480B86D1D37817B81DB1'],
            ['ideal', 30.05, 'IDKEY'],
        ]);
        $this->assertSame('fashioncheque', $this->requests[0]['name']);
        $this->assertSame('ideal', $this->requests[1]['name']);
    }

    /**
     * Without any giftcard refund the whole amount is left to the primary payment method.
     */
    public function testNothingIsSentWhenNoGiftcardIsRefunded(): void
    {
        $this->paymentGroupTransaction->method('getAnyGroupTransactionItems')
            ->willReturn([$this->row('ideal', 'IDKEY', 40.00)]);

        $buildSubject = ['payment' => $this->createPaymentDataObject(40.00), 'amount' => '20.00'];

        $this->assertSame(20.0, $this->service->refundGroupTransactions($buildSubject));
        $this->assertSame([], $this->sent);
        $this->assertArrayNotHasKey('response', $buildSubject);
    }

    /**
     * Automatic flow: a voucher listed after the other payment is still refunded, and nothing
     * is sent to the other payment once the voucher covers the refund.
     */
    public function testVoucherAfterTheOtherPaymentCoversTheWholeRefund(): void
    {
        $voucher = $this->row('buckaroovoucher', 'VKEY', 10.00);
        $this->paymentGroupTransaction->method('getAnyGroupTransactionItems')
            ->willReturn([$this->row('ideal', 'IDKEY', 30.00), $voucher]);
        $this->paymentGroupTransaction->method('getGroupTransactionByTrxId')->willReturn($voucher);

        $buildSubject = ['payment' => $this->createPaymentDataObject(40.00), 'amount' => 10.00];

        $this->assertSame(0, $this->service->refundGroupTransactions($buildSubject));
        $this->assertSentRefunds([['buckaroovoucher', 10.00, 'VKEY']]);
    }

    /**
     * A remaining cent is refunded on the other payment.
     */
    public function testRemainingCentIsRefundedOnTheOtherPayment(): void
    {
        $this->groupTransaction(null);
        $this->postGiftcards(['B1DA75BE2400480B86D1D37817B81DB1|fashioncheque|10.00' => '0.01']);
        $this->paymentGroupTransaction->method('getAnyGroupTransactionItems')
            ->willReturn([$this->row('ideal', 'IDKEY', 30.00)]);

        $buildSubject = ['payment' => $this->createPaymentDataObject(40.00), 'amount' => 0.02];

        $this->assertSame(0, $this->service->refundGroupTransactions($buildSubject));
        $this->assertSentRefunds([
            ['fashioncheque', 0.01, 'B1DA75BE2400480B86D1D37817B81DB1'],
            ['ideal', 0.01, 'IDKEY'],
        ]);
    }

    /**
     * A one-cent remainder is still refunded on the other payment for amounts whose
     * difference is not exact in floating point (10.01 - 10.00 = 0.00999...).
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('oneCentRemainderProvider')]
    public function testOneCentRemainderIsRefundedOnTheOtherPayment(float $refund, string $giftcard): void
    {
        $this->groupTransaction(null);
        $this->postGiftcards(['B1DA75BE2400480B86D1D37817B81DB1|fashioncheque|' . $giftcard => $giftcard]);
        $this->paymentGroupTransaction->method('getAnyGroupTransactionItems')
            ->willReturn([$this->row('ideal', 'IDKEY', 30.00)]);

        $buildSubject = ['payment' => $this->createPaymentDataObject(90.00), 'amount' => $refund];

        $this->assertSame(0, $this->service->refundGroupTransactions($buildSubject));
        $this->assertSentRefunds([
            ['fashioncheque', (float)$giftcard, 'B1DA75BE2400480B86D1D37817B81DB1'],
            ['ideal', 0.01, 'IDKEY'],
        ]);
    }

    /**
     * Without another payment the one-cent remainder is returned for the primary method.
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('oneCentRemainderProvider')]
    public function testOneCentRemainderIsReturnedWithoutOtherPayment(float $refund, string $giftcard): void
    {
        $this->groupTransaction(null);
        $this->postGiftcards(['B1DA75BE2400480B86D1D37817B81DB1|fashioncheque|' . $giftcard => $giftcard]);
        $this->paymentGroupTransaction->method('getAnyGroupTransactionItems')->willReturn([]);

        $buildSubject = ['payment' => $this->createPaymentDataObject(90.00), 'amount' => $refund];

        $this->assertSame(0.01, $this->service->refundGroupTransactions($buildSubject));
        $this->assertArrayNotHasKey('response', $buildSubject);
    }

    public static function oneCentRemainderProvider(): array
    {
        return [
            '10.01 with 10.00 on the giftcard' => [10.01, '10.00'],
            '9.99 with 9.98 on the giftcard'   => [9.99, '9.98'],
            '7.31 with 7.30 on the giftcard'   => [7.31, '7.30'],
        ];
    }

    /**
     * Without another payment the remaining cent is returned for the primary method.
     */
    public function testRemainingCentIsReturnedWithoutOtherPayment(): void
    {
        $this->groupTransaction(null);
        $this->postGiftcards(['B1DA75BE2400480B86D1D37817B81DB1|fashioncheque|10.00' => '0.01']);
        $this->paymentGroupTransaction->method('getAnyGroupTransactionItems')->willReturn([]);

        $buildSubject = ['payment' => $this->createPaymentDataObject(40.00), 'amount' => 0.02];

        $this->assertSame(0.01, $this->service->refundGroupTransactions($buildSubject));
        $this->assertArrayNotHasKey('response', $buildSubject);
    }

    /**
     * A posted giftcard that is not refunded still takes its share off the order total, which
     * caps what is left for the primary method: 40.00 - 10.00.
     */
    public function testAmountLeftIsCappedAtTheOrderTotalWithoutPostedGiftcards(): void
    {
        $this->groupTransaction(null);
        $this->postGiftcards(['B1DA75BE2400480B86D1D37817B81DB1|fashioncheque|10.00' => '0']);
        $this->paymentGroupTransaction->method('getAnyGroupTransactionItems')->willReturn([]);

        $buildSubject = ['payment' => $this->createPaymentDataObject('40.00'), 'amount' => 35.00];

        $this->assertSame(30.0, $this->service->refundGroupTransactions($buildSubject));
        $this->assertSame([], $this->sent);
    }

    public function testAmountLeftIsCappedAtTheOrderGrandTotal(): void
    {
        $this->paymentGroupTransaction->method('getAnyGroupTransactionItems')->willReturn([]);

        $buildSubject = ['payment' => $this->createPaymentDataObject('40.05'), 'amount' => 50.00];

        $this->assertSame(40.05, $this->service->refundGroupTransactions($buildSubject));
    }

    public function testFullyRefundedGiftcardIsNotRefundedAgain(): void
    {
        $this->groupTransaction(10.00);
        $this->groupTransactionResource->expects($this->never())->method('save');

        $this->service->createRefundGroupRequest([], 'B1DA75BE2400480B86D1D37817B81DB1|fashioncheque|10.00', 10.00);

        $this->assertSame([], $this->sent);
        $this->assertSame(40.05, $this->service->getAmountLeftToRefund());
    }

    /**
     * A giftcard key without a stored row is refunded as posted and nothing is recorded.
     */
    public function testUnknownGiftcardIsRefundedAsPostedWithCustomerDetails(): void
    {
        $this->paymentGroupTransaction->method('getGroupTransactionByTrxId')->willReturn($this->emptyRow());
        $this->groupTransactionResource->expects($this->never())->method('save');

        $buildSubject = ['payment' => $this->createPaymentDataObject(40.00, ['lastname' => 'Jansen'])];
        $this->service->createRefundGroupRequest($buildSubject, 'KEY2|vvvgiftcard|10.00', '7.50');

        $this->assertSentRefunds([['vvvgiftcard', 7.50, 'KEY2']]);
        $this->assertSame('jan@example.com', $this->requests[0]['email']);
        $this->assertSame('Jansen', $this->requests[0]['lastname']);
    }

    /**
     * Fashioncheque refunds never carry customer details, whatever the case of the service code.
     */
    public function testFashionchequeRefundCarriesNoCustomerDetails(): void
    {
        $this->paymentGroupTransaction->method('getGroupTransactionByTrxId')->willReturn($this->emptyRow());

        $buildSubject = ['payment' => $this->createPaymentDataObject(40.00, ['lastname' => 'Jansen'])];
        $this->service->createRefundGroupRequest($buildSubject, 'KEY3|FashionCheque|5.00', 5.00);

        $this->assertSentRefunds([['FashionCheque', 5.00, 'KEY3']]);
        $this->assertArrayNotHasKey('email', $this->requests[0]);
        $this->assertArrayNotHasKey('lastname', $this->requests[0]);
    }

    public function testRejectedGiftcardRefundIsNotRecorded(): void
    {
        $groupTransaction = $this->groupTransaction(null);
        $this->groupTransactionResource->expects($this->never())->method('save');
        $this->refundAccepted = false;

        try {
            $this->service->createRefundGroupRequest([], 'B1DA75BE2400480B86D1D37817B81DB1|fashioncheque|10.00', 10.00);
            $this->fail('A rejected refund must throw');
        } catch (ClientException $e) {
            $this->assertSame(
                'Buckaroo giftcard refund failed for fashioncheque (Status 490): Refund rejected',
                $e->getMessage()
            );
        }
        $this->assertNull($groupTransaction->getData('refunded_amount'));
    }

    public function testRejectedRefundOfTheOtherPaymentThrows(): void
    {
        $voucher = $this->row('buckaroovoucher', 'VKEY', 10.00);
        $this->paymentGroupTransaction->method('getAnyGroupTransactionItems')
            ->willReturn([$voucher, $this->row('ideal', 'IDKEY', 30.00)]);
        $this->paymentGroupTransaction->method('getGroupTransactionByTrxId')->willReturn($voucher);
        $this->refundResults = [true, false];

        $this->expectException(ClientException::class);
        $this->expectExceptionMessage('Buckaroo refund failed for ideal (Status 490): Refund rejected');

        $buildSubject = ['payment' => $this->createPaymentDataObject(40.00), 'amount' => 25.00];
        $this->service->refundGroupTransactions($buildSubject);
    }

    /**
     * @return array
     */
    public static function automaticGiftcardRefundProvider(): array
    {
        return [
            'a single cent left to refund' => [0.01, 10.00, null, true, 0.01],
            'refunded part of the card is not offered again' => [10.00, 10.00, 4.00, false, 6.00],
            'a single cent left on the card' => [5.00, 0.02, 0.01, true, 0.01],
        ];
    }

    /**
     * @param float $amount
     * @param float $cardAmount
     * @param float|null $cardRefunded
     * @param bool $stored whether the card row is found by its key
     * @param float $expected
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('automaticGiftcardRefundProvider')]
    public function testAutomaticGiftcardRefund(
        float $amount,
        float $cardAmount,
        ?float $cardRefunded,
        bool $stored,
        float $expected
    ): void {
        $card = $this->row('vvvgiftcard', 'GKEY', $cardAmount, $cardRefunded);
        $this->paymentGroupTransaction->method('getAnyGroupTransactionItems')->willReturn([$card]);
        $this->paymentGroupTransaction->method('getGroupTransactionByTrxId')
            ->willReturn($stored ? $card : $this->emptyRow());

        $buildSubject = ['payment' => $this->createPaymentDataObject(40.00), 'amount' => $amount];
        $this->service->refundGroupTransactions($buildSubject);

        $this->assertSentRefunds([['vvvgiftcard', $expected, 'GKEY']]);
    }

    /**
     * Single giftcard payment: the card is refunded up to what was paid on it.
     */
    public function testSingleGiftcardIsRefundedUpToWhatWasPaidOnIt(): void
    {
        $payment = $this->singleGiftcardPayment('fashioncheque');
        $payment->expects($this->once())
            ->method('setAdditionalInformation')
            ->with('single_giftcard_refunded_amount', 12.0);

        $buildSubject = ['payment' => $this->createPaymentDataObject(40.00, ['payment' => $payment]), 'amount' => 15.00];

        $this->assertSame(5.0, $this->service->refundGroupTransactions($buildSubject));
        $this->assertSentRefunds([['fashioncheque', 10.00, 'GCKEY']]);
        $this->assertSame('fashioncheque', $this->requests[0]['name']);
        $this->assertArrayNotHasKey('response', $buildSubject);
    }

    public function testSingleGiftcardCoveringTheRefundCompletesIt(): void
    {
        $payment = $this->singleGiftcardPayment('vvvgiftcard');
        $payment->expects($this->once())
            ->method('setAdditionalInformation')
            ->with('single_giftcard_refunded_amount', 10.0);

        $buildSubject = [
            'payment' => $this->createPaymentDataObject(40.00, ['payment' => $payment, 'lastname' => 'Jansen']),
            'amount'  => 8.00,
        ];

        $this->assertSame(0.0, $this->service->refundGroupTransactions($buildSubject));
        $this->assertSentRefunds([['vvvgiftcard', 8.00, 'GCKEY']]);
        $this->assertSame('Jansen', $this->requests[0]['lastname']);
        $this->assertTrue($buildSubject['response']['group_transaction_refund_complete']);
    }

    /**
     * A stored service code that is no giftcard is left to the regular refund.
     */
    public function testSingleGiftcardWithOtherServiceCodeIsLeftToTheRegularRefund(): void
    {
        $payment = $this->singleGiftcardPayment('ideal');
        $payment->expects($this->never())->method('setAdditionalInformation');

        $buildSubject = ['payment' => $this->createPaymentDataObject(40.00, ['payment' => $payment]), 'amount' => 15.00];

        $this->assertSame(15.0, $this->service->refundGroupTransactions($buildSubject));
        $this->assertSame([], $this->sent);
    }

    public function testRejectedSingleGiftcardRefundIsNotRecorded(): void
    {
        $payment = $this->singleGiftcardPayment('fashioncheque');
        $payment->expects($this->never())->method('setAdditionalInformation');
        $this->refundAccepted = false;

        $this->expectException(ClientException::class);
        $this->expectExceptionMessage('Buckaroo giftcard refund failed (Status 490): Refund rejected');

        $buildSubject = ['payment' => $this->createPaymentDataObject(40.00, ['payment' => $payment]), 'amount' => 15.00];
        $this->service->refundGroupTransactions($buildSubject);
    }

    public function testSingleGiftcardWithNothingToRefundSendsNothing(): void
    {
        $payment = $this->singleGiftcardPayment('fashioncheque');
        $payment->expects($this->never())->method('setAdditionalInformation');

        $buildSubject = ['payment' => $this->createPaymentDataObject(40.00, ['payment' => $payment]), 'amount' => 0];
        $this->service->refundGroupTransactions($buildSubject);

        $this->assertSame([], $this->sent);
    }

    /**
     * A remaining cent after the single giftcard is left for the regular refund.
     */
    public function testSingleGiftcardLeavesARemainingCent(): void
    {
        $payment = $this->singleGiftcardPayment('fashioncheque', '0.01');

        $buildSubject = ['payment' => $this->createPaymentDataObject(40.00, ['payment' => $payment]), 'amount' => 0.02];

        $this->assertSame(0.01, $this->service->refundGroupTransactions($buildSubject));
        $this->assertArrayNotHasKey('response', $buildSubject);
    }

    /**
     * @return array
     */
    public static function amountLeftWithStoredGroupAmountProvider(): array
    {
        return [
            'full refund leaves out what the group transactions hold' => [40.00, 10.00, 30.00],
            'full refund without group amount'                       => [40.00, 0.0, 40.00],
            'partial refund is passed on as is'                      => [30.00, 10.00, 30.00],
        ];
    }

    /**
     * @param float $amount
     * @param float $groupAmount
     * @param float $expected
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('amountLeftWithStoredGroupAmountProvider')]
    public function testAmountLeftWithStoredGroupAmount(float $amount, float $groupAmount, float $expected): void
    {
        $this->paymentGroupTransaction->method('getAnyGroupTransactionItems')->willReturn([]);
        $this->paymentGroupTransaction->method('getGroupTransactionAmount')->with('000000077')->willReturn($groupAmount);

        $buildSubject = ['payment' => $this->createPaymentDataObject(40.00), 'amount' => $amount];

        $this->assertSame($expected, $this->service->refundGroupTransactions($buildSubject));
    }

    /**
     * @return array
     */
    public static function adjustmentResetProvider(): array
    {
        return [
            'group amount and posted negative adjustment' => [10.00, ['adjustment_negative' => '5'], 1],
            'a single cent of group amount'                => [0.01, ['adjustment_negative' => '5'], 0],
            'no posted negative adjustment'                => [10.00, ['adjustment_negative' => ''], 0],
        ];
    }

    /**
     * A negative adjustment posted without giftcard rows is reset when giftcards paid part of the order.
     *
     * @param float $groupAmount
     * @param array $creditmemoParams
     * @param int $resets
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('adjustmentResetProvider')]
    public function testNegativeAdjustmentIsResetForGiftcardOrders(float $groupAmount, array $creditmemoParams, int $resets): void
    {
        $this->paymentGroupTransaction->method('getAnyGroupTransactionItems')->willReturn([]);
        $this->paymentGroupTransaction->method('getGroupTransactionAmount')->willReturn($groupAmount);
        $this->request->method('getParams')->willReturn(['creditmemo' => $creditmemoParams]);

        $paymentDO = $this->createPaymentDataObject(40.00);
        $paymentDO->getOrder()->getOrder()->expects($this->exactly($resets))->method('setAdjustmentNegative')->with(0);

        $buildSubject = ['payment' => $paymentDO, 'amount' => 20.00];
        $this->service->refundGroupTransactions($buildSubject);
    }

    /**
     * @return array
     */
    public static function unrecordedRefundProvider(): array
    {
        $after = [[2.50, '2026-11-25 12:00:00'], [1.50, '2026-11-25 12:05:00']];

        return [
            'refunds an hour after the last credit memo are counted' => [['2026-11-25 10:00:00'], $after, 6.00],
            'refunds without any credit memo are counted' => [[], $after, 6.00],
            'refund within 30 seconds of the last credit memo is recorded by it' => [
                ['2026-11-25 09:00:00', '2026-11-25 10:00:00'], [[4.00, '2026-11-25 11:00:10']], 10.00,
            ],
            'refund exactly 30 seconds after the last credit memo is recorded by it' => [
                ['2026-11-25 10:00:00'], [[4.00, '2026-11-25 11:00:30']], 10.00,
            ],
            'a single cent counts' => [['2026-11-25 10:00:00'], [[0.01, '2026-11-25 12:00:00']], 9.99],
        ];
    }

    /**
     * Giftcard refunds Buckaroo carried out without a credit memo (Buckaroo time is Amsterdam,
     * credit memo time UTC) count toward this refund and are recorded on the giftcard; a refund
     * of another transaction does not count.
     *
     * @param array $creditmemoTimes
     * @param array $refunds [amount, Amsterdam time]
     * @param float $expected
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('unrecordedRefundProvider')]
    public function testUnrecordedGiftcardRefundIsCounted(array $creditmemoTimes, array $refunds, float $expected): void
    {
        $key = 'B1DA75BE2400480B86D1D37817B81DB1';
        $groupTransaction = $this->groupTransaction(null);
        $this->postGiftcards([$key . '|fashioncheque|10.00' => '10.00']);
        $this->paymentGroupTransaction->method('getAnyGroupTransactionItems')->willReturn([]);
        $this->groupTransactionResource->expects($this->exactly($expected < 10.00 ? 2 : 1))
            ->method('save')
            ->with($this->identicalTo($groupTransaction));

        $statuses = [
            'fashioncheque|' . $key => $this->originalStatus([]),
            'fashioncheque|R9' => $this->refundStatus('OTHERKEY', 3.00, '2026-11-25 12:00:00'),
        ];
        $listed = ['R9'];
        foreach ($refunds as $index => [$amount, $time]) {
            $statuses['fashioncheque|R' . $index] = $this->refundStatus($key, $amount, $time);
            $listed[] = 'R' . $index;
        }
        $this->useBuckaroo(['fashioncheque' => $listed], $statuses);

        $buildSubject = [
            'payment' => $this->createPaymentDataObject(50.00, ['creditmemos' => $creditmemoTimes]),
            'amount'  => 40.00,
        ];

        $this->assertEqualsWithDelta(30.00, $this->service->refundGroupTransactions($buildSubject), 0.0001);
        $this->assertSentRefunds([['fashioncheque', $expected, $key]]);
        $this->assertEqualsWithDelta(10.00, $groupTransaction->getRefundedAmount(), 0.001);
    }

    /**
     * @return array
     */
    public static function unrecordedOtherPaymentRefundProvider(): array
    {
        return [
            'part of the rest was refunded'  => [5.00, [['ideal', 25.00, 'IDKEY']]],
            'a single cent was refunded'     => [0.01, [['ideal', 29.99, 'IDKEY']]],
            'the whole rest was refunded'    => [30.00, []],
        ];
    }

    /**
     * An earlier refund of the other payment found through its related transactions is not paid twice.
     *
     * @param float $refunded
     * @param array $expectedOther
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('unrecordedOtherPaymentRefundProvider')]
    public function testUnrecordedRefundOfTheOtherPaymentIsNotPaidTwice(float $refunded, array $expectedOther): void
    {
        $this->groupTransaction(null);
        $this->postGiftcards(['B1DA75BE2400480B86D1D37817B81DB1|fashioncheque|10.00' => '10.00']);
        $this->paymentGroupTransaction->method('getAnyGroupTransactionItems')
            ->willReturn([$this->row('ideal', 'IDKEY', 30.00)]);
        $this->useBuckaroo(
            ['fashioncheque' => [], 'ideal' => ['IDKEY']],
            [
                'fashioncheque|B1DA75BE2400480B86D1D37817B81DB1' => $this->originalStatus([]),
                'ideal|IDKEY' => $this->originalStatus([['RelationType' => 'refund', 'RelatedTransactionKey' => 'R2']]),
                'ideal|R2' => $this->refundStatus('IDKEY', $refunded, '2026-11-25 12:00:00'),
            ]
        );

        $buildSubject = [
            'payment' => $this->createPaymentDataObject(40.00, ['creditmemos' => ['2026-11-25 10:00:00']]),
            'amount'  => 40.00,
        ];

        $this->assertSame(0, $this->service->refundGroupTransactions($buildSubject));
        $this->assertSentRefunds(array_merge(
            [['fashioncheque', 10.00, 'B1DA75BE2400480B86D1D37817B81DB1']],
            $expectedOther
        ));
    }

    /**
     * @param array $expected [payment_method, amountCredit, originalTransactionKey]
     */
    private function assertSentRefunds(array $expected): void
    {
        $this->assertCount(count($expected), $this->sent);
        foreach ($expected as $index => [$method, $amount, $key]) {
            $this->assertSame($method, $this->sent[$index][0]);
            $this->assertEqualsWithDelta($amount, $this->sent[$index][1], 0.0001);
            $this->assertSame($key, $this->sent[$index][2]);
        }
    }

    /**
     * @param array $giftcards posted "key|servicecode|amount" => amount
     */
    private function postGiftcards(array $giftcards): void
    {
        $this->request->method('getParams')->willReturn(['creditmemo' => ['buckaroo_already_paid' => $giftcards]]);
    }

    /**
     * Buckaroo answers for the unrecorded refund check
     *
     * @param array $byInvoice service code => transaction keys listed for the order
     * @param array $statuses  "servicecode|key" => status
     */
    private function useBuckaroo(array $byInvoice, array $statuses): void
    {
        $adapter = $this->getFakeMock(BuckarooAdapter::class)->getMock();
        $adapter->method('getTransactionsByInvoice')->willReturnCallback(
            static function ($method, $storeId, $invoice) use ($byInvoice) {
                if ($storeId !== 1 || $invoice !== '000000077') {
                    throw new \InvalidArgumentException('Unexpected lookup');
                }
                return array_map(static fn ($key) => ['key' => $key, 'status' => 190], $byInvoice[$method]);
            }
        );
        $adapter->method('getTransactionStatus')->willReturnCallback(
            static function ($method, $storeId, $key) use ($statuses) {
                if ($storeId !== 1 || !isset($statuses[$method . '|' . $key])) {
                    throw new \InvalidArgumentException('Unexpected lookup');
                }
                return $statuses[$method . '|' . $key];
            }
        );

        $objectManager = $this->getFakeMock(ObjectManagerInterface::class)->getMock();
        $objectManager->method('get')->with(BuckarooAdapter::class)->willReturn($adapter);
        AppObjectManager::setInstance($objectManager);
    }

    /**
     * @param array $related
     *
     * @return TransactionResponse|MockObject
     */
    private function originalStatus(array $related)
    {
        $status = $this->getFakeMock(TransactionResponse::class)->getMock();
        $status->method('get')->willReturnMap([['RelatedTransactions', $related]]);

        return $status;
    }

    /**
     * @param string $originalKey
     * @param float  $amount
     * @param string $time Amsterdam time
     *
     * @return TransactionResponse|MockObject
     */
    private function refundStatus(string $originalKey, float $amount, string $time)
    {
        $status = $this->getFakeMock(TransactionResponse::class)->getMock();
        $status->method('get')->willReturnMap([
            ['RelatedTransactions', [['RelationType' => 'Refund', 'RelatedTransactionKey' => $originalKey]]],
            ['AmountCredit', $amount],
            ['Status', ['DateTime' => $time]],
        ]);
        $status->method('getStatusCode')->willReturn(190);

        return $status;
    }

    /**
     * @param string $servicecode
     * @param string $cardAmount
     *
     * @return Payment|MockObject
     */
    private function singleGiftcardPayment(string $servicecode, string $cardAmount = '10.00')
    {
        $payment = $this->getFakeMock(Payment::class)->getMock();
        $payment->method('getMethod')->willReturn('buckaroo_magento2_giftcards');
        $payment->method('getAdditionalInformation')->willReturnMap([
            ['single_giftcard_payment', true],
            ['single_giftcard_servicecode', $servicecode],
            ['single_giftcard_transaction_id', 'GCKEY'],
            ['single_giftcard_amount', $cardAmount],
            ['single_giftcard_refunded_amount', '2.00'],
        ]);

        return $payment;
    }

    /**
     * @param string     $servicecode
     * @param string     $transactionId
     * @param float      $amount
     * @param float|null $refunded
     *
     * @return GroupTransaction
     */
    private function row(string $servicecode, string $transactionId, float $amount, ?float $refunded = null): GroupTransaction
    {
        $row = $this->emptyRow();
        $row->setData([
            'entity_id'       => 40,
            'transaction_id'  => $transactionId,
            'servicecode'     => $servicecode,
            'amount'          => $amount,
            'refunded_amount' => $refunded,
            'status'          => '190',
        ]);

        return $row;
    }

    /**
     * @return GroupTransaction
     */
    private function emptyRow(): GroupTransaction
    {
        return $this->getFakeMock(GroupTransaction::class)->onlyMethods([])->getMock();
    }

    /**
     * @return mixed
     */
    private function getObjectManagerInstance()
    {
        $property = new \ReflectionProperty(AppObjectManager::class, '_instance');

        return $property->getValue();
    }

    /**
     * @param float|string $grandTotal
     * @param array        $options creditmemos (UTC creation times), lastname, payment
     *
     * @return PaymentDataObjectInterface
     */
    private function createPaymentDataObject($grandTotal, array $options = []): PaymentDataObjectInterface
    {
        $creditmemos = array_map(
            static fn ($time) => new DataObject(['created_at' => $time]),
            $options['creditmemos'] ?? []
        );
        $order = $this->getFakeMock(Order::class)->getMock();
        $order->method('getBaseGrandTotal')->willReturn($grandTotal);
        $order->method('getIncrementId')->willReturn('000000077');
        $order->method('getStoreId')->willReturn('1');
        $order->method('getCustomerEmail')->willReturn('jan@example.com');
        $order->method('getCreditmemosCollection')->willReturn($creditmemos);
        if (isset($options['lastname'])) {
            $order->method('getBillingAddress')->willReturn(new DataObject(['lastname' => $options['lastname']]));
        }

        $orderAdapter = $this->createMock(OrderAdapterInterfaceStub::class);
        $orderAdapter->method('getOrder')->willReturn($order);

        $payment = $options['payment'] ?? null;
        if ($payment === null) {
            $payment = $this->getFakeMock(Payment::class)->getMock();
            $payment->method('getMethod')->willReturn('buckaroo_magento2_voucher');
            $payment->method('getAdditionalInformation')->willReturn(null);
        }

        $paymentDO = $this->getFakeMock(PaymentDataObjectInterface::class)->getMock();
        $paymentDO->method('getOrder')->willReturn($orderAdapter);
        $paymentDO->method('getPayment')->willReturn($payment);

        return $paymentDO;
    }

    /**
     * @param float|null $refundedAmount
     *
     * @return GroupTransaction
     */
    private function groupTransaction(?float $refundedAmount): GroupTransaction
    {
        $groupTransaction = $this->getFakeMock(GroupTransaction::class)->onlyMethods([])->getMock();
        $groupTransaction->setData([
            'entity_id'       => 24,
            'transaction_id'  => 'B1DA75BE2400480B86D1D37817B81DB1',
            'servicecode'     => 'fashioncheque',
            'amount'          => 10.00,
            'refunded_amount' => $refundedAmount,
        ]);

        $this->paymentGroupTransaction->method('getGroupTransactionByTrxId')
            ->with('B1DA75BE2400480B86D1D37817B81DB1')
            ->willReturn($groupTransaction);

        return $groupTransaction;
    }
}
