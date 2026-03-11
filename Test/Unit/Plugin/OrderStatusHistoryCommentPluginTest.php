<?php

namespace Buckaroo\Magento2\Test\Unit\Plugin;

use Buckaroo\Magento2\Plugin\OrderStatusHistoryCommentPlugin;
use Buckaroo\Magento2\Service\CheckPaymentType;
use Buckaroo\Magento2\Test\BaseTest;
use Magento\Sales\Model\Order;

class OrderStatusHistoryCommentPluginTest extends BaseTest
{
    protected $instanceClass = OrderStatusHistoryCommentPlugin::class;

    public function testOfflineRefundCommentIsCleanedForBuckarooPayment(): void
    {
        $orderMock = $this->getOrderMock();
        $comment = 'We refunded $59.00 offline. Transaction ID: "A7BDD18D052843098E3461FE3EDA423B-capture"';

        $result = $this->getInstance([
            'checkPaymentType' => $this->getCheckPaymentTypeMock(true),
        ])->beforeAddStatusHistoryComment($orderMock, $comment, 'closed');

        $this->assertSame(['We refunded $59.00 offline.', 'closed'], $result);
    }

    public function testOnlineRefundCommentIsLeftUntouched(): void
    {
        $orderMock = $this->getOrderMock();
        $comment = 'We refunded $59.00 online. Transaction ID: "A7BDD18D052843098E3461FE3EDA423B-refund"';

        $result = $this->getInstance([
            'checkPaymentType' => $this->getCheckPaymentTypeMock(true),
        ])->beforeAddStatusHistoryComment($orderMock, $comment, 'processing');

        $this->assertSame([$comment, 'processing'], $result);
    }

    public function testNonBuckarooOfflineRefundCommentIsLeftUntouched(): void
    {
        $orderMock = $this->getOrderMock();
        $comment = 'We refunded $59.00 offline. Transaction ID: "A7BDD18D052843098E3461FE3EDA423B-capture"';

        $result = $this->getInstance([
            'checkPaymentType' => $this->getCheckPaymentTypeMock(false),
        ])->beforeAddStatusHistoryComment($orderMock, $comment, false);

        $this->assertSame([$comment, false], $result);
    }

    public function testBuckarooNonRefundCommentContainingTransactionIdIsLeftUntouched(): void
    {
        $orderMock = $this->getOrderMock();
        $comment = 'Registered notification about captured amount of $59.00. Transaction ID: "A7BDD18D052843098E3461FE3EDA423B-capture"';

        $result = $this->getInstance([
            'checkPaymentType' => $this->getCheckPaymentTypeMock(true),
        ])->beforeAddStatusHistoryComment($orderMock, $comment, 'processing');

        $this->assertSame([$comment, 'processing'], $result);
    }

    private function getOrderMock(): Order
    {
        $paymentMock = $this->createMock(\Magento\Sales\Api\Data\OrderPaymentInterface::class);

        $orderMock = $this->getMockBuilder(Order::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getPayment'])
            ->getMock();
        $orderMock->method('getPayment')->willReturn($paymentMock);

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
