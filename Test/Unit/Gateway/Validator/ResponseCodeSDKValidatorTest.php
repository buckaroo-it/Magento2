<?php

declare(strict_types=1);

namespace Buckaroo\Magento2\Test\Unit\Gateway\Validator;

use Buckaroo\Magento2\Gateway\Validator\ResponseCodeSDKValidator;
use Buckaroo\Transaction\Response\TransactionResponse;
use Magento\Framework\App\Request\Http;
use Magento\Payment\Gateway\Data\PaymentDataObjectInterface;
use Magento\Payment\Gateway\Validator\ResultInterface;
use Magento\Payment\Gateway\Validator\ResultInterfaceFactory;
use Magento\Payment\Model\MethodInterface;
use Magento\Sales\Model\Order\Payment as OrderPayment;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class ResponseCodeSDKValidatorTest extends TestCase
{
    /**
     * The gateway description holds no reason, so it must be handed over empty for the
     * gateway command to replace with the standard decline message, and logged as critical.
     *
     * @dataProvider descriptionWithoutReasonProvider
     */
    public function testDeclineWithoutReasonIsBlankedAndLogged(string $gatewayDescription): void
    {
        $resultFactory = $this->createMock(ResultInterfaceFactory::class);
        $result = $this->createMock(ResultInterface::class);
        $logger = $this->createMock(LoggerInterface::class);

        $transaction = $this->createMock(TransactionResponse::class);
        $transaction->method('getStatusCode')->willReturn(490);
        $transaction->method('getSubStatusCode')->willReturn('S996');
        $transaction->method('getSomeError')->willReturn($gatewayDescription);

        $resultFactory->expects($this->once())
            ->method('create')
            ->with([
                'isValid' => false,
                'failsDescription' => [''],
                'errorCodes' => [490],
            ])
            ->willReturn($result);

        $logger->expects($this->once())
            ->method('critical')
            ->with($this->stringContains('without a failure reason'));

        $validator = new ResponseCodeSDKValidator(
            $this->createMock(\Buckaroo\Magento2\Helper\Data::class),
            $this->createMock(Http::class),
            $resultFactory,
            $logger
        );

        $this->assertSame($result, $validator->validate([
            'response' => ['object' => $transaction],
            'payment' => $this->createPaymentMock(),
        ]));
    }

    /**
     * @return array<string, array<int, string>>
     */
    public static function descriptionWithoutReasonProvider(): array
    {
        return [
            'single full stop' => ['.'],
            'plaza s996 template' => ['An error occurred while processing the transaction: .'],
            'empty description' => [''],
        ];
    }

    public function testDescriptionWithAReasonIsPassedThrough(): void
    {
        $resultFactory = $this->createMock(ResultInterfaceFactory::class);
        $result = $this->createMock(ResultInterface::class);
        $logger = $this->createMock(LoggerInterface::class);

        $transaction = $this->createMock(TransactionResponse::class);
        $transaction->method('getStatusCode')->willReturn(490);
        $transaction->method('getSomeError')->willReturn('Insufficient funds');

        $resultFactory->expects($this->once())
            ->method('create')
            ->with([
                'isValid' => false,
                'failsDescription' => ['Insufficient funds'],
                'errorCodes' => [490],
            ])
            ->willReturn($result);

        $logger->expects($this->never())->method('critical');

        $validator = new ResponseCodeSDKValidator(
            $this->createMock(\Buckaroo\Magento2\Helper\Data::class),
            $this->createMock(Http::class),
            $resultFactory,
            $logger
        );

        $this->assertSame($result, $validator->validate([
            'response' => ['object' => $transaction],
            'payment' => $this->createPaymentMock(),
        ]));
    }

    /**
     * @return PaymentDataObjectInterface
     */
    private function createPaymentMock(): PaymentDataObjectInterface
    {
        $paymentMethod = $this->createMock(MethodInterface::class);
        $paymentMethod->method('getCode')->willReturn('buckaroo_magento2_billink');
        $paymentModel = $this->createMock(OrderPayment::class);
        $paymentModel->method('getMethodInstance')->willReturn($paymentMethod);
        $payment = $this->createMock(PaymentDataObjectInterface::class);
        $payment->method('getPayment')->willReturn($paymentModel);

        return $payment;
    }
}
