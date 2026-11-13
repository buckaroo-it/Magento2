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

namespace Buckaroo\Magento2\Test\Unit\Model\Idin;

use Buckaroo\Magento2\Logging\BuckarooLoggerInterface;
use Buckaroo\Magento2\Model\ConfigProvider\Idin as IdinConfigProvider;
use Buckaroo\Magento2\Model\Idin\CheckoutSupport;
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

    /** @var BuckarooLoggerInterface|MockObject */
    private $logger;

    /** @var CheckoutSupport|MockObject */
    private $checkoutSupport;

    public function setUp(): void
    {
        parent::setUp();

        $this->idinConfigProvider = $this->createMock(IdinConfigProvider::class);
        $this->appState = $this->createMock(State::class);
        $this->logger = $this->createMock(BuckarooLoggerInterface::class);
        $this->checkoutSupport = $this->createMock(CheckoutSupport::class);
    }

    private function getGuard(): VerificationGuard
    {
        return new VerificationGuard(
            $this->idinConfigProvider,
            $this->appState,
            $this->logger,
            $this->checkoutSupport
        );
    }

    private function getQuote(int $storeId = 1): Quote
    {
        $quote = $this->getMockBuilder(Quote::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getId', 'getStoreId'])
            ->getMock();
        $quote->method('getStoreId')->willReturn($storeId);

        return $quote;
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

    public function testRefusesUnverifiedQuoteWhenCheckoutHasIdinStep(): void
    {
        $this->appState->method('getAreaCode')->willReturn(Area::AREA_FRONTEND);
        $this->idinConfigProvider->method('getQuoteIdinStatus')->willReturn(['active' => true, 'verified' => false]);
        $this->checkoutSupport->method('getCheckoutWithoutIdinStep')->willReturn(null);
        $this->logger->expects($this->never())->method('addWarning');

        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('Please verify your age with iDIN before placing this order.');

        $this->getGuard()->assertVerified($this->getQuote());
    }

    public static function checkoutWithoutIdinStepProvider(): array
    {
        return [
            'hyva checkout'       => [CheckoutSupport::HYVA_CHECKOUT, 'Update the Buckaroo Hyvä Checkout module to 2.4.0 or higher'],
            'hyva react checkout' => [CheckoutSupport::HYVA_REACT_CHECKOUT, 'Use a checkout with an iDIN step'],
        ];
    }

    #[DataProvider('checkoutWithoutIdinStepProvider')]
    public function testAllowsUnverifiedQuoteWhenCheckoutHasNoIdinStep(string $checkout, string $advice): void
    {
        $this->appState->method('getAreaCode')->willReturn(Area::AREA_FRONTEND);
        $this->idinConfigProvider->method('getQuoteIdinStatus')->willReturn(['active' => true, 'verified' => false]);
        $this->checkoutSupport->method('getCheckoutWithoutIdinStep')->willReturn($checkout);
        $this->logger->expects($this->once())
            ->method('addWarning')
            ->with($this->logicalAnd($this->stringContains($checkout), $this->stringContains($advice)));

        $this->getGuard()->assertVerified($this->getQuote());
    }

    public function testChecksTheCheckoutOfTheQuoteStore(): void
    {
        $this->appState->method('getAreaCode')->willReturn(Area::AREA_FRONTEND);
        $this->idinConfigProvider->method('getQuoteIdinStatus')->willReturn(['active' => true, 'verified' => false]);
        $this->checkoutSupport->expects($this->once())
            ->method('getCheckoutWithoutIdinStep')
            ->with(3)
            ->willReturn(CheckoutSupport::HYVA_CHECKOUT);

        $this->getGuard()->assertVerified($this->getQuote(3));
    }

    public function testDoesNotCheckTheCheckoutWhenNoVerificationIsNeeded(): void
    {
        $this->appState->method('getAreaCode')->willReturn(Area::AREA_FRONTEND);
        $this->idinConfigProvider->method('getQuoteIdinStatus')->willReturn(['active' => false, 'verified' => false]);
        $this->checkoutSupport->expects($this->never())->method('getCheckoutWithoutIdinStep');
        $this->logger->expects($this->never())->method('addWarning');

        $this->getGuard()->assertVerified($this->getQuote());
    }

    public function testAllowsWhenStatusCannotBeDeterminedAndCheckoutHasNoIdinStep(): void
    {
        $this->appState->method('getAreaCode')->willReturn(Area::AREA_FRONTEND);
        $this->idinConfigProvider->method('getQuoteIdinStatus')
            ->willThrowException(new NoSuchEntityException(__('Product not found')));
        $this->checkoutSupport->method('getCheckoutWithoutIdinStep')->willReturn(CheckoutSupport::HYVA_CHECKOUT);

        $this->getGuard()->assertVerified($this->getQuote());
        $this->addToAssertionCount(1);
    }

    public function testIgnoresNonQuoteCarts(): void
    {
        $this->appState->method('getAreaCode')->willReturn(Area::AREA_FRONTEND);
        $this->idinConfigProvider->expects($this->never())->method('getQuoteIdinStatus');

        $this->getGuard()->assertVerified($this->createMock(CartInterface::class));
    }
}
