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

use Buckaroo\Magento2\Gateway\Response\CaptureTransactionIdHandler;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * BTI-1413 — Magento refuses an online capture once the authorization transaction is closed
 * (Payment::canCapture()), so a partial capture must leave it open. Otherwise the next shipment
 * registers an invoice that is never paid and never reaches Buckaroo.
 */
class CaptureTransactionIdHandlerTest extends AbstractResponseHandlerTest
{
    /**
     * @param bool $canInvoice        Whether the order still has lines to invoice.
     * @param bool $expectParentClose
     */
    #[DataProvider('captureProvider')]
    public function testTheAuthorizationIsOnlyClosedOnTheFinalCapture(
        bool $canInvoice,
        bool $expectParentClose
    ): void {
        $this->transactionResponse->method('getTransactionKey')->willReturn('test_transaction_key');

        $this->orderMock->method('canInvoice')->willReturn($canInvoice);
        $this->orderPaymentMock->method('getOrder')->willReturn($this->orderMock);

        $this->orderPaymentMock
            ->expects($this->once())
            ->method('setShouldCloseParentTransaction')
            ->with($expectParentClose);

        // The capture transaction itself is always terminal.
        $this->orderPaymentMock
            ->expects($this->once())
            ->method('setIsTransactionClosed')
            ->with(true);

        (new CaptureTransactionIdHandler())->handle(
            ['payment' => $this->getPaymentDOMock()],
            $this->getTransactionResponse()
        );
    }

    public static function captureProvider(): array
    {
        return [
            'partial capture keeps the authorization open' => [true, false],
            'final capture closes the authorization' => [false, true],
        ];
    }

    /**
     * A payment without an order cannot be reasoned about; fall back to the old behaviour.
     */
    public function testAPaymentWithoutAnOrderClosesTheAuthorization(): void
    {
        $this->transactionResponse->method('getTransactionKey')->willReturn('test_transaction_key');
        $this->orderPaymentMock->method('getOrder')->willReturn(null);

        $this->orderPaymentMock
            ->expects($this->once())
            ->method('setShouldCloseParentTransaction')
            ->with(true);

        (new CaptureTransactionIdHandler())->handle(
            ['payment' => $this->getPaymentDOMock()],
            $this->getTransactionResponse()
        );
    }
}
