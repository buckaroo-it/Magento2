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

namespace Buckaroo\Magento2\Test\Unit\Gateway\Request;

use Buckaroo\Magento2\Gateway\Data\Order\OrderAdapter;
use Magento\Payment\Gateway\Data\PaymentDataObjectInterface;
use Magento\Payment\Model\InfoInterface;
use Magento\Payment\Model\MethodInterface;
use Magento\Sales\Model\Order;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

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
