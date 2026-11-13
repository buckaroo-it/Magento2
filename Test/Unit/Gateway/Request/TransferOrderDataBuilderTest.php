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

use Buckaroo\Magento2\Gateway\Request\TransferOrderDataBuilder;
use Buckaroo\Magento2\Model\ConfigProvider\Method\Transfer;
use Magento\Store\Model\Store;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;

class TransferOrderDataBuilderTest extends AbstractDataBuilderTest
{
    /**
     * @var Transfer|MockObject
     */
    private $transferConfigMock;

    /**
     * @var Store|MockObject
     */
    private $storeMock;

    /**
     * @var TransferOrderDataBuilder
     */
    private $dataBuilder;

    protected function setUp(): void
    {
        parent::setUp();

        $this->storeMock = $this->createMock(Store::class);
        $this->orderMock->method('getStore')->willReturn($this->storeMock);

        $this->transferConfigMock = $this->createMock(Transfer::class);
        $this->transferConfigMock->method('getDueDateFormated')->willReturn('2026-11-13');

        $this->dataBuilder = new TransferOrderDataBuilder($this->transferConfigMock);
    }

    /**
     * SendMail tells Buckaroo whether to email the payment instructions. It must follow the
     * payment instruction email setting of the order's store, never the order confirmation email.
     */
    #[DataProvider('paymentInstructionEmailProvider')]
    public function testSendMailFollowsPaymentInstructionEmail(bool $paymentInstructionEmail): void
    {
        $this->transferConfigMock->expects($this->once())
            ->method('hasPaymentInstructionEmail')
            ->with($this->storeMock)
            ->willReturn($paymentInstructionEmail);
        $this->transferConfigMock->expects($this->never())->method('hasOrderEmail');

        $result = $this->dataBuilder->build(['payment' => $this->getPaymentDOMock()]);

        $this->assertSame($paymentInstructionEmail, $result['sendMail']);
        $this->assertSame('2026-11-13', $result['dateDue']);
    }

    public static function paymentInstructionEmailProvider(): array
    {
        return [
            'Send Payment Instruction Email = Yes' => [true],
            'Send Payment Instruction Email = No'  => [false],
        ];
    }
}
