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

namespace Buckaroo\Magento2\Test\Unit\Observer;

use Buckaroo\Magento2\Helper\PaymentGroupTransaction;
use Buckaroo\Magento2\Model\Idin\VerificationGuard;
use Buckaroo\Magento2\Observer\VoucherOrderCoverageGuard;
use Magento\Framework\DataObject;
use Magento\Framework\Event;
use Magento\Framework\Event\Observer;
use Magento\Framework\Exception\LocalizedException;
use Magento\Quote\Model\Quote;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Payment;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class VoucherOrderCoverageGuardTest extends TestCase
{
    #[DataProvider('coveredProvider')]
    public function testVoucherOrderCoveredByVouchersIsPlaced(float $alreadyPaid, array $serviceCodes): void
    {
        $this->createGuard($alreadyPaid, $serviceCodes)
            ->execute($this->createObserver('buckaroo_magento2_voucher', 34.0));

        $this->addToAssertionCount(1);
    }

    public static function coveredProvider(): array
    {
        return [
            'exactly covered by a voucher'  => [34.0, ['buckaroovoucher']],
            'within rounding distance'      => [33.995, ['buckaroovoucher']],
            'giftcard and voucher together' => [34.0, ['vvvgiftcard', 'buckaroovoucher']],
        ];
    }

    #[DataProvider('notCoveredProvider')]
    public function testVoucherOrderNotCoveredByVouchersIsRefused(float $alreadyPaid, array $serviceCodes): void
    {
        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('Your vouchers do not cover the order total yet.');

        $this->createGuard($alreadyPaid, $serviceCodes)
            ->execute($this->createObserver('buckaroo_magento2_voucher', 34.0));
    }

    public static function notCoveredProvider(): array
    {
        return [
            'nothing paid'                 => [0.0, []],
            'partly paid by a voucher'     => [24.0, ['buckaroovoucher']],
            'fully paid without a voucher' => [34.0, ['vvvgiftcard']],
        ];
    }

    /**
     * The module itself submits an uncovered quote to record and cancel it after a failed or
     * cancelled payment (push processor, giftcard response); that order must be created
     */
    public function testOrdersTheModuleCreatesAfterThePaymentOutcomeAreNotChecked(): void
    {
        $groupTransaction = $this->createMock(PaymentGroupTransaction::class);
        $groupTransaction->expects($this->never())->method('getAlreadyPaid');

        $quote = $this->getMockBuilder(Quote::class)->disableOriginalConstructor()->onlyMethods([])->getMock();
        $quote->setData(VerificationGuard::FLAG_ALREADY_CHECKED, true);

        (new VoucherOrderCoverageGuard($groupTransaction))
            ->execute($this->createObserver('buckaroo_magento2_voucher', 34.0, $quote));
    }

    public function testOtherMethodsAreNotChecked(): void
    {
        $groupTransaction = $this->createMock(PaymentGroupTransaction::class);
        $groupTransaction->expects($this->never())->method('getAlreadyPaid');

        (new VoucherOrderCoverageGuard($groupTransaction))
            ->execute($this->createObserver('buckaroo_magento2_ideal', 34.0));
    }

    private function createGuard(float $alreadyPaid, array $serviceCodes): VoucherOrderCoverageGuard
    {
        $groupTransaction = $this->createMock(PaymentGroupTransaction::class);
        $groupTransaction->method('getAlreadyPaid')->with('000000042')->willReturn($alreadyPaid);
        $groupTransaction->method('getGroupTransactionItems')->with('000000042')->willReturn(
            array_map(function (string $serviceCode) {
                return new DataObject(['servicecode' => $serviceCode]);
            }, $serviceCodes)
        );

        return new VoucherOrderCoverageGuard($groupTransaction);
    }

    private function createObserver(string $method, float $grandTotal, ?Quote $quote = null): Observer
    {
        $payment = $this->createMock(Payment::class);
        $payment->method('getMethod')->willReturn($method);

        $order = $this->getMockBuilder(Order::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getPayment', 'getIncrementId', 'getGrandTotal'])
            ->getMock();
        $order->method('getPayment')->willReturn($payment);
        $order->method('getIncrementId')->willReturn('000000042');
        $order->method('getGrandTotal')->willReturn($grandTotal);

        return new Observer(['event' => new Event(['order' => $order, 'quote' => $quote])]);
    }
}
