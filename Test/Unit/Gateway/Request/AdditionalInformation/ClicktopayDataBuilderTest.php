<?php
declare(strict_types=1);

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

namespace Buckaroo\Magento2\Test\Unit\Gateway\Request\AdditionalInformation;

use Buckaroo\Magento2\Gateway\Request\AdditionalInformation\ClicktopayDataBuilder;
use Magento\Payment\Gateway\Data\PaymentDataObjectInterface;
use Magento\Payment\Model\InfoInterface;
use PHPUnit\Framework\TestCase;

class ClicktopayDataBuilderTest extends TestCase
{
    private ClicktopayDataBuilder $builder;

    protected function setUp(): void
    {
        $this->builder = new ClicktopayDataBuilder();
    }

    public function testBuildReturnsTransientTokenAndIdentifier(): void
    {
        $payment = $this->createMock(InfoInterface::class);
        $payment->method('getAdditionalInformation')
            ->willReturnMap([
                ['transient_token', 'tok_abc123'],
                ['identifier', 'sess_xyz789'],
            ]);

        $paymentDO = $this->createMock(PaymentDataObjectInterface::class);
        $paymentDO->method('getPayment')->willReturn($payment);

        $result = $this->builder->build(['payment' => $paymentDO]);

        $this->assertSame('tok_abc123', $result['transientToken']);
        $this->assertSame('sess_xyz789', $result['identifier']);
    }

    public function testBuildWithEmptyIdentifierReturnsEmptyString(): void
    {
        $payment = $this->createMock(InfoInterface::class);
        $payment->method('getAdditionalInformation')
            ->willReturnMap([
                ['transient_token', 'tok_abc123'],
                ['identifier', null],
            ]);

        $paymentDO = $this->createMock(PaymentDataObjectInterface::class);
        $paymentDO->method('getPayment')->willReturn($payment);

        $result = $this->builder->build(['payment' => $paymentDO]);

        $this->assertSame('tok_abc123', $result['transientToken']);
        $this->assertSame('', $result['identifier']);
    }

    public function testBuildThrowsExceptionWhenTransientTokenMissing(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Click to Pay transient token is missing');

        $payment = $this->createMock(InfoInterface::class);
        $payment->method('getAdditionalInformation')
            ->willReturnMap([
                ['transient_token', null],
                ['identifier', null],
            ]);

        $paymentDO = $this->createMock(PaymentDataObjectInterface::class);
        $paymentDO->method('getPayment')->willReturn($payment);

        $this->builder->build(['payment' => $paymentDO]);
    }
}
