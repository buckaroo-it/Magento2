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

namespace Buckaroo\Magento2\Test\Unit\Gateway\Skip;

use Buckaroo\Magento2\Gateway\Skip\GiftcardOrderSkip;
use Buckaroo\Magento2\Helper\PaymentGroupTransaction;
use Magento\Payment\Gateway\Data\PaymentDataObjectInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Payment;
use PHPUnit\Framework\TestCase;

class GiftcardOrderSkipTest extends TestCase
{
    /**
     * @return array<string, array{float, float, bool}>
     */
    public static function amountProvider(): array
    {
        return [
            'nothing paid'              => [0.0, 50.0, false],
            'partially paid'            => [20.0, 50.0, false],
            'fully paid'                => [50.0, 50.0, true],
            'paid within rounding'      => [49.995, 50.0, true],
            'total raised after paying' => [50.0, 57.95, false],
        ];
    }

    /**
     * @dataProvider amountProvider
     */
    public function testSkipsOnlyWhenThePaidAmountCoversTheOrder(
        float $alreadyPaid,
        float $grandTotal,
        bool $expected
    ): void {
        $order = $this->createMock(Order::class);
        $order->method('getIncrementId')->willReturn('000000123');
        $order->method('getGrandTotal')->willReturn($grandTotal);

        $payment = $this->createMock(Payment::class);
        $payment->method('getOrder')->willReturn($order);

        $paymentDO = $this->createMock(PaymentDataObjectInterface::class);
        $paymentDO->method('getPayment')->willReturn($payment);

        $groupTransaction = $this->createMock(PaymentGroupTransaction::class);
        $groupTransaction->method('getAlreadyPaid')->with('000000123')->willReturn($alreadyPaid);

        $skip = new GiftcardOrderSkip($groupTransaction);

        $this->assertSame($expected, $skip->isSkip(['payment' => $paymentDO]));
    }
}
