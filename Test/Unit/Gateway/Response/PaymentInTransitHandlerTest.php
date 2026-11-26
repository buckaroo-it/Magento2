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

namespace Buckaroo\Magento2\Test\Unit\Gateway\Response;


use PHPUnit\Framework\Attributes\DataProvider;
use Buckaroo\Magento2\Gateway\Response\PaymentInTransitHandler;

class PaymentInTransitHandlerTest extends AbstractResponseHandlerTest
{
    /**
     * @var PaymentInTransitHandler
     */
    private $paymentInTransitHandler;

    protected function setUp(): void
    {
        parent::setUp();
        $this->paymentInTransitHandler = new PaymentInTransitHandler();
    }

    /**
     * The flag is first set to true; without a redirect it is cleared to false again.
     *
     * @param bool  $hasRedirect
     * @param array $expectedCalls
     */
    #[DataProvider('handleDataProvider')]
    public function testHandle(bool $hasRedirect, array $expectedCalls): void
    {
        $this->transactionResponse
            ->method('hasRedirect')
            ->willReturn($hasRedirect);

        $calls = [];
        $this->orderPaymentMock
            ->expects($this->exactly(count($expectedCalls)))
            ->method('setAdditionalInformation')
            ->willReturnCallback(function ($key, $value) use (&$calls) {
                $calls[] = [$key, $value];
                return $this->orderPaymentMock;
            });

        $this->paymentInTransitHandler->handle(
            ['payment' => $this->getPaymentDOMock()],
            $this->getTransactionResponse()
        );

        $this->assertSame($expectedCalls, $calls);
    }

    public static function handleDataProvider(): array
    {
        return [
            'redirect leaves flag true' => [
                true,
                [['buckaroo_payment_in_transit', true]],
            ],
            'no redirect clears flag to false' => [
                false,
                [['buckaroo_payment_in_transit', true], ['buckaroo_payment_in_transit', false]],
            ],
        ];
    }

    public function testHandleSkipsWhenGroupTransactionRefundIsComplete(): void
    {
        $this->orderPaymentMock->expects($this->never())->method('setAdditionalInformation');

        $this->paymentInTransitHandler->handle(
            ['payment' => $this->getPaymentDOMock()],
            ['object' => $this->transactionResponse, 'group_transaction_refund_complete' => true]
        );
    }
}
