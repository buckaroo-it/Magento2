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

use Buckaroo\Magento2\Gateway\Http\Client\TransactionPayRemainder;
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

class TransactionPayRemainderTest extends BaseTest
{
    protected $instanceClass = TransactionPayRemainder::class;

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

    public function setUp(): void
    {
        parent::setUp();

        $this->adapterMock = $this->createMock(BuckarooAdapter::class);
        $this->payReminderServiceMock = $this->createMock(PayReminderService::class);
        $this->transactionResponseMock = $this->createStub(TransactionResponse::class);
    }

    private function createClient(
        string $serviceAction = TransactionType::PAY,
        string $newServiceAction = TransactionType::PAY_REMAINDER
    ): TransactionPayRemainder {
        return new TransactionPayRemainder(
            $this->createStub(LoggerInterface::class),
            $this->createStub(PaymentLogger::class),
            $this->adapterMock,
            $this->payReminderServiceMock,
            $serviceAction,
            $newServiceAction
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

    public function testProcessResolvesServiceActionFromInvoiceIncrementId(): void
    {
        $body = [
            'payment_method' => 'ideal',
            'invoice'        => '100000123',
            'order'          => '100000999',
            'amountDebit'    => 15.0,
        ];

        $this->payReminderServiceMock->expects($this->once())
            ->method('getServiceAction')
            ->with('100000123', TransactionType::PAY, TransactionType::PAY_REMAINDER)
            ->willReturn(TransactionType::PAY_REMAINDER);

        $this->adapterMock->expects($this->once())
            ->method('execute')
            ->with(
                TransactionType::PAY_REMAINDER,
                'ideal',
                [
                    'invoice'     => '100000123',
                    'order'       => '100000999',
                    'amountDebit' => 15.0,
                ]
            )
            ->willReturn($this->transactionResponseMock);

        $result = $this->createClient()->placeRequest($this->createTransfer($body));

        $this->assertSame(['object' => $this->transactionResponseMock], $result);
    }

    public function testProcessFallsBackToOrderIncrementIdWhenInvoiceIsMissing(): void
    {
        $body = [
            'payment_method' => 'ideal',
            'order'          => '100000456',
        ];

        $this->payReminderServiceMock->expects($this->once())
            ->method('getServiceAction')
            ->with('100000456', TransactionType::PAY, TransactionType::PAY_REMAINDER)
            ->willReturn(TransactionType::PAY);

        $this->adapterMock->expects($this->once())
            ->method('execute')
            ->with(TransactionType::PAY, 'ideal', ['order' => '100000456'])
            ->willReturn($this->transactionResponseMock);

        $this->createClient()->placeRequest($this->createTransfer($body));
    }

    public function testProcessPassesEmptyIncrementIdWhenNoOrderReferenceExists(): void
    {
        $body = ['payment_method' => 'ideal', 'amountDebit' => 5.0];

        $this->payReminderServiceMock->expects($this->once())
            ->method('getServiceAction')
            ->with('', TransactionType::PAY, TransactionType::PAY_REMAINDER)
            ->willReturn(TransactionType::PAY);

        $this->adapterMock->expects($this->once())
            ->method('execute')
            ->with(TransactionType::PAY, 'ideal', ['amountDebit' => 5.0])
            ->willReturn($this->transactionResponseMock);

        $this->createClient()->placeRequest($this->createTransfer($body));
    }

    public function testCustomConstructorActionsAreForwardedToPayReminderService(): void
    {
        $body = [
            'payment_method' => 'creditcards',
            'invoice'        => '100000001',
        ];

        $this->payReminderServiceMock->expects($this->once())
            ->method('getServiceAction')
            ->with(
                '100000001',
                TransactionType::AUTHORIZE,
                TransactionType::PAY_REMAINDER_ENCRYPTED
            )
            ->willReturn(TransactionType::AUTHORIZE);

        $this->adapterMock->expects($this->once())
            ->method('execute')
            ->with(TransactionType::AUTHORIZE, 'creditcards', ['invoice' => '100000001'])
            ->willReturn($this->transactionResponseMock);

        $client = $this->createClient(
            TransactionType::AUTHORIZE,
            TransactionType::PAY_REMAINDER_ENCRYPTED
        );

        $client->placeRequest($this->createTransfer($body));
    }

    public function testSetServiceActionDelegatesToPayReminderService(): void
    {
        $this->adapterMock->expects($this->never())->method('execute');

        $this->payReminderServiceMock->expects($this->once())
            ->method('getServiceAction')
            ->with('100000777', TransactionType::PAY, TransactionType::PAY_REMAINDER)
            ->willReturn(TransactionType::PAY_REMAINDER);

        $this->assertSame(
            TransactionType::PAY_REMAINDER,
            $this->createClient()->setServiceAction('100000777')
        );
    }
}
