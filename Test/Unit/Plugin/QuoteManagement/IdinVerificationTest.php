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
