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

use Buckaroo\Magento2\Helper\PaymentFee;
use Buckaroo\Magento2\Helper\PaymentGroupTransaction;
use Buckaroo\Magento2\Model\ConfigProvider\Account as AccountConfigProvider;
use Buckaroo\Magento2\Model\ConfigProvider\BuckarooFee as BuckarooFeeConfigProvider;
use Buckaroo\Magento2\Model\ConfigProvider\Method\Factory as MethodConfigProviderFactory;
use Buckaroo\Magento2\Model\GroupTransaction;
use Buckaroo\Magento2\Model\ResourceModel\Giftcard\Collection as GiftcardCollection;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\DataObject;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Creditmemo;
use Magento\Sales\Model\Order\Invoice;
use Magento\Store\Model\Store;
use PHPUnit\Framework\TestCase;

/**
 * Fee and already-paid total lines shown on orders, invoices and credit memos.
 *
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class PaymentFeeTest extends TestCase
{
    /**
     * @var PaymentGroupTransaction|\PHPUnit\Framework\MockObject\MockObject
     */
    private $groupTransaction;

    /**
     * @var BuckarooFeeConfigProvider|\PHPUnit\Framework\MockObject\MockObject
     */
    private $feeConfig;

    /**
     * @var GiftcardCollection|\PHPUnit\Framework\MockObject\MockObject
     */
    private $giftcards;

    /**
     * @var array
     */
    private array $params = [];

    protected function setUp(): void
    {
        $this->groupTransaction = $this->createMock(PaymentGroupTransaction::class);
        $this->feeConfig = $this->createMock(BuckarooFeeConfigProvider::class);
        $this->giftcards = $this->createMock(GiftcardCollection::class);
    }

    /**
     * Each partial payment gets its own line, in the base currency as well.
     */
    public function testOrderTotalsListEveryPartialPaymentNextToTheFee(): void
    {
        $order = $this->makeOrder('EUR', 'USD', 1.3);
        $this->feeConfig->expects($this->once())->method('getSalesDisplayType')
            ->with($order->getStore())
            ->willReturn(1);

        $this->groupTransaction->method('getAlreadyPaid')->with('000000042')->willReturn(30.0);
        $this->groupTransaction->method('getGroupTransactionItems')->with('000000042')->willReturn([
            ['servicecode' => 'ideal', 'amount' => '10.00', 'transaction_id' => 'ID-KEY'],
            ['servicecode' => 'buckaroovoucher', 'amount' => '20.00', 'transaction_id' => 'VC-KEY'],
        ]);

        $totals = $this->helper()->getTotals($order);

        $this->assertSame(
            [
                ['buckaroo_fee', 2.0, 3.0, 'Payment Fee (Excl. Tax)', []],
                ['buckaroo_already_paid_ID-KEY', 10.0, 7.69, 'Paid with Ideal', []],
                ['buckaroo_already_paid_VC-KEY', 20.0, 15.38, 'Paid with Voucher', []],
            ],
            $this->lines($totals)
        );
        $this->assertSame([10.0, 7.69], [$totals[1]['value'], $totals[1]['base_value']]);
        $this->assertSame([20.0, 15.38], [$totals[2]['value'], $totals[2]['base_value']]);
        foreach ($totals as $total) {
            $this->assertArrayNotHasKey('block_name', $total);
            $this->assertArrayNotHasKey('transaction_id', $total);
        }
    }

    public function testWithoutGroupItemsTheAlreadyPaidAmountIsOneLine(): void
    {
        $this->feeConfig->method('getSalesDisplayType')->willReturn(1);
        $this->groupTransaction->method('getAlreadyPaid')->willReturn(34.0);
        $this->groupTransaction->method('getGroupTransactionItems')->willReturn([]);

        // The same currency needs no conversion, whatever rate is stored.
        $totals = $this->helper()->getTotals($this->makeOrder('EUR', 'EUR', 2.0));

        $this->assertSame(
            [
                ['buckaroo_fee', 2.0, 3.0, 'Payment Fee (Excl. Tax)', []],
                ['buckaroo_already_paid', 34.0, 34.0, 'Paid with Giftcard / Voucher', []],
            ],
            $this->lines($totals)
        );
    }

    public function testWithoutAConversionRateTheAmountIsKept(): void
    {
        $this->feeConfig->method('getSalesDisplayType')->willReturn(1);
        $this->groupTransaction->method('getAlreadyPaid')->willReturn(34.0);
        $this->groupTransaction->method('getGroupTransactionItems')->willReturn([]);

        $totals = $this->helper()->getTotals($this->makeOrder('EUR', 'USD', 0.0));

        $this->assertSame(['buckaroo_already_paid', 34.0, 34.0, 'Paid with Giftcard / Voucher', []], $this->lines($totals)[1]);
    }

    public function testNothingPaidYetShowsOnlyTheFee(): void
    {
        $this->feeConfig->method('getSalesDisplayType')->willReturn(1);
        $this->groupTransaction->method('getAlreadyPaid')->willReturn(0.0);
        $this->groupTransaction->expects($this->never())->method('getGroupTransactionItems');

        $totals = $this->helper()->getTotals($this->makeOrder('EUR', 'EUR', 1.0));

        $this->assertSame([['buckaroo_fee', 2.0, 3.0, 'Payment Fee (Excl. Tax)', []]], $this->lines($totals));
    }

    public function testAFeeWithOnlyABaseAmountIsStillShown(): void
    {
        $this->feeConfig->method('getSalesDisplayType')->willReturn(1);
        $this->groupTransaction->method('getAlreadyPaid')->willReturn(0.0);

        $totals = $this->helper()->getTotals(new DataObject(['buckaroo_fee' => 0.0, 'base_buckaroo_fee' => 3.0]));

        $this->assertSame([['buckaroo_fee', 0.0, 3.0, 'Payment Fee (Excl. Tax)', []]], $this->lines($totals));
    }

    public function testTheOrderIncrementIdIsReadFromTheOrderBehindTheDocument(): void
    {
        $order = $this->makeOrder('EUR', 'EUR', 1.0);
        $invoice = $this->createMock(Invoice::class);
        $invoice->method('getOrder')->willReturn($order);

        $helper = $this->helper();

        $this->assertSame('000000042', $helper->getOrderIncrementId($invoice));
        $this->assertNull($helper->getOrderIncrementId('000000042'));
        $this->assertSame('Payment Fee', (string)$helper->getBuckarooPaymentFeeLabel());
    }

    public function testACreditmemoOfAnOrderWithoutPartialPaymentsHasNoRefundRows(): void
    {
        $this->groupTransaction->method('getAlreadyPaid')->willReturn(0.0);
        $this->groupTransaction->expects($this->never())->method('getGroupTransactionItems');

        $this->assertSame([], $this->creditmemoRows());
    }

    /**
     * A posted amount below what is left on a voucher is offered as posted.
     */
    public function testAPostedPartOfAVoucherIsOffered(): void
    {
        $this->params = ['creditmemo' => ['buckaroo_already_paid' => ['VC-KEY|buckaroovoucher|10' => '3']]];
        $this->groupTransaction->method('getAlreadyPaid')->willReturn(10.0);
        $this->groupTransaction->method('getGroupTransactionItems')->willReturn([
            $this->groupItem('VC-KEY', 'buckaroovoucher', 10.00),
        ]);

        $this->assertSame(['VC-KEY|buckaroovoucher|10' => ['Paid with Voucher', -3.0]], $this->creditmemoRows());
    }

    public function testAnOtherMethodBeforeTheVoucherDoesNotHideIt(): void
    {
        $this->groupTransaction->method('getAlreadyPaid')->willReturn(24.0);
        $this->groupTransaction->method('getGroupTransactionItems')->willReturn([
            $this->groupItem('ID-KEY', 'ideal', 14.00),
            $this->groupItem('VC-KEY', 'buckaroovoucher', 10.00),
        ]);

        $this->assertSame(['VC-KEY|buckaroovoucher|10' => ['Paid with Voucher', -10.0]], $this->creditmemoRows());
    }

    /**
     * A giftcard that cannot be refunded in parts offers its whole amount until fully refunded.
     */
    public function testAGiftcardNotRefundableInPartsOffersItsWholeAmount(): void
    {
        $this->groupTransaction->method('getAlreadyPaid')->willReturn(20.0);
        $this->groupTransaction->method('getGroupTransactionItems')->willReturn([
            $this->groupItem('GA-KEY', 'vvvgiftcard', 10.00, 4.00),
            $this->groupItem('GB-KEY', 'vvvgiftcard', '10.0000', 10.00),
        ]);

        $this->assertSame(['GA-KEY|vvvgiftcard|10' => ['Paid with VVV Cadeaukaart', -10.0]], $this->creditmemoRows());
    }

    /**
     * @return PaymentFee
     */
    private function helper(): PaymentFee
    {
        $request = $this->createMock(RequestInterface::class);
        $request->method('getParams')->willReturnCallback(fn() => $this->params);
        $context = $this->createMock(Context::class);
        $context->method('getRequest')->willReturn($request);

        $this->giftcards->method('getItemByColumnValue')->willReturnMap([
            ['servicecode', 'vvvgiftcard', new DataObject([
                'servicecode'           => 'vvvgiftcard',
                'label'                 => 'VVV Cadeaukaart',
                'is_partial_refundable' => 0,
            ])],
        ]);

        return new PaymentFee(
            $context,
            $this->createMock(AccountConfigProvider::class),
            $this->feeConfig,
            $this->createMock(MethodConfigProviderFactory::class),
            $this->groupTransaction,
            $this->giftcards
        );
    }

    /**
     * @param string $baseCurrency
     * @param string $orderCurrency
     * @param float  $rate
     *
     * @return Order|\PHPUnit\Framework\MockObject\MockObject
     */
    private function makeOrder(string $baseCurrency, string $orderCurrency, float $rate)
    {
        $order = $this->getMockBuilder(Order::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getStore', 'getIncrementId', 'getBaseCurrencyCode', 'getOrderCurrencyCode'])
            ->getMock();
        $order->method('getStore')->willReturn($this->createMock(Store::class));
        $order->method('getIncrementId')->willReturn('000000042');
        $order->method('getBaseCurrencyCode')->willReturn($baseCurrency);
        $order->method('getOrderCurrencyCode')->willReturn($orderCurrency);
        $order->setData([
            'base_to_order_rate'           => $rate,
            'buckaroo_fee'                 => 2.0,
            'buckaroo_fee_tax_amount'      => 0.42,
            'base_buckaroo_fee'            => 3.0,
            'buckaroo_fee_base_tax_amount' => 0.63,
        ]);

        return $order;
    }

    /**
     * @param array $totals
     *
     * @return array
     */
    private function lines(array $totals): array
    {
        return array_map(
            static fn(array $total) => [
                $total['code'],
                round((float)$total['value'], 2),
                round((float)$total['base_value'], 2),
                (string)$total['label'],
                $total['extra_info'],
            ],
            $totals
        );
    }

    /**
     * @return array<string, array{0: string, 1: mixed}>
     */
    private function creditmemoRows(): array
    {
        $order = $this->makeOrder('EUR', 'EUR', 1.0);
        $creditmemo = $this->createMock(Creditmemo::class);
        $creditmemo->method('getOrder')->willReturn($order);

        $totals = [];
        $this->helper()->addAlreadyPayedTotals($creditmemo, $totals);

        $rows = [];
        foreach ($totals as $total) {
            $this->assertSame('buckaroo_already_paid', $total['block_name']);
            $rows[$total['transaction_id']] = [(string)$total['label'], $total['value']];
        }

        return $rows;
    }

    /**
     * @param string       $transactionId
     * @param string       $serviceCode
     * @param float|string $amount
     * @param float|null   $refunded
     *
     * @return GroupTransaction
     */
    private function groupItem(string $transactionId, string $serviceCode, $amount, ?float $refunded = null)
    {
        $item = $this->getMockBuilder(GroupTransaction::class)
            ->disableOriginalConstructor()
            ->onlyMethods([])
            ->getMock();
        $item->setData([
            'transaction_id'  => $transactionId,
            'servicecode'     => $serviceCode,
            'amount'          => $amount,
            'refunded_amount' => $refunded,
        ]);

        return $item;
    }
}
