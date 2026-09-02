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

namespace Buckaroo\Magento2\Test\Unit\Gateway\Request;

use Buckaroo\Magento2\Gateway\Data\Order\OrderAdapter;
use Magento\Payment\Gateway\Data\PaymentDataObjectInterface;
use Magento\Payment\Model\InfoInterface;
use Magento\Payment\Model\MethodInterface;
use Magento\Sales\Model\Order;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * @SuppressWarnings(PHPMD.NumberOfChildren)
 */
abstract class AbstractDataBuilderTest extends TestCase
{
    /**
     * @var MockObject|Order
     */
    protected $orderMock;

    /**
     * @var (MethodInterface&MockObject)|MockObject
     */
    protected $paymentMethodInstanceMock;

    /**
     * @inheritdoc
     */
    protected function setUp(): void
    {
        $this->orderMock = $this->createMock(Order::class);

        $this->paymentMethodInstanceMock = $this->createMock(MethodInterface::class);
    }

    /**
     * Get Payment Data Object from buildSubject
     */
    protected function getPaymentDOMock()
    {
        $paymentDOMock = $this->createMock(PaymentDataObjectInterface::class);

        $orderAdapter = $this->createMock(OrderAdapter::class);

        $orderAdapter->method('getOrder')
            ->willReturn($this->orderMock);

        $paymentDOMock->method('getOrder')
            ->willReturn($orderAdapter);

        $infoInterface = $this->createMock(InfoInterface::class);

        $infoInterface->method('getMethodInstance')
            ->willReturn($this->paymentMethodInstanceMock);

        $paymentDOMock->method('getPayment')
            ->willReturn($infoInterface);

        return $paymentDOMock;
    }
}
