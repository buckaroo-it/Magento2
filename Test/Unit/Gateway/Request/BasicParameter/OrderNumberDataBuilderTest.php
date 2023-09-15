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
