<?php
declare(strict_types=1);

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
