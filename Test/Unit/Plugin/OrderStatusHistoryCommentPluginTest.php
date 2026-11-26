<?php

namespace Buckaroo\Magento2\Test\Unit\Plugin;

use Buckaroo\Magento2\Model\ConfigProvider\Method\Klarnakp;
use Buckaroo\Magento2\Plugin\OrderStatusHistoryCommentPlugin;
use Buckaroo\Magento2\Service\CheckPaymentType;
use Buckaroo\Magento2\Test\BaseTest;
use Magento\Framework\Escaper;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Payment;
use PHPUnit\Framework\Attributes\DataProvider;

class OrderStatusHistoryCommentPluginTest extends BaseTest
{
    protected $instanceClass = OrderStatusHistoryCommentPlugin::class;

    public function testPlazaOfflineRefundCommentIsNormalizedToOnline(): void
    {
        $orderMock = $this->getOrderMock('A7BDD18D052843098E3461FE3EDA423B');
        $comment = 'We refunded $59.00 offline. Transaction ID: "A7BDD18D052843098E3461FE3EDA423B-capture"';

        $result = $this->getInstance([
            'checkPaymentType' => $this->getCheckPaymentTypeMock(true),
        ])->beforeAddStatusHistoryComment($orderMock, $comment, 'closed');

        $this->assertStringContainsString('online', $result[0]);
        $this->assertStringNotContainsString('offline', $result[0]);
    }

    public function testAdminOfflineRefundCommentIsLeftUntouched(): void
    {
        $orderMock = $this->getOrderMock(null);
        $comment = 'We refunded $59.00 offline. Transaction ID: "A7BDD18D052843098E3461FE3EDA423B-capture"';

        $result = $this->getInstance([
            'checkPaymentType' => $this->getCheckPaymentTypeMock(true),
        ])->beforeAddStatusHistoryComment($orderMock, $comment, 'closed');

        $this->assertSame([$comment, 'closed'], $result);
    }

    public function testKlarnaPartialCancellationCommentUsesTheRemainingAmount(): void
    {
        $orderMock = $this->getPartialCaptureOrderMock(41.40, 29.40);
        $comment = 'Canceled order online Amount: €41.40.';

        $result = $this->getInstance([
            'checkPaymentType' => $this->getCheckPaymentTypeMock(true),
        ])->beforeAddStatusHistoryComment($orderMock, $comment, false);

        $this->assertSame('Canceled order online Amount: €12.00.', $result[0]);
    }

    public function testFullKlarnaCancellationCommentIsLeftUntouched(): void
    {
        $orderMock = $this->getPartialCaptureOrderMock(41.40, 0.0);
        $comment = 'Canceled order online Amount: €41.40.';

        $result = $this->getInstance([
            'checkPaymentType' => $this->getCheckPaymentTypeMock(true),
        ])->beforeAddStatusHistoryComment($orderMock, $comment, false);

        $this->assertSame([$comment, false], $result);
    }

    public function testNonBuckarooOfflineRefundCommentIsLeftUntouched(): void
    {
        $orderMock = $this->getOrderMock(null);
        $comment = 'We refunded $59.00 offline. Transaction ID: "A7BDD18D052843098E3461FE3EDA423B-capture"';

        $result = $this->getInstance([
            'checkPaymentType' => $this->getCheckPaymentTypeMock(false),
        ])->beforeAddStatusHistoryComment($orderMock, $comment, false);

        $this->assertSame([$comment, false], $result);
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function plazaRefundCommentProvider(): array
    {
        $link = '<a href="https://plaza.buckaroo.nl/Transaction/Transactions/Details?transactionKey=KEY123"'
            . ' target="_blank" rel="noopener noreferrer">KEY123</a>';

        return [
            'existing transaction id is replaced by the plaza link' => [
                'We refunded $59.00 offline. Transaction ID: "KEY123-capture"',
                'We refunded $59.00 online. Transaction ID: "' . $link . '"',
            ],
            'lower case transaction id label' => [
                'We refunded $59.00 offline. transaction id: "KEY123-capture"',
                'We refunded $59.00 online. Transaction ID: "' . $link . '"',
            ],
            'missing transaction id is appended' => [
                'We refunded $59.00 offline.  ',
                'We refunded $59.00 online. Transaction ID: "' . $link . '"',
            ],
            'only the first offline and transaction id are rewritten' => [
                'We refunded $59.00 offline. Transaction ID: "A" offline Transaction ID: "B"',
                'We refunded $59.00 online. Transaction ID: "' . $link . '" offline Transaction ID: "B"',
            ],
        ];
    }

    #[DataProvider('plazaRefundCommentProvider')]
    public function testPlazaRefundCommentGetsTheTransactionLink(string $comment, string $expected): void
    {
        $result = $this->getInstance([
            'checkPaymentType' => $this->getCheckPaymentTypeMock(true),
            'escaper'          => $this->getEscaperMock(),
        ])->beforeAddStatusHistoryComment($this->getOrderMock('KEY123'), $comment, 'closed');

        $this->assertSame([$expected, 'closed'], $result);
    }

    public function testStatusDefaultsToFalse(): void
    {
        $comment = 'Payment received';

        $result = $this->getInstance([
            'checkPaymentType' => $this->getCheckPaymentTypeMock(true),
        ])->beforeAddStatusHistoryComment($this->getOrderMock('KEY123'), $comment);

        $this->assertSame([$comment, false], $result);
    }

    public function testNonBuckarooRefundCommentWithAKeyIsLeftUntouched(): void
    {
        $comment = 'We refunded $59.00 offline.';

        $result = $this->getInstance([
            'checkPaymentType' => $this->getCheckPaymentTypeMock(false),
            'escaper'          => $this->getEscaperMock(),
        ])->beforeAddStatusHistoryComment($this->getOrderMock('KEY123'), $comment, 'closed');

        $this->assertSame([$comment, 'closed'], $result);
    }

    public function testRefundCommentOnOrderWithoutPaymentIsLeftUntouched(): void
    {
        $orderMock = $this->getMockBuilder(Order::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getPayment'])
            ->getMock();
        $orderMock->method('getPayment')->willReturn(null);
        $comment = 'We refunded $59.00 offline.';

        $result = $this->getInstance([
            'checkPaymentType' => $this->getCheckPaymentTypeMock(true),
        ])->beforeAddStatusHistoryComment($orderMock, $comment, 'closed');

        $this->assertSame([$comment, 'closed'], $result);
    }

    /**
     * Only a Klarna cancellation of a partly captured order quotes the remaining amount.
     */
    #[DataProvider('untouchedCancellationProvider')]
    public function testCancellationCommentIsLeftUntouched(
        string $method,
        float $baseTotalPaid,
        string $comment
    ): void {
        $orderMock = $this->getPartialCaptureOrderMock(41.40, $baseTotalPaid, $method);

        $result = $this->getInstance([
            'checkPaymentType' => $this->getCheckPaymentTypeMock(true),
        ])->beforeAddStatusHistoryComment($orderMock, $comment, false);

        $this->assertSame([$comment, false], $result);
    }

    /**
     * @return array<string, array{string, float, string}>
     */
    public static function untouchedCancellationProvider(): array
    {
        return [
            'not a cancellation'    => [Klarnakp::CODE, 29.40, 'Captured amount Amount: €41.40.'],
            'other payment method'  => ['buckaroo_magento2_ideal', 29.40, 'Canceled order online Amount: €41.40.'],
            'fully captured order'  => [Klarnakp::CODE, 41.40, 'Canceled order online Amount: €41.40.'],
        ];
    }

    private function getEscaperMock(): Escaper
    {
        $escaper = $this->getMockBuilder(Escaper::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['escapeUrl', 'escapeHtml'])
            ->getMock();
        $escaper->method('escapeUrl')->willReturnArgument(0);
        $escaper->method('escapeHtml')->willReturnArgument(0);

        return $escaper;
    }

    private function getOrderMock(?string $plazaRefundTransactionKey): Order
    {
        $paymentMock = $this->getMockBuilder(Payment::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getAdditionalInformation'])
            ->getMock();
        $paymentMock->method('getAdditionalInformation')
            ->with('buckaroo_refund_transaction_key')
            ->willReturn($plazaRefundTransactionKey);

        $orderMock = $this->getMockBuilder(Order::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getPayment'])
            ->getMock();
        $orderMock->method('getPayment')->willReturn($paymentMock);

        return $orderMock;
    }

    private function getPartialCaptureOrderMock(
        float $baseGrandTotal,
        float $baseTotalPaid,
        string $method = Klarnakp::CODE
    ): Order
    {
        $paymentMock = $this->getMockBuilder(Payment::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getMethod', 'formatAmount', 'formatPrice', 'getAdditionalInformation'])
            ->getMock();
        $paymentMock->method('getMethod')->willReturn($method);
        $paymentMock->method('formatAmount')->willReturnArgument(0);
        $paymentMock->method('formatPrice')->willReturnCallback(
            function ($amount) {
                return '€' . number_format((float)$amount, 2, '.', '');
            }
        );

        $orderMock = $this->getMockBuilder(Order::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getPayment', 'getBaseGrandTotal', 'getBaseTotalPaid'])
            ->getMock();
        $orderMock->method('getPayment')->willReturn($paymentMock);
        $orderMock->method('getBaseGrandTotal')->willReturn($baseGrandTotal);
        $orderMock->method('getBaseTotalPaid')->willReturn($baseTotalPaid);

        return $orderMock;
    }

    private function getCheckPaymentTypeMock(bool $isBuckarooPayment): CheckPaymentType
    {
        $checkPaymentTypeMock = $this->getMockBuilder(CheckPaymentType::class)
            ->onlyMethods(['isBuckarooPayment'])
            ->getMock();
        $checkPaymentTypeMock->method('isBuckarooPayment')->willReturn($isBuckarooPayment);

        return $checkPaymentTypeMock;
    }
}
