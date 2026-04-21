<?php

/**
 * NOTICE OF LICENSE
 *
 * This source file is subject to the MIT License
 * It is available through the world-wide-web at this URL:
 * https://tldrlegal.com/license/mit-license
 * If you are unable to obtain it through the world-wide-web, please send an email
 * to support@buckaroo.nl so we can send you a copy immediately.
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

namespace Buckaroo\Magento2\Test\Unit\Block\Catalog\Product\View;

use Buckaroo\Magento2\Block\Catalog\Product\View\PaypalExpress;
use Buckaroo\Magento2\Model\ConfigProvider\Account;
use Buckaroo\Magento2\Model\ConfigProvider\Method\Paypal;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\Encryption\Encryptor;
use Magento\Framework\View\Element\Template\Context;
use Magento\Quote\Model\Quote;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Regression coverage for BTI-860: the cart-page config must seed `amount`
 * and `currency` from the active quote so the PayPal button can never mount
 * with initialAmount=null.
 */
class PaypalExpressTest extends TestCase
{
    /** @var Context&MockObject */
    private $context;

    /** @var Account&MockObject */
    private $configProviderAccount;

    /** @var Encryptor&MockObject */
    private $encryptor;

    /** @var Paypal&MockObject */
    private $paypalConfig;

    /** @var StoreManagerInterface&MockObject */
    private $storeManager;

    /** @var Store&MockObject */
    private $store;

    protected function setUp(): void
    {
        $this->context = $this->createMock(Context::class);
        $this->configProviderAccount = $this->createMock(Account::class);
        $this->encryptor = $this->createMock(Encryptor::class);
        $this->paypalConfig = $this->createMock(Paypal::class);
        $this->storeManager = $this->createMock(StoreManagerInterface::class);
        $this->store = $this->getMockBuilder(Store::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getCurrentCurrency'])
            ->getMock();

        $this->context->method('getStoreManager')->willReturn($this->storeManager);
        $this->storeManager->method('getStore')->willReturn($this->store);

        $currency = $this->getMockBuilder(\Magento\Directory\Model\Currency::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getCode'])
            ->getMock();
        $currency->method('getCode')->willReturn('EUR');
        $this->store->method('getCurrentCurrency')->willReturn($currency);

        $this->encryptor->method('decrypt')->willReturn('test-key');
        $this->configProviderAccount->method('getMerchantKey')->willReturn('encrypted');
        $this->paypalConfig->method('getExpressMerchantId')->willReturn('merchant-1');
        $this->paypalConfig->method('getButtonColor')->willReturn('gold');
        $this->paypalConfig->method('getButtonShape')->willReturn('rect');
        $this->paypalConfig->method('getActive')->willReturn(0);
    }

    public function testCartConfigSeedsAmountAndCurrencyFromQuote(): void
    {
        $quote = $this->makeQuoteMock(42, ['grand_total' => 29.985, 'quote_currency_code' => 'USD']);
        $checkoutSession = $this->createMock(CheckoutSession::class);
        $checkoutSession->method('getQuote')->willReturn($quote);

        $block = $this->buildBlock($checkoutSession);

        $config = $block->getCartConfig();

        $this->assertSame('29.99', $config['amount']);
        $this->assertSame('USD', $config['currency']);
    }

    public function testCartConfigOmitsAmountWhenQuoteIsEmpty(): void
    {
        $quote = $this->makeQuoteMock(null, ['grand_total' => 0.0]);
        $checkoutSession = $this->createMock(CheckoutSession::class);
        $checkoutSession->method('getQuote')->willReturn($quote);

        $block = $this->buildBlock($checkoutSession);

        $config = $block->getCartConfig();

        $this->assertArrayNotHasKey('amount', $config);
    }

    public function testCartConfigOmitsAmountWhenQuoteLookupThrows(): void
    {
        $checkoutSession = $this->createMock(CheckoutSession::class);
        $checkoutSession->method('getQuote')
            ->willThrowException(new \RuntimeException('no active quote'));

        $block = $this->buildBlock($checkoutSession);

        $config = $block->getCartConfig();

        $this->assertArrayNotHasKey('amount', $config);
        $this->assertSame('EUR', $config['currency']);
    }

    public function testCartConfigFallsBackToStoreCurrencyWhenQuoteHasNone(): void
    {
        $quote = $this->makeQuoteMock(42, ['grand_total' => 10.0, 'quote_currency_code' => null]);
        $checkoutSession = $this->createMock(CheckoutSession::class);
        $checkoutSession->method('getQuote')->willReturn($quote);

        $block = $this->buildBlock($checkoutSession);

        $config = $block->getCartConfig();

        $this->assertSame('10.00', $config['amount']);
        $this->assertSame('EUR', $config['currency']);
    }

    public function testCartConfigWorksWithoutCheckoutSession(): void
    {
        $block = $this->buildBlock(null);

        $config = $block->getCartConfig();

        $this->assertArrayNotHasKey('amount', $config);
        $this->assertSame('EUR', $config['currency']);
    }

    private function buildBlock(?CheckoutSession $checkoutSession): PaypalExpress
    {
        return new PaypalExpress(
            $this->context,
            $this->configProviderAccount,
            $this->encryptor,
            $this->paypalConfig,
            null,
            $checkoutSession
        );
    }

    /**
     * @param int|null $id
     * @param array<string, mixed> $data
     * @return Quote&MockObject
     */
    private function makeQuoteMock(?int $id, array $data): Quote
    {
        $quote = $this->getMockBuilder(Quote::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getId', 'getData'])
            ->getMock();
        $quote->method('getId')->willReturn($id);
        $quote->method('getData')->willReturnCallback(
            static fn($key) => $data[$key] ?? null
        );
        return $quote;
    }
}
