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
use PHPUnit\Framework\Attributes\DataProvider;
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

    public function testAllowsTenFailedAttemptsPerQuoteAndRefusesTheEleventh(): void
    {
        // The counter is stored on the quote payment; a fresh quote has none
        $stored = null;
        $this->paymentMock->method('getAdditionalInformation')
            ->with('buckaroo_giftcard_failed_attempts')
            ->willReturnCallback(function () use (&$stored) {
                return $stored;
            });
        $this->paymentMock->method('setAdditionalInformation')
            ->willReturnCallback(function ($key, $value) use (&$stored) {
                $this->assertSame('buckaroo_giftcard_failed_attempts', $key);
                $stored = $value;
                return $this->paymentMock;
            });

        $allowed = 0;
        $refusal = null;
        while ($allowed < 20) {
            try {
                $this->service->assertWithinLimit($this->quoteMock);
            } catch (ApiException $e) {
                $refusal = $e;
                break;
            }
            $allowed++;
            $this->service->registerFailedAttempt($this->quoteMock);
        }

        $this->assertSame(10, $allowed);
        $this->assertSame(10, $stored);
        $this->assertInstanceOf(ApiException::class, $refusal);
        $this->assertSame('Too many giftcard attempts. Please try again later.', $refusal->getMessage());
    }

    /**
     * @return array
     */
    public static function refusedCountProvider(): array
    {
        return [
            'ten failures'                => [10],
            'eleven failures'             => [11],
            'ten failures stored as text' => ['10'],
        ];
    }

    /**
     * @param int|string $failedAttempts
     */
    #[DataProvider('refusedCountProvider')]
    public function testRefusesOnceTenAttemptsFailed($failedAttempts): void
    {
        $this->paymentMock->method('getAdditionalInformation')
            ->with('buckaroo_giftcard_failed_attempts')
            ->willReturn($failedAttempts);

        $this->expectException(ApiException::class);
        $this->expectExceptionMessage('Too many giftcard attempts. Please try again later.');

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
}
