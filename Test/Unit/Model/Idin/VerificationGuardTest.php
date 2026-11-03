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

namespace Buckaroo\Magento2\Test\Unit\Model\Idin;

use Buckaroo\Magento2\Logging\BuckarooLoggerInterface;
use Buckaroo\Magento2\Model\ConfigProvider\Idin as IdinConfigProvider;
use Buckaroo\Magento2\Model\Idin\VerificationGuard;
use Buckaroo\Magento2\Test\BaseTest;
use Magento\Framework\App\Area;
use Magento\Framework\App\State;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Quote\Api\Data\CartInterface;
use Magento\Quote\Model\Quote;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;

class VerificationGuardTest extends BaseTest
{
    /** @var IdinConfigProvider|MockObject */
    private $idinConfigProvider;

    /** @var State|MockObject */
    private $appState;

    public function setUp(): void
    {
        parent::setUp();

        $this->idinConfigProvider = $this->createMock(IdinConfigProvider::class);
        $this->appState = $this->createMock(State::class);
    }

    private function getGuard(): VerificationGuard
    {
        return new VerificationGuard(
            $this->idinConfigProvider,
            $this->appState,
            $this->createMock(BuckarooLoggerInterface::class)
        );
    }

    private function getQuote(): Quote
    {
        return $this->getMockBuilder(Quote::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getId'])
            ->getMock();
    }

    public static function shopperAreaProvider(): array
    {
        return [
            'storefront' => [Area::AREA_FRONTEND],
            'rest'       => [Area::AREA_WEBAPI_REST],
            'graphql'    => [Area::AREA_GRAPHQL],
        ];
    }

    #[DataProvider('shopperAreaProvider')]
    public function testRefusesUnverifiedQuote(string $area): void
    {
        $this->appState->method('getAreaCode')->willReturn($area);
        $this->idinConfigProvider->method('getQuoteIdinStatus')->willReturn(['active' => true, 'verified' => false]);

        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('Please verify your age with iDIN before placing this order.');

        $this->getGuard()->assertVerified($this->getQuote());
    }

    public function testAllowsQuoteThatNeedsNoVerification(): void
    {
        $this->appState->method('getAreaCode')->willReturn(Area::AREA_FRONTEND);
        $this->idinConfigProvider->method('getQuoteIdinStatus')->willReturn(['active' => false, 'verified' => true]);

        $this->getGuard()->assertVerified($this->getQuote());
        $this->addToAssertionCount(1);
    }

    public function testSkipsQuoteMarkedAsAlreadyChecked(): void
    {
        $this->appState->method('getAreaCode')->willReturn(Area::AREA_FRONTEND);
        $this->idinConfigProvider->expects($this->never())->method('getQuoteIdinStatus');

        $quote = $this->getQuote();
        $quote->setData(VerificationGuard::FLAG_ALREADY_CHECKED, true);

        $this->getGuard()->assertVerified($quote);
    }

    public function testSkipsAdminOrders(): void
    {
        $this->appState->method('getAreaCode')->willReturn(Area::AREA_ADMINHTML);
        $this->idinConfigProvider->expects($this->never())->method('getQuoteIdinStatus');

        $this->getGuard()->assertVerified($this->getQuote());
    }

    public function testSkipsWhenNoAreaIsSet(): void
    {
        $this->appState->method('getAreaCode')->willThrowException(new LocalizedException(__('Area code is not set')));
        $this->idinConfigProvider->expects($this->never())->method('getQuoteIdinStatus');

        $this->getGuard()->assertVerified($this->getQuote());
    }

    public function testRefusesWhenStatusCannotBeDetermined(): void
    {
        $this->appState->method('getAreaCode')->willReturn(Area::AREA_FRONTEND);
        $this->idinConfigProvider->method('getQuoteIdinStatus')
            ->willThrowException(new NoSuchEntityException(__('Product not found')));

        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('We could not check the age verification for your order. Please try again.');

        $this->getGuard()->assertVerified($this->getQuote());
    }

    public function testIgnoresNonQuoteCarts(): void
    {
        $this->appState->method('getAreaCode')->willReturn(Area::AREA_FRONTEND);
        $this->idinConfigProvider->expects($this->never())->method('getQuoteIdinStatus');

        $this->getGuard()->assertVerified($this->createMock(CartInterface::class));
    }
}
