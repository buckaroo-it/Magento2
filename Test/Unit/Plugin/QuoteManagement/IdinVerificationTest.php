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

namespace Buckaroo\Magento2\Test\Unit\Plugin\QuoteManagement;

use Buckaroo\Magento2\Model\Idin\VerificationGuard;
use Buckaroo\Magento2\Plugin\QuoteManagement\IdinVerification;
use Buckaroo\Magento2\Test\BaseTest;
use Magento\Framework\Exception\LocalizedException;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\QuoteManagement;

class IdinVerificationTest extends BaseTest
{
    public function testSubmitIsStoppedWhenVerificationIsMissing(): void
    {
        $quote = $this->createMock(Quote::class);
        $guard = $this->createMock(VerificationGuard::class);
        $guard->expects($this->once())->method('assertVerified')->with($quote)
            ->willThrowException(new LocalizedException(__('Please verify your age with iDIN before placing this order.')));

        $this->expectException(LocalizedException::class);

        (new IdinVerification($guard))->beforeSubmit($this->createMock(QuoteManagement::class), $quote);
    }

    public function testSubmitArgumentsAreKept(): void
    {
        $quote = $this->createMock(Quote::class);
        $guard = $this->createMock(VerificationGuard::class);
        $guard->expects($this->once())->method('assertVerified')->with($quote);

        $this->assertNull(
            (new IdinVerification($guard))->beforeSubmit($this->createMock(QuoteManagement::class), $quote, ['x' => 1])
        );
    }
}
