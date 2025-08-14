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

namespace Buckaroo\Magento2\Test\Unit\Model\Giftcard\Request;

use Buckaroo\Magento2\Api\GiftcardRepositoryInterface;
use Buckaroo\Magento2\Model\Giftcard\Request\Giftcard;
use Buckaroo\Magento2\Model\Giftcard\Request\GiftcardException;
use Buckaroo\Magento2\Model\Giftcard\Request\GiftcardInterface;
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

class GiftcardTest extends TestCase
{
    /**
     * @var Giftcard
     */
    private $giftcard;

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
     * @var MockObject|GiftcardRepositoryInterface
     */
    private $giftcardRepositoryMock;

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

        $this->giftcardRepositoryMock = $this->getMockBuilder(GiftcardRepositoryInterface::class)
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

        $this->giftcard = new Giftcard(
            $this->storeManagerMock,
            $this->commandPoolMock,
            $this->paymentDataObjectFactoryMock,
            $this->giftcardRepositoryMock
        );
    }

    /**
     * Test successful giftcard application
     */
    public function testSendSuccess(): void
    {
        $cardId = 'tcs';
        $cardNumber = '123456789';
        $pin = '1234';
        $grandTotal = 100.00;

        // Set up giftcard data
        $this->giftcard->setCardId($cardId);
        $this->giftcard->setCardNumber($cardNumber);
        $this->giftcard->setPin($pin);
        $this->giftcard->setQuote($this->quoteMock);

        // Mock quote methods
        $this->quoteMock->expects($this->once())
            ->method('getGrandTotal')
            ->willReturn($grandTotal);

        $this->quoteMock->expects($this->once())
            ->method('getPayment')
            ->willReturn($this->paymentMock);

        // Mock payment methods
        $this->paymentMock->expects($this->exactly(3))
            ->method('setAdditionalInformation')
            ->withConsecutive(
                ['giftcard_id', $cardId],
                ['giftcard_number', $cardNumber],
                ['giftcard_pin', $pin]
            );

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
            ->with('giftcard_inline')
            ->willReturn($commandMock);

        $commandMock->expects($this->once())
            ->method('execute')
            ->with([
                'payment' => $paymentDataObjectMock,
                'amount' => $grandTotal
            ]);

        $result = $this->giftcard->send();

        $this->assertEquals(['status' => 'success'], $result);
    }

    /**
     * Test exception when card ID is missing
     */
    public function testSendThrowsExceptionWhenCardIdMissing(): void
    {
        $this->giftcard->setCardNumber('123456789');
        $this->giftcard->setPin('1234');
        $this->giftcard->setQuote($this->quoteMock);

        $this->expectException(GiftcardException::class);
        $this->expectExceptionMessage('Giftcard id is required');

        $this->giftcard->send();
    }

    /**
     * Test exception when card number is missing
     */
    public function testSendThrowsExceptionWhenCardNumberMissing(): void
    {
        $this->giftcard->setCardId('tcs');
        $this->giftcard->setPin('1234');
        $this->giftcard->setQuote($this->quoteMock);

        $this->expectException(GiftcardException::class);
        $this->expectExceptionMessage('Giftcard number is required');

        $this->giftcard->send();
    }

    /**
     * Test exception when pin is missing
     */
    public function testSendThrowsExceptionWhenPinMissing(): void
    {
        $this->giftcard->setCardId('tcs');
        $this->giftcard->setCardNumber('123456789');
        $this->giftcard->setQuote($this->quoteMock);

        $this->expectException(GiftcardException::class);
        $this->expectExceptionMessage('Giftcard pin is required');

        $this->giftcard->send();
    }

    /**
     * Test exception when quote is missing
     */
    public function testSendThrowsExceptionWhenQuoteMissing(): void
    {
        $this->giftcard->setCardId('tcs');
        $this->giftcard->setCardNumber('123456789');
        $this->giftcard->setPin('1234');

        $this->expectException(GiftcardException::class);
        $this->expectExceptionMessage('Quote is required');

        $this->giftcard->send();
    }

    /**
     * Test command exception handling
     */
    public function testSendHandlesCommandException(): void
    {
        $cardId = 'tcs';
        $cardNumber = '123456789';
        $pin = '1234';
        $grandTotal = 100.00;
        $commandExceptionMessage = 'Command failed';

        // Set up giftcard data
        $this->giftcard->setCardId($cardId);
        $this->giftcard->setCardNumber($cardNumber);
        $this->giftcard->setPin($pin);
        $this->giftcard->setQuote($this->quoteMock);

        $this->quoteMock->expects($this->once())
            ->method('getGrandTotal')
            ->willReturn($grandTotal);

        $this->quoteMock->expects($this->once())
            ->method('getPayment')
            ->willReturn($this->paymentMock);

        $this->paymentMock->expects($this->exactly(3))
            ->method('setAdditionalInformation');

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
            ->with('giftcard_inline')
            ->willReturn($commandMock);

        $commandMock->expects($this->once())
            ->method('execute')
            ->willThrowException(new CommandException(__($commandExceptionMessage)));

        $this->expectException(GiftcardException::class);
        $this->expectExceptionMessage($commandExceptionMessage);

        $this->giftcard->send();
    }

    /**
     * Test setCardNumber method
     */
    public function testSetCardNumber(): void
    {
        $cardNumber = '123456789';
        
        $result = $this->giftcard->setCardNumber($cardNumber);
        
        $this->assertInstanceOf(GiftcardInterface::class, $result);
        $this->assertSame($this->giftcard, $result);
    }

    /**
     * Test setPin method
     */
    public function testSetPin(): void
    {
        $pin = '1234';
        
        $result = $this->giftcard->setPin($pin);
        
        $this->assertInstanceOf(GiftcardInterface::class, $result);
        $this->assertSame($this->giftcard, $result);
    }

    /**
     * Test setCardId method
     */
    public function testSetCardId(): void
    {
        $cardId = 'tcs';
        
        $result = $this->giftcard->setCardId($cardId);
        
        $this->assertInstanceOf(GiftcardInterface::class, $result);
        $this->assertSame($this->giftcard, $result);
    }

    /**
     * Test setQuote method
     */
    public function testSetQuote(): void
    {
        $result = $this->giftcard->setQuote($this->quoteMock);
        
        $this->assertInstanceOf(GiftcardInterface::class, $result);
        $this->assertSame($this->giftcard, $result);
    }
}
