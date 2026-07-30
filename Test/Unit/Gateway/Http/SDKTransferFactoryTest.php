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
