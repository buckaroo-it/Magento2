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
