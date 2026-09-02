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
    private $orderNumberDataBuilder;

    /**
     * @inheritdoc
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->orderNumberDataBuilder = new OrderNumberDataBuilder();
    }

    /**
     */
    public function testBuild(): void
    {
        $paymentDO = $this->createMock(PaymentDataObjectInterface::class);

        $order = $this->createMock(Order::class);

        $orderAdapter = $this->createMock(OrderAdapter::class);

        $orderAdapter->method('getOrder')->willReturn($order);
                $paymentDO->method('getOrder')->willReturn($orderAdapter);

        $order->method('getIncrementId')->willReturn('100000001');

        $result = $this->orderNumberDataBuilder->build(['payment' => $paymentDO]);
        $this->assertEquals(['order' => '100000001'], $result);
    }
}
