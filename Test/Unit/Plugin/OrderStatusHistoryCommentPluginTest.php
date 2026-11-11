<?php

namespace Buckaroo\Magento2\Test\Unit\Plugin;

use Buckaroo\Magento2\Model\ConfigProvider\Method\Klarnakp;
use Buckaroo\Magento2\Plugin\OrderStatusHistoryCommentPlugin;
use Buckaroo\Magento2\Service\CheckPaymentType;
use Buckaroo\Magento2\Test\BaseTest;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Payment;

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

    private function getPartialCaptureOrderMock(float $baseGrandTotal, float $baseTotalPaid): Order
    {
        $paymentMock = $this->getMockBuilder(Payment::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getMethod', 'formatAmount', 'formatPrice', 'getAdditionalInformation'])
            ->getMock();
        $paymentMock->method('getMethod')->willReturn(Klarnakp::CODE);
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
