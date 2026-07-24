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

namespace Buckaroo\Magento2\Test\Unit\Gateway\Http\Client;

use Buckaroo\Magento2\Gateway\Http\Client\DefaultTransaction;
use Buckaroo\Magento2\Gateway\Http\Client\TransactionType;
use Buckaroo\Magento2\Model\Adapter\BuckarooAdapter;
use Buckaroo\Magento2\Test\BaseTest;
use Buckaroo\Transaction\Response\TransactionResponse;
use Magento\Payment\Gateway\Http\ClientException;
use Magento\Payment\Gateway\Http\TransferInterface;
use Magento\Payment\Model\Method\Logger as PaymentLogger;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use Psr\Log\LoggerInterface;

#[AllowMockObjectsWithoutExpectations]
class DefaultTransactionTest extends BaseTest
{
    protected $instanceClass = DefaultTransaction::class;

    /**
     * @var LoggerInterface|MockObject
     */
    private $loggerMock;

    /**
     * @var PaymentLogger|MockObject
     */
    private $paymentLoggerMock;

    /**
     * @var BuckarooAdapter|MockObject
     */
    private $adapterMock;

    /**
     * @var TransactionResponse|Stub
     */
    private $transactionResponseMock;

    public function setUp(): void
    {
        parent::setUp();

        $this->loggerMock = $this->createMock(LoggerInterface::class);
        $this->paymentLoggerMock = $this->createMock(PaymentLogger::class);
        $this->adapterMock = $this->createMock(BuckarooAdapter::class);
        $this->transactionResponseMock = $this->createStub(TransactionResponse::class);
    }

    private function createClient(string $action = TransactionType::PAY): DefaultTransaction
    {
        return new DefaultTransaction(
            $this->loggerMock,
            $this->paymentLoggerMock,
            $this->adapterMock,
            $action
        );
    }

    /**
     * @param array $body
     *
     * @return TransferInterface|Stub
     */
    private function createTransfer(array $body)
    {
        $transfer = $this->createStub(TransferInterface::class);
        $transfer->method('getBody')->willReturn($body);

        return $transfer;
    }

    public function testPlaceRequestStripsPaymentMethodAndForwardsBodyToSdk(): void
    {
        $body = [
            'payment_method' => 'ideal',
            'currency'       => 'EUR',
            'amountDebit'    => 10.5,
            'invoice'        => '100000001',
        ];

        $this->adapterMock->expects($this->once())
            ->method('execute')
            ->with(
                TransactionType::PAY,
                'ideal',
                [
                    'currency'    => 'EUR',
                    'amountDebit' => 10.5,
                    'invoice'     => '100000001',
                ]
            )
            ->willReturn($this->transactionResponseMock);

        $result = $this->createClient()->placeRequest($this->createTransfer($body));

        $this->assertSame(['object' => $this->transactionResponseMock], $result);
    }

    public function testPlaceRequestUsesConfiguredActionFromConstructor(): void
    {
        $body = [
            'payment_method' => 'creditcards',
            'amountDebit'    => 99.99,
        ];

        $this->adapterMock->expects($this->once())
            ->method('execute')
            ->with(TransactionType::AUTHORIZE, 'creditcards', ['amountDebit' => 99.99])
            ->willReturn($this->transactionResponseMock);

        $result = $this->createClient(TransactionType::AUTHORIZE)
            ->placeRequest($this->createTransfer($body));

        $this->assertSame(['object' => $this->transactionResponseMock], $result);
    }

    public function testEncryptedCardDataForcesPayEncryptedAction(): void
    {
        $body = [
            'payment_method'    => 'creditcards',
            'encryptedCardData' => 'ENCRYPTED_BLOB',
            'amountDebit'       => 25.0,
        ];

        $this->adapterMock->expects($this->once())
            ->method('execute')
            ->with(
                TransactionType::PAY_ENCRYPTED,
                'creditcards',
                [
                    'encryptedCardData' => 'ENCRYPTED_BLOB',
                    'amountDebit'       => 25.0,
                ]
            )
            ->willReturn($this->transactionResponseMock);

        $result = $this->createClient()->placeRequest($this->createTransfer($body));

        $this->assertSame(['object' => $this->transactionResponseMock], $result);
    }

    public function testPlaceRequestLogsRequestAndResponseViaPaymentLogger(): void
    {
        $body = [
            'payment_method' => 'ideal',
            'currency'       => 'EUR',
        ];

        $this->adapterMock->method('execute')->willReturn($this->transactionResponseMock);

        $this->paymentLoggerMock->expects($this->once())
            ->method('debug')
            ->with($this->callback(function (array $log) use ($body): bool {
                return $log['request'] === $body
                    && $log['client'] === DefaultTransaction::class
                    && array_key_exists('response', $log)
                    && is_array($log['response']);
            }));

        $this->createClient()->placeRequest($this->createTransfer($body));
    }

    public function testRefundBelowMinimumAmountSkipsSdkCall(): void
    {
        $body = [
            'payment_method' => 'giftcards',
            'amountCredit'   => 0.005,
        ];

        $this->adapterMock->expects($this->never())->method('execute');
        $this->paymentLoggerMock->expects($this->never())->method('debug');

        $this->loggerMock->expects($this->once())
            ->method('debug')
            ->with(
                'Skipping refund API call - amount already fully refunded via group transactions (giftcards/vouchers)'
            );

        $result = $this->createClient(TransactionType::REFUND)
            ->placeRequest($this->createTransfer($body));

        $this->assertSame(
            [
                'object'                            => [],
                'group_transaction_refund_complete' => true,
            ],
            $result
        );
    }

    public function testRefundAtMinimumAmountThresholdStillCallsSdk(): void
    {
        $body = [
            'payment_method' => 'giftcards',
            'amountCredit'   => 0.01,
        ];

        $this->adapterMock->expects($this->once())
            ->method('execute')
            ->with(TransactionType::REFUND, 'giftcards', ['amountCredit' => 0.01])
            ->willReturn($this->transactionResponseMock);

        $result = $this->createClient(TransactionType::REFUND)
            ->placeRequest($this->createTransfer($body));

        $this->assertSame(['object' => $this->transactionResponseMock], $result);
    }

    public function testRefundWithoutAmountCreditIsNotSkipped(): void
    {
        $body = ['payment_method' => 'ideal'];

        $this->adapterMock->expects($this->once())
            ->method('execute')
            ->with(TransactionType::REFUND, 'ideal', [])
            ->willReturn($this->transactionResponseMock);

        $result = $this->createClient(TransactionType::REFUND)
            ->placeRequest($this->createTransfer($body));

        $this->assertSame(['object' => $this->transactionResponseMock], $result);
    }

    public function testSdkExceptionIsWrappedInClientExceptionAndLogged(): void
    {
        $body = ['payment_method' => 'ideal', 'currency' => 'EUR'];

        $this->adapterMock->expects($this->once())
            ->method('execute')
            ->willThrowException(new \Exception('SDK failure'));

        $this->loggerMock->expects($this->once())->method('critical');
        // The finally block must still log the request even when the SDK throws.
        $this->paymentLoggerMock->expects($this->once())->method('debug');

        $this->expectException(ClientException::class);
        $this->expectExceptionMessage('SDK failure');

        $this->createClient()->placeRequest($this->createTransfer($body));
    }

    public function testEmptyExceptionMessageFallsBackToGenericMessage(): void
    {
        $body = ['payment_method' => 'ideal'];

        $this->adapterMock->expects($this->once())
            ->method('execute')
            ->willThrowException(new \Exception(''));

        $this->expectException(ClientException::class);
        $this->expectExceptionMessage('Sorry, but something went wrong');

        $this->createClient()->placeRequest($this->createTransfer($body));
    }
}
