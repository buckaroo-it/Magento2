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

namespace Buckaroo\Magento2\Test\Unit\Gateway\Skip;

use Buckaroo\Magento2\Gateway\Response\TransactionIdHandler;
use Buckaroo\Magento2\Gateway\Skip\CancelVoidSkip;
use Buckaroo\Magento2\Logging\BuckarooLoggerInterface;
use Magento\Payment\Gateway\Data\PaymentDataObjectInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Payment;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Test for CancelVoidSkip
 */
class CancelVoidSkipTest extends TestCase
{
    /**
     * @var CancelVoidSkip
     */
    private $cancelVoidSkip;

    /**
     * @var BuckarooLoggerInterface|MockObject
     */
    private $loggerMock;

    /**
     * Set up test
     */
    protected function setUp(): void
    {
        $this->loggerMock = $this->createMock(BuckarooLoggerInterface::class);
        $this->cancelVoidSkip = new CancelVoidSkip($this->loggerMock);
    }

    /**
     * Test skip when no transaction key
     */
    public function testSkipWhenNoTransactionKey()
    {
        $paymentMock = $this->createMock(Payment::class);
        $paymentMock->method('getMethod')->willReturn('buckaroo_magento2_afterpay20');
        $paymentMock->method('getAdditionalInformation')->willReturn(null); // No transaction key
        $paymentMock->method('getOrder')->willReturn(null);

        $paymentDOMock = $this->createMock(PaymentDataObjectInterface::class);
        $paymentDOMock->method('getPayment')->willReturn($paymentMock);

        $commandSubject = ['payment' => $paymentDOMock];

        $this->loggerMock->expects($this->once())
            ->method('addDebug')
            ->with($this->stringContains('No transaction key found'));

        $result = $this->cancelVoidSkip->isSkip($commandSubject);

        $this->assertTrue($result, 'Cancel/void should be skipped when no transaction key');
    }

    /**
     * Test skip when order is in NEW state (payment failed)
     */
    public function testSkipWhenOrderInNewState()
    {
        $orderMock = $this->createMock(Order::class);
        $orderMock->method('getState')->willReturn(Order::STATE_NEW);
        $orderMock->method('getIncrementId')->willReturn('000000569');

        $paymentMock = $this->createMock(Payment::class);
        $paymentMock->method('getMethod')->willReturn('buckaroo_magento2_afterpay20');
        $paymentMock->method('getAdditionalInformation')
            ->willReturnCallback(function ($key) {
                if ($key === TransactionIdHandler::BUCKAROO_ORIGINAL_TRANSACTION_KEY_KEY) {
                    return 'ABC123'; // Transaction key exists
                }
                return null;
            });
        $paymentMock->method('getOrder')->willReturn($orderMock);

        $paymentDOMock = $this->createMock(PaymentDataObjectInterface::class);
        $paymentDOMock->method('getPayment')->willReturn($paymentMock);

        $commandSubject = ['payment' => $paymentDOMock];

        $this->loggerMock->expects($this->once())
            ->method('addDebug')
            ->with($this->stringContains('Order in NEW state'));

        $result = $this->cancelVoidSkip->isSkip($commandSubject);

        $this->assertTrue($result, 'Cancel/void should be skipped when order in NEW state (payment failed)');
    }


    /**
     * Test do not skip when order is in PROCESSING state (payment succeeded)
     */
    public function testDoNotSkipWhenOrderInProcessingState()
    {
        $orderMock = $this->createMock(Order::class);
        $orderMock->method('getState')->willReturn(Order::STATE_PROCESSING);
        $orderMock->method('getIncrementId')->willReturn('000000569');

        $paymentMock = $this->createMock(Payment::class);
        $paymentMock->method('getMethod')->willReturn('buckaroo_magento2_afterpay20');
        $paymentMock->method('getAdditionalInformation')
            ->willReturnCallback(function ($key) {
                if ($key === TransactionIdHandler::BUCKAROO_ORIGINAL_TRANSACTION_KEY_KEY) {
                    return 'ABC123DEF456';
                }
                return null;
            });
        $paymentMock->method('getOrder')->willReturn($orderMock);

        $paymentDOMock = $this->createMock(PaymentDataObjectInterface::class);
        $paymentDOMock->method('getPayment')->willReturn($paymentMock);

        $commandSubject = ['payment' => $paymentDOMock];

        $this->loggerMock->expects($this->never())
            ->method('addDebug');

        $result = $this->cancelVoidSkip->isSkip($commandSubject);

        $this->assertFalse($result, 'Cancel/void should proceed when order in PROCESSING state');
    }
}
