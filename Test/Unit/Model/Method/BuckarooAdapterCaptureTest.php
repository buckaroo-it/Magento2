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

namespace Buckaroo\Magento2\Test\Unit\Model\Method;

use Buckaroo\Magento2\Model\Method\BuckarooAdapter;
use Buckaroo\Magento2\Test\BaseTest;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NotFoundException;
use Magento\Payment\Gateway\Command\CommandPoolInterface;
use Magento\Payment\Gateway\CommandInterface;
use Magento\Payment\Gateway\Config\ValueHandlerInterface;
use Magento\Payment\Gateway\Config\ValueHandlerPoolInterface;
use Magento\Payment\Gateway\Data\PaymentDataObjectFactory;
use Magento\Payment\Gateway\Data\PaymentDataObjectInterface;
use Magento\Sales\Model\Order\Payment;

/**
 * "Capture Online" on a method that only pays at checkout (no capture command)
 *
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class BuckarooAdapterCaptureTest extends BaseTest
{
    protected $instanceClass = BuckarooAdapter::class;

    private function adapter(CommandPoolInterface $commandPool): BuckarooAdapter
    {
        $handler = $this->createMock(ValueHandlerInterface::class);
        $handler->method('handle')->willReturn('1');
        $handlerPool = $this->createMock(ValueHandlerPoolInterface::class);
        $handlerPool->method('get')->willReturn($handler);

        $paymentDataObjectFactory = $this->createMock(PaymentDataObjectFactory::class);
        $paymentDataObjectFactory->method('create')->willReturn($this->createMock(PaymentDataObjectInterface::class));

        return $this->getInstance([
            'valueHandlerPool' => $handlerPool,
            'commandPool' => $commandPool,
            'paymentDataObjectFactory' => $paymentDataObjectFactory,
            'code' => 'buckaroo_magento2_googlepay',
            'commandExecutor' => null,
        ]);
    }

    public function testCaptureWithoutACaptureCommandExplainsHowToInvoice(): void
    {
        $commandPool = $this->createMock(CommandPoolInterface::class);
        $commandPool->method('get')->with('capture')
            ->willThrowException(new NotFoundException(__('The "capture" command doesn\'t exist.')));

        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage("Create the invoice with 'Capture Offline'.");

        $this->adapter($commandPool)->capture($this->createMock(Payment::class), 26.62);
    }

    public function testCaptureWithACaptureCommandStillCaptures(): void
    {
        $command = $this->createMock(CommandInterface::class);
        $command->expects($this->once())->method('execute');
        $commandPool = $this->createMock(CommandPoolInterface::class);
        $commandPool->method('get')->with('capture')->willReturn($command);

        $adapter = $this->adapter($commandPool);

        $this->assertSame($adapter, $adapter->capture($this->createMock(Payment::class), 26.62));
    }
}
