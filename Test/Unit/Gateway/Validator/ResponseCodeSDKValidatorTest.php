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
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class ResponseCodeSDKValidatorTest extends TestCase
{
    /**
     * The gateway description holds no reason, so it must be handed over empty for the
     * gateway command to replace with the standard decline message, and logged as critical.
     */
    #[DataProvider('descriptionWithoutReasonProvider')]
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

    public function testCompletedGroupRefundIsValidWithoutResponseObject(): void
    {
        $resultFactory = $this->createMock(ResultInterfaceFactory::class);
        $result = $this->createMock(ResultInterface::class);
        $resultFactory->expects($this->once())
            ->method('create')
            ->with([
                'isValid' => true,
                'failsDescription' => ['Refund completed via group transactions'],
                'errorCodes' => [],
            ])
            ->willReturn($result);

        $validator = $this->createValidator($resultFactory);

        $this->assertSame($result, $validator->validate([
            'response' => ['group_transaction_refund_complete' => true],
        ]));
    }

    public function testGroupRefundFlagOtherThanTrueStillRequiresResponseObject(): void
    {
        $resultFactory = $this->createMock(ResultInterfaceFactory::class);
        $result = $this->createMock(ResultInterface::class);
        $resultFactory->expects($this->once())
            ->method('create')
            ->with([
                'isValid' => false,
                'failsDescription' => ['Data must be an instance of "TransactionResponse"'],
                'errorCodes' => [],
            ])
            ->willReturn($result);

        $validator = $this->createValidator($resultFactory);

        $this->assertSame($result, $validator->validate([
            'response' => ['group_transaction_refund_complete' => false],
        ]));
    }

    public function testSuccessStatusIsValid(): void
    {
        $resultFactory = $this->createMock(ResultInterfaceFactory::class);
        $result = $this->createMock(ResultInterface::class);
        $resultFactory->expects($this->once())
            ->method('create')
            ->with([
                'isValid' => true,
                'failsDescription' => ['Transaction Success'],
                'errorCodes' => [190],
            ])
            ->willReturn($result);

        $validator = $this->createValidator($resultFactory, $this->createRequestMock('1'));

        $this->assertSame($result, $validator->validate([
            'response' => ['object' => $this->createResponse(['Status' => ['Code' => ['Code' => 190]]])],
        ]));
        $this->assertSame(190, $validator->getStatusCode());
    }

    /**
     * A cancelled-by-user status must stay a failure and keep its own code.
     */
    public function testCancelledStatusIsNotTurnedIntoSuccess(): void
    {
        $resultFactory = $this->createMock(ResultInterfaceFactory::class);
        $result = $this->createMock(ResultInterface::class);
        $resultFactory->expects($this->once())
            ->method('create')
            ->with([
                'isValid' => false,
                'failsDescription' => ['Cancelled by consumer'],
                'errorCodes' => [890],
            ])
            ->willReturn($result);

        $validator = $this->createValidator($resultFactory);

        $this->assertSame($result, $validator->validate([
            'response' => ['object' => $this->createResponse([
                'Status' => ['Code' => ['Code' => 890]],
                'Message' => 'Cancelled by consumer',
            ])],
            'payment' => $this->createPaymentMock(),
        ]));
        $this->assertSame(890, $validator->getStatusCode());
    }

    /**
     * No status code plus the cancel request parameter means the shopper cancelled.
     */
    public function testMissingStatusWithCancelParamIsCancelledByUser(): void
    {
        $resultFactory = $this->createMock(ResultInterfaceFactory::class);
        $result = $this->createMock(ResultInterface::class);
        $resultFactory->expects($this->once())
            ->method('create')
            ->with([
                'isValid' => false,
                'failsDescription' => ['Shopper left'],
                'errorCodes' => [890],
            ])
            ->willReturn($result);

        $validator = $this->createValidator($resultFactory, $this->createRequestMock('1'));

        $this->assertSame($result, $validator->validate([
            'response' => ['object' => $this->createResponse(['Message' => 'Shopper left'])],
            'payment' => $this->createPaymentMock(),
        ]));
    }

    /**
     * A missing status reported as cancelled by the response is treated as success.
     */
    public function testMissingStatusOnCancelledResponseIsSuccess(): void
    {
        $transaction = $this->createMock(TransactionResponse::class);
        $transaction->method('getStatusCode')->willReturn(null);
        $transaction->method('isCanceled')->willReturn(true);

        $resultFactory = $this->createMock(ResultInterfaceFactory::class);
        $result = $this->createMock(ResultInterface::class);
        $resultFactory->expects($this->once())
            ->method('create')
            ->with([
                'isValid' => true,
                'failsDescription' => ['Transaction Success'],
                'errorCodes' => [190],
            ])
            ->willReturn($result);

        $validator = $this->createValidator($resultFactory, $this->createRequestMock('1'));

        $this->assertSame($result, $validator->validate(['response' => ['object' => $transaction]]));
    }

    /**
     * The cancel parameter must not override a status code the gateway did return.
     */
    public function testCancelParamDoesNotOverrideFailedStatus(): void
    {
        $resultFactory = $this->createMock(ResultInterfaceFactory::class);
        $result = $this->createMock(ResultInterface::class);
        $resultFactory->expects($this->once())
            ->method('create')
            ->with([
                'isValid' => false,
                'failsDescription' => ['Insufficient funds'],
                'errorCodes' => [490],
            ])
            ->willReturn($result);

        $validator = $this->createValidator($resultFactory, $this->createRequestMock('1'));

        $this->assertSame($result, $validator->validate([
            'response' => ['object' => $this->createResponse([
                'Status' => ['Code' => ['Code' => 490]],
                'Message' => '  Insufficient funds  ',
            ])],
            'payment' => $this->createPaymentMock(),
        ]));
    }

    public function testUnknownStatusIsInvalid(): void
    {
        $resultFactory = $this->createMock(ResultInterfaceFactory::class);
        $result = $this->createMock(ResultInterface::class);
        $resultFactory->expects($this->once())
            ->method('create')
            ->with([
                'isValid' => false,
                'failsDescription' => ['Invalid Buckaroo status code received: %1.'],
                'errorCodes' => [999],
            ])
            ->willReturn($result);

        $validator = $this->createValidator($resultFactory);

        $this->assertSame($result, $validator->validate([
            'response' => ['object' => $this->createResponse(['Status' => ['Code' => ['Code' => 999]]])],
        ]));
    }

    #[DataProvider('countryCodeRejectionProvider')]
    public function testAfterpayCountryCodeRejectionMessage(int $statusCode, string $error, string $expected): void
    {
        $resultFactory = $this->createMock(ResultInterfaceFactory::class);
        $result = $this->createMock(ResultInterface::class);
        $resultFactory->expects($this->once())
            ->method('create')
            ->with([
                'isValid' => false,
                'failsDescription' => [$expected],
                'errorCodes' => [$statusCode],
            ])
            ->willReturn($result);

        $validator = $this->createValidator($resultFactory);

        $this->assertSame($result, $validator->validate([
            'response' => ['object' => $this->createResponse([
                'Status' => ['Code' => ['Code' => $statusCode]],
                'Message' => $error,
            ])],
            'payment' => $this->createPaymentMock(),
        ]));
    }

    /**
     * @return array<string, array{int, string, string}>
     */
    public static function countryCodeRejectionProvider(): array
    {
        $countryError = 'Invalid value for deliveryCustomer.address.countryCode';

        return [
            'rejected with country code error' => [
                690,
                $countryError,
                'Pay rejected: It is not allowed to specify another country for the invoice and delivery '
                . 'address for Afterpay transactions.',
            ],
            'failed with country code error' => [490, $countryError, $countryError],
            'rejected with other error' => [690, 'Rejected by risk check', 'Rejected by risk check'],
        ];
    }

    public function testFraudSubCodeReplacesMessage(): void
    {
        $resultFactory = $this->createMock(ResultInterfaceFactory::class);
        $result = $this->createMock(ResultInterface::class);
        $fraudMessage = 'An anti-fraud rule has blocked this transaction automatically. Please contact the webshop.';
        $resultFactory->expects($this->once())
            ->method('create')
            ->with([
                'isValid' => false,
                'failsDescription' => [$fraudMessage],
                'errorCodes' => [690],
            ])
            ->willReturn($result);

        $validator = $this->createValidator($resultFactory);

        $this->assertSame($result, $validator->validate([
            'response' => ['object' => $this->createResponse([
                'Status' => ['Code' => ['Code' => 690], 'SubCode' => ['Code' => 'S103']],
                'Message' => 'Rejected',
            ])],
            'payment' => $this->createPaymentMock(),
        ]));
        $this->assertSame($fraudMessage, $validator->getFailureMessageOnFraud());
    }

    public function testDeclineWithoutReasonLogsResponseIdentifiers(): void
    {
        $resultFactory = $this->createMock(ResultInterfaceFactory::class);
        $resultFactory->method('create')->willReturn($this->createMock(ResultInterface::class));
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('critical')
            ->with(
                'Buckaroo gateway declined the transaction without a failure reason. '
                . 'status_code: 490, sub_code: S996, raw_description: "An error occurred: .", '
                . 'transaction_key: ABC123, invoice: 000000042'
            );

        $validator = new ResponseCodeSDKValidator(
            $this->createMock(\Buckaroo\Magento2\Helper\Data::class),
            $this->createMock(Http::class),
            $resultFactory,
            $logger
        );

        $validator->validate([
            'response' => ['object' => $this->createResponse([
                'Key' => 'ABC123',
                'Invoice' => '000000042',
                'Status' => [
                    'Code' => ['Code' => 490],
                    'SubCode' => ['Code' => 'S996', 'Description' => 'An error occurred: .'],
                ],
            ])],
            'payment' => $this->createPaymentMock(),
        ]);
    }

    /**
     * @param array<string, mixed> $data
     * @return TransactionResponse
     */
    private function createResponse(array $data): TransactionResponse
    {
        return new TransactionResponse(null, $data);
    }

    /**
     * @param string|null $cancel
     * @return Http
     */
    private function createRequestMock(?string $cancel): Http
    {
        $request = $this->createMock(Http::class);
        $request->method('getParam')->willReturnMap([['cancel', null, $cancel]]);

        return $request;
    }

    /**
     * @param ResultInterfaceFactory $resultFactory
     * @param Http|null              $request
     * @return ResponseCodeSDKValidator
     */
    private function createValidator(ResultInterfaceFactory $resultFactory, ?Http $request = null): ResponseCodeSDKValidator
    {
        return new ResponseCodeSDKValidator(
            $this->createMock(\Buckaroo\Magento2\Helper\Data::class),
            $request ?? $this->createRequestMock(null),
            $resultFactory,
            $this->createMock(LoggerInterface::class)
        );
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
