<?php

declare(strict_types=1);

namespace Buckaroo\Magento2\Test\Unit\Gateway\Request\BasicParameter;

use Buckaroo\Magento2\Gateway\Data\Order\OrderAdapter;
use Buckaroo\Magento2\Gateway\Request\BasicParameter\OrderNumberDataBuilder;
use Buckaroo\Magento2\Test\Unit\Gateway\Request\AbstractDataBuilderTest;
use Magento\Payment\Gateway\Data\PaymentDataObjectInterface;
use Magento\Sales\Model\Order;

class OrderNumberDataBuilderTest extends AbstractDataBuilderTest
{
    /**
     * @var OrderNumberDataBuilder
     */
    private OrderNumberDataBuilder $orderNumberDataBuilder;

    /**
     * @inheritdoc
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->orderNumberDataBuilder = new OrderNumberDataBuilder();
    }

    /**
     * @return void
     */
    public function testBuild(): void
    {
        $paymentDO = $this->getMockBuilder(PaymentDataObjectInterface::class)
            ->disableOriginalConstructor()
            ->getMock();

        $order = $this->getMockBuilder(Order::class)
            ->disableOriginalConstructor()
            ->getMock();

        $orderAdapter = $this->getMockBuilder(OrderAdapter::class)
            ->disableOriginalConstructor()
            ->getMock();

        $orderAdapter->method('getOrder')->willReturn($order);
        $paymentDO->method('getOrder')->willReturn($orderAdapter);

        $order->method('getIncrementId')->willReturn('100000001');

        $result = $this->orderNumberDataBuilder->build(['payment' => $paymentDO]);
        $this->assertEquals(['order' => '100000001'], $result);
    }
}