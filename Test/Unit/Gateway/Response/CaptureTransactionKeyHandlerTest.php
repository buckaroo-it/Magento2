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

use Buckaroo\Magento2\Gateway\Response\CaptureTransactionKeyHandler;
use Buckaroo\Magento2\Model\Method\BuckarooAdapter;
use Magento\Payment\Gateway\Data\PaymentDataObjectInterface;

class CaptureTransactionKeyHandlerTest extends AbstractResponseHandlerTest
{
    /**
     * @var CaptureTransactionKeyHandler
     */
    private $captureTransactionKeyHandler;

    protected function setUp(): void
    {
        parent::setUp();

        $this->captureTransactionKeyHandler = new CaptureTransactionKeyHandler();
    }

    /**
     * Payment data object mock without the invocation caps of the shared helper.
     */
    private function getCapturePaymentDOMock()
    {
        $paymentDOMock = $this->createMock(PaymentDataObjectInterface::class);
        $paymentDOMock->method('getPayment')
            ->willReturn($this->orderPaymentMock);

        return $paymentDOMock;
    }

    public function testHandleStoresCaptureTransactionKey(): void
    {
        $transactionKey = 'capture_pay_transaction_key';

        $this->transactionResponse->expects($this->atLeastOnce())
            ->method('getTransactionKey')
            ->willReturn($transactionKey);

        $additionalInformationCalls = [];
        $this->orderPaymentMock
            ->method('setAdditionalInformation')
            ->willReturnCallback(
                function ($key, $value) use (&$additionalInformationCalls) {
                    $additionalInformationCalls[$key] = $value;
                    return $this->orderPaymentMock;
                }
            );

        $this->captureTransactionKeyHandler->handle(
            ['payment' => $this->getCapturePaymentDOMock()],
            $this->getTransactionResponse()
        );

        $this->assertSame(
            $transactionKey,
            $additionalInformationCalls[BuckarooAdapter::BUCKAROO_CAPTURE_TRANSACTION_KEY] ?? null,
            'The Pay transaction key must be stored as the capture transaction key'
        );
        $this->assertTrue(
            $additionalInformationCalls[BuckarooAdapter::BUCKAROO_ALREADY_CAPTURED] ?? null,
            'The payment must be flagged as already captured'
        );
    }

    public function testHandleDoesNotTouchTransactionId(): void
    {
        $this->transactionResponse->method('getTransactionKey')->willReturn('capture_pay_transaction_key');
        $this->orderPaymentMock->method('setAdditionalInformation')->willReturnSelf();

        // The shared capture chain owns transaction id handling; this handler must only add keys.
        $this->orderPaymentMock->expects($this->never())->method('setTransactionId');
        $this->orderPaymentMock->expects($this->never())->method('setIsTransactionClosed');

        $this->captureTransactionKeyHandler->handle(
            ['payment' => $this->getCapturePaymentDOMock()],
            $this->getTransactionResponse()
        );
    }

    public function testHandleWithoutTransactionKeyStoresNothing(): void
    {
        $this->transactionResponse->expects($this->atLeastOnce())
            ->method('getTransactionKey')
            ->willReturn('');

        $this->orderPaymentMock->expects($this->never())->method('setAdditionalInformation');

        $this->captureTransactionKeyHandler->handle(
            ['payment' => $this->getCapturePaymentDOMock()],
            $this->getTransactionResponse()
        );
    }

    public function testHandleSkipsCompletedGroupTransactionRefund(): void
    {
        $this->orderPaymentMock->expects($this->never())->method('setAdditionalInformation');

        $this->captureTransactionKeyHandler->handle(
            ['payment' => $this->getCapturePaymentDOMock()],
            ['group_transaction_refund_complete' => true]
        );
    }
}
