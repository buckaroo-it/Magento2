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

namespace Buckaroo\Magento2\Test\Unit\Service\Giftcard;

use Buckaroo\Magento2\Model\Giftcard\Api\ApiException;
use Buckaroo\Magento2\Service\Giftcard\AttemptLimit;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Payment;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class AttemptLimitTest extends TestCase
{
    /**
     * @var CartRepositoryInterface|MockObject
     */
    private $cartRepositoryMock;

    /**
     * @var Payment|MockObject
     */
    private $paymentMock;

    /**
     * @var Quote|MockObject
     */
    private $quoteMock;

    /**
     * @var AttemptLimit
     */
    private AttemptLimit $service;

    protected function setUp(): void
    {
        $this->cartRepositoryMock = $this->createMock(CartRepositoryInterface::class);
        $this->paymentMock = $this->createMock(Payment::class);
        $this->quoteMock = $this->createMock(Quote::class);
        $this->quoteMock->method('getPayment')->willReturn($this->paymentMock);

        $this->service = new AttemptLimit($this->cartRepositoryMock);
    }

    public function testAllowsRequestBelowTheLimit(): void
    {
        // Arrange
        $this->paymentMock->method('getAdditionalInformation')
            ->with('buckaroo_giftcard_failed_attempts')
            ->willReturn(AttemptLimit::MAX_ATTEMPTS - 1);

        // Act + Assert (no exception)
        $this->service->assertWithinLimit($this->quoteMock);
        $this->addToAssertionCount(1);
    }

    public function testRejectsRequestAtTheLimit(): void
    {
        // Arrange
        $this->paymentMock->method('getAdditionalInformation')
            ->with('buckaroo_giftcard_failed_attempts')
            ->willReturn(AttemptLimit::MAX_ATTEMPTS);

        // Assert
        $this->expectException(ApiException::class);

        // Act
        $this->service->assertWithinLimit($this->quoteMock);
    }

    public function testRegisterFailedAttemptIncrementsCounterAndPersistsQuote(): void
    {
        // Arrange
        $this->paymentMock->method('getAdditionalInformation')
            ->with('buckaroo_giftcard_failed_attempts')
            ->willReturn(4);

        // Assert
        $this->paymentMock->expects($this->once())
            ->method('setAdditionalInformation')
            ->with('buckaroo_giftcard_failed_attempts', 5);
        $this->cartRepositoryMock->expects($this->once())
            ->method('save')
            ->with($this->quoteMock);

        // Act
        $this->service->registerFailedAttempt($this->quoteMock);
    }

    public function testTreatsMissingCounterAsZero(): void
    {
        // Arrange
        $this->paymentMock->method('getAdditionalInformation')
            ->with('buckaroo_giftcard_failed_attempts')
            ->willReturn(null);

        // Act + Assert (no exception on a fresh quote)
        $this->service->assertWithinLimit($this->quoteMock);
        $this->addToAssertionCount(1);
    }
}
