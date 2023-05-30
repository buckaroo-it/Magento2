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

namespace Buckaroo\Magento2\Test\Unit\Gateway\Response;

use Buckaroo\Magento2\Gateway\Response\PaymentInTransitHandler;

class PaymentInTransitHandlerTest extends AbstractResponseHandlerTest
{
    /**
     * @var PaymentInTransitHandler
     */
    private PaymentInTransitHandler $paymentInTransitHandler;

    protected function setUp(): void
    {
        parent::setUp();
        $this->paymentInTransitHandler = new PaymentInTransitHandler();
    }

    /**
     * @dataProvider handleDataProvider
     */
    public function testHandle(bool $hasRedirect, bool $inTransit)
    {
        $this->transactionResponse
            ->expects($this->once())
            ->method('hasRedirect')
            ->willReturn($hasRedirect);

        if (!$hasRedirect) {
            $this->orderPaymentMock
                ->expects($this->atMost(2))
                ->method('setAdditionalInformation')
                ->with(PaymentInTransitHandler::BUCKAROO_PAYMENT_IN_TRANSIT, $this->logicalOr($inTransit, false));
        } else {
            $this->orderPaymentMock
                ->expects($this->once())
                ->method('setAdditionalInformation')
                ->with(PaymentInTransitHandler::BUCKAROO_PAYMENT_IN_TRANSIT, $inTransit);
        }

        $this->paymentInTransitHandler->handle(
            ['payment' => $this->getPaymentDOMock()],
            $this->getTransactionResponse()
        );
    }

    public function handleDataProvider(): array
    {
        return [
            'Has Redirect' => [true, true],
            'No Redirect' => [false, true],
        ];
    }
}
