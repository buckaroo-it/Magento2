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

namespace Buckaroo\Magento2\Test\Unit\Model\Voucher;

use Buckaroo\Magento2\Model\Giftcard\Request\GiftcardException;
use Buckaroo\Magento2\Model\Voucher\ApplyVoucherRequest;
use Buckaroo\Magento2\Model\Voucher\ApplyVoucherRequestInterface;
use Magento\Payment\Gateway\Command\CommandException;
use Magento\Payment\Gateway\Command\CommandPoolInterface;
use Magento\Payment\Gateway\CommandInterface;
use Magento\Payment\Gateway\Data\PaymentDataObjectFactory;
use Magento\Payment\Gateway\Data\PaymentDataObjectInterface;
use Magento\Quote\Api\Data\CartInterface;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Payment;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class ApplyVoucherRequestTest extends TestCase
{
    /**
     * @var ApplyVoucherRequest
     */
    private $applyVoucherRequest;

    /**
     * @var MockObject|StoreManagerInterface
     */
    private $storeManagerMock;

    /**
     * @var MockObject|CommandPoolInterface
     */
    private $commandPoolMock;

    /**
     * @var MockObject|PaymentDataObjectFactory
     */
    private $paymentDataObjectFactoryMock;

    /**
     * @var MockObject|StoreInterface
     */
    private $storeMock;

    /**
     * @var MockObject|Quote
     */
    private $quoteMock;

    /**
     * @var MockObject|Payment
     */
    private $paymentMock;

    /**
     * @inheritdoc
     */
    protected function setUp(): void
    {
        $this->storeManagerMock = $this->getMockBuilder(StoreManagerInterface::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->commandPoolMock = $this->getMockBuilder(CommandPoolInterface::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->paymentDataObjectFactoryMock = $this->getMockBuilder(PaymentDataObjectFactory::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->storeMock = $this->getMockBuilder(StoreInterface::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->quoteMock = $this->getMockBuilder(Quote::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->paymentMock = $this->getMockBuilder(Payment::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->storeManagerMock->expects($this->once())
            ->method('getStore')
            ->willReturn($this->storeMock);

        $this->applyVoucherRequest = new ApplyVoucherRequest(
            $this->storeManagerMock,
            $this->commandPoolMock,
            $this->paymentDataObjectFactoryMock
        );
    }

    /**
     * Test successful voucher application
     */
    public function testSendSuccess(): void
    {
        $voucherCode = 'TEST123';
        $grandTotal = 100.00;

        // Set up quote
        $this->applyVoucherRequest->setVoucherCode($voucherCode);
        $this->applyVoucherRequest->setQuote($this->quoteMock);

        // Mock quote methods
        $this->quoteMock->expects($this->once())
            ->method('getGrandTotal')
            ->willReturn($grandTotal);

        $this->quoteMock->expects($this->once())
            ->method('getPayment')
            ->willReturn($this->paymentMock);

        // Mock payment methods
        $this->paymentMock->expects($this->once())
            ->method('setAdditionalInformation')
            ->with('voucher_code', $voucherCode);

        // Mock payment data object creation
        $paymentDataObjectMock = $this->getMockBuilder(PaymentDataObjectInterface::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->paymentDataObjectFactoryMock->expects($this->once())
            ->method('create')
            ->with($this->paymentMock)
            ->willReturn($paymentDataObjectMock);

        // Mock command execution
        $commandMock = $this->getMockBuilder(CommandInterface::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->commandPoolMock->expects($this->once())
            ->method('get')
            ->with('voucher_apply')
            ->willReturn($commandMock);

        $commandMock->expects($this->once())
            ->method('execute')
            ->with([
                'payment' => $paymentDataObjectMock,
                'amount' => $grandTotal
            ]);

        $result = $this->applyVoucherRequest->send();

        $this->assertEquals(['status' => 'success'], $result);
    }

    /**
     * Test exception when voucher code is missing
     */
    public function testSendThrowsExceptionWhenVoucherCodeMissing(): void
    {
        $this->applyVoucherRequest->setQuote($this->quoteMock);

        $this->expectException(GiftcardException::class);
        $this->expectExceptionMessage('Field `voucherCode` is required');

        $this->applyVoucherRequest->send();
    }

    /**
     * Test exception when quote is missing
     */
    public function testSendThrowsExceptionWhenQuoteMissing(): void
    {
        $this->applyVoucherRequest->setVoucherCode('TEST123');

        $this->expectException(GiftcardException::class);
        $this->expectExceptionMessage('Quote is required');

        $this->applyVoucherRequest->send();
    }

    /**
     * Test command exception handling
     */
    public function testSendHandlesCommandException(): void
    {
        $voucherCode = 'TEST123';
        $grandTotal = 100.00;
        $commandExceptionMessage = 'Command failed';

        // Set up quote
        $this->applyVoucherRequest->setVoucherCode($voucherCode);
        $this->applyVoucherRequest->setQuote($this->quoteMock);

        $this->quoteMock->expects($this->once())
            ->method('getGrandTotal')
            ->willReturn($grandTotal);

        $this->quoteMock->expects($this->once())
            ->method('getPayment')
            ->willReturn($this->paymentMock);

        $this->paymentMock->expects($this->once())
            ->method('setAdditionalInformation')
            ->with('voucher_code', $voucherCode);

        // Mock payment data object creation
        $paymentDataObjectMock = $this->getMockBuilder(PaymentDataObjectInterface::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->paymentDataObjectFactoryMock->expects($this->once())
            ->method('create')
            ->with($this->paymentMock)
            ->willReturn($paymentDataObjectMock);

        // Mock command execution with exception
        $commandMock = $this->getMockBuilder(CommandInterface::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->commandPoolMock->expects($this->once())
            ->method('get')
            ->with('voucher_apply')
            ->willReturn($commandMock);

        $commandMock->expects($this->once())
            ->method('execute')
            ->willThrowException(new CommandException(__($commandExceptionMessage)));

        $this->expectException(GiftcardException::class);
        $this->expectExceptionMessage($commandExceptionMessage);

        $this->applyVoucherRequest->send();
    }

    /**
     * Test setVoucherCode method
     */
    public function testSetVoucherCode(): void
    {
        $voucherCode = 'TEST456';
        
        $result = $this->applyVoucherRequest->setVoucherCode($voucherCode);
        
        $this->assertInstanceOf(ApplyVoucherRequestInterface::class, $result);
        $this->assertSame($this->applyVoucherRequest, $result);
    }

    /**
     * Test setQuote method
     */
    public function testSetQuote(): void
    {
        $result = $this->applyVoucherRequest->setQuote($this->quoteMock);
        
        $this->assertInstanceOf(ApplyVoucherRequestInterface::class, $result);
        $this->assertSame($this->applyVoucherRequest, $result);
    }
}
