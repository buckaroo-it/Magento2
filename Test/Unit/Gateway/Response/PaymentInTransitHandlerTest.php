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
     *
     * @param bool $hasRedirect
     * @param bool $inTransit
     */
    #[DataProvider('handleDataProvider')]
    public function testHandle(bool $hasRedirect, bool $inTransit)
    {
        $this->transactionResponse
            ->method('hasRedirect')
            ->willReturn($hasRedirect);

        if (!$hasRedirect) {
            $this->orderPaymentMock
                ->expects($this->atMost(2))
                ->method('setAdditionalInformation')
                ->with(PaymentInTransitHandler::BUCKAROO_PAYMENT_IN_TRANSIT, $this->logicalOr($inTransit, false));
        } else {
            $this->orderPaymentMock
                ->method('setAdditionalInformation')
                ->with(PaymentInTransitHandler::BUCKAROO_PAYMENT_IN_TRANSIT, $inTransit);
        }

        $this->paymentInTransitHandler->handle(
            ['payment' => $this->getPaymentDOMock()],
            $this->getTransactionResponse()
        );
    }

    public static function handleDataProvider(): array
    {
        return [
            'Has Redirect' => [true, true],
            'No Redirect' => [false, true],
        ];
    }
}
