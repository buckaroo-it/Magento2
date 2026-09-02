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

namespace Buckaroo\Magento2\Test\Unit\Gateway\Http\Client;

use Buckaroo\Magento2\Gateway\Http\Client\TransactionIdealPayOrFastCheckout;
use Buckaroo\Magento2\Gateway\Http\Client\TransactionType;
use Buckaroo\Magento2\Model\Adapter\BuckarooAdapter;
use Buckaroo\Magento2\Service\PayReminderService;
use Buckaroo\Magento2\Test\BaseTest;
use Buckaroo\Transaction\Response\TransactionResponse;
use Magento\Payment\Gateway\Http\TransferInterface;
use Magento\Payment\Model\Method\Logger as PaymentLogger;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use Psr\Log\LoggerInterface;

class TransactionIdealPayOrFastCheckoutTest extends BaseTest
{
    protected $instanceClass = TransactionIdealPayOrFastCheckout::class;

    /**
     * @var BuckarooAdapter|MockObject
     */
    private $adapterMock;

    /**
     * @var PayReminderService|MockObject
     */
    private $payReminderServiceMock;

    /**
     * @var TransactionResponse|Stub
     */
    private $transactionResponseMock;

    /**
     * @var TransactionIdealPayOrFastCheckout
     */
    private $client;

    public function setUp(): void
    {
        parent::setUp();

        $this->adapterMock = $this->createMock(BuckarooAdapter::class);
        $this->payReminderServiceMock = $this->createMock(PayReminderService::class);
        $this->transactionResponseMock = $this->createStub(TransactionResponse::class);

        $this->client = new TransactionIdealPayOrFastCheckout(
            $this->createStub(LoggerInterface::class),
            $this->createStub(PaymentLogger::class),
            $this->adapterMock,
            $this->payReminderServiceMock
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

    public function testFastCheckoutIssuerUsesPayFastCheckoutActionWithoutIssuer(): void
    {
        $body = [
            'payment_method' => 'ideal',
            'issuer'         => TransactionIdealPayOrFastCheckout::FAST_CHECKOUT_ISSUER,
            'amountDebit'    => 25.0,
            'invoice'        => '100000010',
        ];

        $this->payReminderServiceMock->expects($this->never())->method('getServiceAction');

        $this->adapterMock->expects($this->once())
            ->method('execute')
            ->with(
                TransactionType::PAY_FAST_CHECKOUT,
                'ideal',
                [
                    'amountDebit' => 25.0,
                    'invoice'     => '100000010',
                ]
            )
            ->willReturn($this->transactionResponseMock);

        $result = $this->client->placeRequest($this->createTransfer($body));

        $this->assertSame(['object' => $this->transactionResponseMock], $result);
    }

    public function testRegularIssuerKeepsIssuerAndUsesPayRemainderFlow(): void
    {
        $body = [
            'payment_method' => 'ideal',
            'issuer'         => 'ABNANL2A',
            'amountDebit'    => 25.0,
            'invoice'        => '100000010',
        ];

        $this->payReminderServiceMock->expects($this->once())
            ->method('getServiceAction')
            ->with('100000010', TransactionType::PAY, TransactionType::PAY_REMAINDER)
            ->willReturn(TransactionType::PAY);

        $this->adapterMock->expects($this->once())
            ->method('execute')
            ->with(
                TransactionType::PAY,
                'ideal',
                [
                    'issuer'      => 'ABNANL2A',
                    'amountDebit' => 25.0,
                    'invoice'     => '100000010',
                ]
            )
            ->willReturn($this->transactionResponseMock);

        $result = $this->client->placeRequest($this->createTransfer($body));

        $this->assertSame(['object' => $this->transactionResponseMock], $result);
    }

    public function testRegularIssuerUsesPayRemainderWhenOrderIsPartiallyPaid(): void
    {
        $body = [
            'payment_method' => 'ideal',
            'issuer'         => 'INGBNL2A',
            'invoice'        => '100000011',
        ];

        $this->payReminderServiceMock->expects($this->once())
            ->method('getServiceAction')
            ->with('100000011', TransactionType::PAY, TransactionType::PAY_REMAINDER)
            ->willReturn(TransactionType::PAY_REMAINDER);

        $this->adapterMock->expects($this->once())
            ->method('execute')
            ->with(
                TransactionType::PAY_REMAINDER,
                'ideal',
                [
                    'issuer'  => 'INGBNL2A',
                    'invoice' => '100000011',
                ]
            )
            ->willReturn($this->transactionResponseMock);

        $this->client->placeRequest($this->createTransfer($body));
    }

    public function testMissingIssuerFallsBackToPayRemainderFlow(): void
    {
        $body = [
            'payment_method' => 'ideal',
            'invoice'        => '100000012',
        ];

        $this->payReminderServiceMock->expects($this->once())
            ->method('getServiceAction')
            ->with('100000012', TransactionType::PAY, TransactionType::PAY_REMAINDER)
            ->willReturn(TransactionType::PAY);

        $this->adapterMock->expects($this->once())
            ->method('execute')
            ->with(TransactionType::PAY, 'ideal', ['invoice' => '100000012'])
            ->willReturn($this->transactionResponseMock);

        $this->client->placeRequest($this->createTransfer($body));
    }
}
