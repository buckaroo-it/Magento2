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

use Buckaroo\Magento2\Gateway\Http\Client\TransactionPayOrInInstallments;
use Buckaroo\Magento2\Gateway\Http\Client\TransactionType;
use Buckaroo\Magento2\Model\Adapter\BuckarooAdapter;
use Buckaroo\Magento2\Test\BaseTest;
use Buckaroo\Transaction\Response\TransactionResponse;
use Magento\Payment\Gateway\Http\TransferInterface;
use Magento\Payment\Model\Method\Logger as PaymentLogger;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use Psr\Log\LoggerInterface;

class TransactionPayOrInInstallmentsTest extends BaseTest
{
    protected $instanceClass = TransactionPayOrInInstallments::class;

    /**
     * @var BuckarooAdapter|MockObject
     */
    private $adapterMock;

    /**
     * @var TransactionResponse|Stub
     */
    private $transactionResponseMock;

    /**
     * @var TransactionPayOrInInstallments
     */
    private $client;

    public function setUp(): void
    {
        parent::setUp();

        $this->adapterMock = $this->createMock(BuckarooAdapter::class);
        $this->transactionResponseMock = $this->createStub(TransactionResponse::class);

        $this->client = new TransactionPayOrInInstallments(
            $this->createStub(LoggerInterface::class),
            $this->createStub(PaymentLogger::class),
            $this->adapterMock
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

    public static function serviceActionProvider(): array
    {
        return [
            'pay action from magento uses pay' => [
                TransactionType::PAY,
                TransactionType::PAY,
            ],
            'payInInstallments action keeps installments' => [
                TransactionType::PAY_IN_INSTALLMENTS,
                TransactionType::PAY_IN_INSTALLMENTS,
            ],
            'any other action falls back to installments' => [
                'authorize',
                TransactionType::PAY_IN_INSTALLMENTS,
            ],
            'empty action falls back to installments' => [
                '',
                TransactionType::PAY_IN_INSTALLMENTS,
            ],
        ];
    }

    #[DataProvider('serviceActionProvider')]
    public function testActionIsSelectedFromServiceActionFromMagento(
        string $serviceActionFromMagento,
        string $expectedSdkAction
    ): void {
        $body = [
            'payment_method'       => 'in3',
            'amountDebit'          => 150.0,
            'additionalParameters' => [
                'service_action_from_magento' => $serviceActionFromMagento,
            ],
        ];

        $this->adapterMock->expects($this->once())
            ->method('execute')
            ->with(
                $expectedSdkAction,
                'in3',
                [
                    'amountDebit'          => 150.0,
                    'additionalParameters' => [
                        'service_action_from_magento' => $serviceActionFromMagento,
                    ],
                ]
            )
            ->willReturn($this->transactionResponseMock);

        $result = $this->client->placeRequest($this->createTransfer($body));

        $this->assertSame(['object' => $this->transactionResponseMock], $result);
    }

    public function testAdditionalParametersAreForwardedUnchangedToSdk(): void
    {
        $body = [
            'payment_method'       => 'capayable',
            'invoice'              => '100000042',
            'additionalParameters' => [
                'service_action_from_magento' => TransactionType::PAY_IN_INSTALLMENTS,
                'customParameter'             => 'customValue',
            ],
        ];

        $this->adapterMock->expects($this->once())
            ->method('execute')
            ->with(
                TransactionType::PAY_IN_INSTALLMENTS,
                'capayable',
                [
                    'invoice'              => '100000042',
                    'additionalParameters' => [
                        'service_action_from_magento' => TransactionType::PAY_IN_INSTALLMENTS,
                        'customParameter'             => 'customValue',
                    ],
                ]
            )
            ->willReturn($this->transactionResponseMock);

        $this->client->placeRequest($this->createTransfer($body));
    }
}
