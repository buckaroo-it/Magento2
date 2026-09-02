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

namespace Buckaroo\Magento2\Test\Unit\Gateway\Http;

use Buckaroo\Magento2\Gateway\Http\SDKTransferFactory;
use Buckaroo\Magento2\Test\BaseTest;
use Magento\Payment\Gateway\Http\TransferBuilder;
use Magento\Payment\Gateway\Http\TransferInterface;

class SDKTransferFactoryTest extends BaseTest
{
    public function testCreateBuildsPostTransferCarryingTheExactRequestBody(): void
    {
        $request = [
            'payment_method' => 'ideal',
            'invoice' => '100000123',
            'amountDebit' => 10.5,
            'clientIP' => ['address' => '127.0.0.1', 'type' => 4],
        ];

        $factory = new SDKTransferFactory(new TransferBuilder());

        $transfer = $factory->create($request);

        $this->assertInstanceOf(TransferInterface::class, $transfer);
        $this->assertSame($request, $transfer->getBody());
        $this->assertSame('POST', $transfer->getMethod());
        $this->assertSame([], $transfer->getHeaders());
        $this->assertSame('', $transfer->getUri());
    }

    public function testCreateWithEmptyRequestStillProducesPostTransferWithEmptyBody(): void
    {
        $factory = new SDKTransferFactory(new TransferBuilder());

        $transfer = $factory->create([]);

        $this->assertSame([], $transfer->getBody());
        $this->assertSame('POST', $transfer->getMethod());
    }

    public function testCreateDelegatesBodyAndMethodToInjectedBuilder(): void
    {
        $request = ['order' => '100000999'];
        $expectedTransfer = $this->createMock(TransferInterface::class);

        $transferBuilder = $this->createMock(TransferBuilder::class);
        $transferBuilder->expects($this->once())
            ->method('setBody')
            ->with($request)
            ->willReturnSelf();
        $transferBuilder->expects($this->once())
            ->method('setMethod')
            ->with('POST')
            ->willReturnSelf();
        $transferBuilder->expects($this->once())
            ->method('build')
            ->willReturn($expectedTransfer);

        $factory = new SDKTransferFactory($transferBuilder);

        $this->assertSame($expectedTransfer, $factory->create($request));
    }
}
