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


use PHPUnit\Framework\Attributes\DataProvider;
use Buckaroo\Magento2\Gateway\Data\Order\OrderAdapter;
use Buckaroo\Magento2\Gateway\Request\BasicParameter\AmountCreditDataBuilder;
use Buckaroo\Magento2\Service\RefundGroupTransactionService;
use Buckaroo\Magento2\Service\TransactionCurrencyResolver;
use Buckaroo\Magento2\Test\Unit\Gateway\Request\AbstractDataBuilderTest;
use Magento\Payment\Gateway\Data\PaymentDataObjectInterface;
use Magento\Payment\Gateway\Http\ClientException;
use Magento\Payment\Gateway\Http\ConverterException;
use Magento\Sales\Model\Order\Creditmemo;
use Magento\Sales\Model\Order\Payment;
use PHPUnit\Framework\MockObject\MockObject;

class AmountCreditDataBuilderTest extends AbstractDataBuilderTest
{
    /**
     * @var MockObject|TransactionCurrencyResolver
     */
    private $transactionCurrencyResolverMock;

    /**
     * @var MockObject|RefundGroupTransactionService
     */
    private $refundGroupServiceMock;

    /**
     * @var AmountCreditDataBuilder
     */
    private $amountCreditDataBuilder;

    protected function setUp(): void
    {
        parent::setUp();

        $this->transactionCurrencyResolverMock = $this->createMock(TransactionCurrencyResolver::class);

        $this->refundGroupServiceMock = $this->createMock(RefundGroupTransactionService::class);

        $this->amountCreditDataBuilder = new AmountCreditDataBuilder(
            $this->transactionCurrencyResolverMock,
            $this->refundGroupServiceMock
        );
    }

    /**
     *
     * @param float $amount
     * @param float $amountLeftToRefund
     * @param float $expectedResult
     *
     * @throws ClientException
     * @throws ConverterException
     */
    #[DataProvider('buildDataProvider')]
    public function testBuild(float $amount, float $amountLeftToRefund, float $expectedResult): void
    {
        $this->orderMock->method('getBaseGrandTotal')->willReturn($amount);
        $this->orderMock->method('getOrderCurrencyCode')->willReturn('USD');
        $this->orderMock->method('getBaseCurrencyCode')->willReturn('EUR');
        $this->orderMock->method('getBaseToOrderRate')->willReturn(1.0);
        $this->orderMock->method('getIncrementId')->willReturn('000000001');

        // USD is not supported by the method, so the base refund amount is used as-is
        $this->transactionCurrencyResolverMock->method('resolve')->willReturn(null);

        $buildSubject = [
            'payment' => $this->getPaymentDOMock(),
            'amount'  => $amount
        ];

        // Enable group transaction logic when amounts differ
        $hasGroupTransactions = ($amount !== $amountLeftToRefund);
        $this->refundGroupServiceMock->method('hasGroupTransactions')->willReturn($hasGroupTransactions);
        $this->refundGroupServiceMock->method('refundGroupTransactions')->willReturn($amountLeftToRefund);

        $result = $this->amountCreditDataBuilder->build($buildSubject);

        $this->assertEquals($expectedResult, $result[AmountCreditDataBuilder::AMOUNT_CREDIT]);
    }

    /**
     * @return array
     */
    public static function buildDataProvider(): array
    {
        return [
            [100.00, 100.00, 100.00],
            [50.00, 75.00, 75.00],
            [0.01, 0.01, 0.01],
        ];
    }

    /**
     * When the transaction was sent in the order currency, the refund must use the
     * creditmemo's stored order-currency grand total instead of converting the base
     * amount by rate, so repeated partial refunds cannot accumulate rounding drift.
     *
     * @throws ClientException
     * @throws ConverterException
     */
    public function testBuildUsesCreditmemoGrandTotalForOrderCurrencyTransaction(): void
    {
        $this->preparePlnOrder();

        $creditmemoMock = $this->createMock(Creditmemo::class);
        $creditmemoMock->method('getGrandTotal')->willReturn(167.67);

        $this->transactionCurrencyResolverMock->method('resolve')->willReturn('PLN');

        $this->refundGroupServiceMock->method('hasGroupTransactions')->willReturn(false);

        $result = $this->amountCreditDataBuilder->build([
            'payment' => $this->getSalesPaymentDOMock($creditmemoMock),
            'amount'  => 39.10
        ]);

        $this->assertEquals(167.67, $result[AmountCreditDataBuilder::AMOUNT_CREDIT]);
    }

    /**
     * The group-transaction remainder is tracked in base currency, so that path
     * still converts with the base-to-order rate.
     *
     * @throws ClientException
     * @throws ConverterException
     */
    public function testBuildConvertsGroupTransactionRemainderByRate(): void
    {
        $this->preparePlnOrder();

        $this->transactionCurrencyResolverMock->method('resolve')->willReturn('PLN');

        $this->refundGroupServiceMock->method('hasGroupTransactions')->willReturn(true);
        $this->refundGroupServiceMock->method('refundGroupTransactions')->willReturn(10.00);

        $result = $this->amountCreditDataBuilder->build([
            'payment' => $this->getSalesPaymentDOMock(null),
            'amount'  => 39.10
        ]);

        // 10.00 base * 4.2882 = 42.88 in order currency
        $this->assertEquals(42.88, $result[AmountCreditDataBuilder::AMOUNT_CREDIT]);
    }

    /**
     * refundGroupTransactions() returns the CLAMPED remainder (group-transaction
     * deduction and total-order ceiling applied). The builder must consume that
     * return value - reading the raw amountLeftToRefund property discards the
     * clamps and can over-refund the primary payment method.
     *
     * @throws ClientException
     * @throws ConverterException
     */
    public function testBuildUsesClampedReturnValueOfRefundGroupTransactions(): void
    {
        $this->orderMock->method('getBaseGrandTotal')->willReturn(100.00);
        $this->orderMock->method('getOrderCurrencyCode')->willReturn('USD');
        $this->orderMock->method('getBaseCurrencyCode')->willReturn('EUR');
        $this->orderMock->method('getBaseToOrderRate')->willReturn(1.0);
        $this->orderMock->method('getIncrementId')->willReturn('000000001');
        $this->transactionCurrencyResolverMock->method('resolve')->willReturn(null);

        $this->refundGroupServiceMock->method('hasGroupTransactions')->willReturn(true);
        // Clamped return value (e.g. grand total minus giftcard group amount) ...
        $this->refundGroupServiceMock->method('refundGroupTransactions')->willReturn(40.00);
        // ... must win over the raw, un-clamped property value
        $this->refundGroupServiceMock->method('getAmountLeftToRefund')->willReturn(100.00);

        $result = $this->amountCreditDataBuilder->build([
            'payment' => $this->getPaymentDOMock(),
            'amount'  => 100.00
        ]);

        $this->assertEquals(40.00, $result[AmountCreditDataBuilder::AMOUNT_CREDIT]);
    }

    /**
     * Configure the shared order mock as the ticket's PLN order (base EUR).
     */
    private function preparePlnOrder(): void
    {
        $this->orderMock->method('getBaseGrandTotal')->willReturn(39.10);
        $this->orderMock->method('getOrderCurrencyCode')->willReturn('PLN');
        $this->orderMock->method('getBaseCurrencyCode')->willReturn('EUR');
        $this->orderMock->method('getBaseToOrderRate')->willReturn(4.2882);
        $this->orderMock->method('getIncrementId')->willReturn('000000555');
    }

    /**
     * Payment data object whose payment is a sales order Payment mock, which
     * declares getCreditmemo() (unlike InfoInterface).
     *
     * @param Creditmemo|MockObject|null $creditmemo
     *
     * @return PaymentDataObjectInterface|MockObject
     */
    private function getSalesPaymentDOMock($creditmemo)
    {
        $paymentMock = $this->createMock(Payment::class);
        $paymentMock->method('getMethodInstance')->willReturn($this->paymentMethodInstanceMock);
        $paymentMock->method('getCreditmemo')->willReturn($creditmemo);
        $paymentMock->method('getAdditionalInformation')->willReturn(null);

        $orderAdapter = $this->createMock(OrderAdapter::class);
        $orderAdapter->method('getOrder')->willReturn($this->orderMock);

        $paymentDOMock = $this->createMock(PaymentDataObjectInterface::class);
        $paymentDOMock->method('getOrder')->willReturn($orderAdapter);
        $paymentDOMock->method('getPayment')->willReturn($paymentMock);

        return $paymentDOMock;
    }
}
