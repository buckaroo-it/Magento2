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

namespace Buckaroo\Magento2\Test\Unit\Model\ExpressCheckout;

use Buckaroo\Magento2\Api\Data\PaypalExpress\OrderCreateResponseInterfaceFactory;
use Buckaroo\Magento2\Api\Data\PaypalExpress\ShippingAddressRequestInterface;
use Buckaroo\Magento2\Api\Data\QuoteCreateResponseInterfaceFactory;
use Buckaroo\Magento2\Logging\Mail;
use Buckaroo\Magento2\Logging\Log;
use Buckaroo\Magento2\Model\ConfigProvider\Method\Ideal as IdealConfig;
use Buckaroo\Magento2\Model\ConfigProvider\Method\Paypal as PaypalConfig;
use Buckaroo\Magento2\Model\Ideal\IdealException;
use Buckaroo\Magento2\Model\Ideal\QuoteBuilderInterfaceFactory as IdealQuoteBuilderFactory;
use Buckaroo\Magento2\Model\Ideal\QuoteCreate as IdealQuoteCreate;
use Buckaroo\Magento2\Model\PaypalExpress\OrderCreate as PaypalOrderCreate;
use Buckaroo\Magento2\Model\PaypalExpress\OrderUpdateFactory;
use Buckaroo\Magento2\Model\PaypalExpress\PaypalExpressException;
use Buckaroo\Magento2\Model\PaypalExpress\QuoteBuilderInterfaceFactory as PaypalQuoteBuilderFactory;
use Buckaroo\Magento2\Model\PaypalExpress\QuoteCreate as PaypalQuoteCreate;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Customer\Api\AddressRepositoryInterface;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Quote\Api\CartManagementInterface;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Api\ShipmentEstimationInterface;
use Magento\Quote\Model\MaskedQuoteIdToQuoteId;
use Magento\Quote\Model\QuoteRepository;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\TestCase;

class AvailabilityTest extends TestCase
{
    public static function paypalQuoteProvider(): array
    {
        return [
            'paypal disabled'        => ['0', 'Product,Cart', 'product'],
            'no buttons configured'  => ['1', null, 'product'],
            'product button off'     => ['1', 'Cart', 'product'],
            'cart button off'        => ['1', 'Product', 'cart'],
        ];
    }

    /**
     * @dataProvider paypalQuoteProvider
     */
    public function testPaypalQuoteCreateIsRejectedWhenUnavailable(
        string $active,
        ?string $buttons,
        string $page
    ): void {
        $quoteBuilderFactory = $this->createMock(PaypalQuoteBuilderFactory::class);
        $quoteBuilderFactory->expects($this->never())->method('create');
        $checkoutSession = $this->createMock(CheckoutSession::class);
        $checkoutSession->expects($this->never())->method('getQuote');

        $quoteCreate = new PaypalQuoteCreate(
            $this->createMock(QuoteCreateResponseInterfaceFactory::class),
            $quoteBuilderFactory,
            $this->createMock(CustomerSession::class),
            $this->createMock(CustomerRepositoryInterface::class),
            $checkoutSession,
            $this->createMock(QuoteRepository::class),
            $this->createMock(ShipmentEstimationInterface::class),
            $this->createLogMock(),
            $this->paypalConfig($active, $buttons)
        );

        $this->expectException(PaypalExpressException::class);
        $quoteCreate->execute(
            $this->createMock(ShippingAddressRequestInterface::class),
            $page,
            'product=12&qty=1'
        );
    }

    public static function paypalOrderProvider(): array
    {
        return [
            'paypal disabled'       => ['0', 'Product,Cart'],
            'no buttons configured' => ['1', null],
            'empty buttons'         => ['1', ''],
        ];
    }

    /**
     * @dataProvider paypalOrderProvider
     */
    public function testPaypalOrderCreateIsRejectedWhenUnavailable(string $active, ?string $buttons): void
    {
        $quoteManagement = $this->createMock(CartManagementInterface::class);
        $quoteManagement->expects($this->never())->method('placeOrder');
        $maskedQuoteIdToQuoteId = $this->createMock(MaskedQuoteIdToQuoteId::class);
        $maskedQuoteIdToQuoteId->expects($this->never())->method('execute');

        $orderCreate = new PaypalOrderCreate(
            $this->createMock(OrderCreateResponseInterfaceFactory::class),
            $quoteManagement,
            $maskedQuoteIdToQuoteId,
            $this->createMock(CustomerSession::class),
            $this->createMock(CheckoutSession::class),
            $this->createMock(CartRepositoryInterface::class),
            $this->createMock(OrderRepositoryInterface::class),
            $this->createMock(OrderUpdateFactory::class),
            $this->createLogMock(),
            $this->paypalConfig($active, $buttons)
        );

        $this->expectException(PaypalExpressException::class);
        $orderCreate->execute('paypal-order-id', 'masked-cart-id');
    }

    public static function idealQuoteProvider(): array
    {
        return [
            'ideal disabled'         => ['0', '1', 'Product,Cart', 'product'],
            'fast checkout disabled' => ['1', '0', 'Product,Cart', 'product'],
            'no buttons configured'  => ['1', '1', null, 'cart'],
            'product button off'     => ['1', '1', 'Cart', 'product'],
            'cart button off'        => ['1', '1', 'Product', 'cart'],
        ];
    }

    /**
     * @dataProvider idealQuoteProvider
     */
    public function testIdealQuoteCreateIsRejectedWhenUnavailable(
        string $active,
        string $fastCheckout,
        ?string $buttons,
        string $page
    ): void {
        $quoteBuilderFactory = $this->createMock(IdealQuoteBuilderFactory::class);
        $quoteBuilderFactory->expects($this->never())->method('create');
        $checkoutSession = $this->createMock(CheckoutSession::class);
        $checkoutSession->expects($this->never())->method('getQuote');

        $idealConfig = $this->createMock(IdealConfig::class);
        $idealConfig->method('isIDealEnabled')->willReturn($active);
        $idealConfig->method('isFastCheckoutEnabled')->willReturn($fastCheckout);
        $idealConfig->method('canShowButtonForPage')->willReturnCallback(
            function ($buttonPage) use ($buttons) {
                return $buttons !== null && in_array($buttonPage, explode(',', $buttons), true);
            }
        );

        $quoteCreate = new IdealQuoteCreate(
            $this->createMock(QuoteCreateResponseInterfaceFactory::class),
            $quoteBuilderFactory,
            $this->createMock(CustomerSession::class),
            $this->createMock(CustomerRepositoryInterface::class),
            $this->createMock(AddressRepositoryInterface::class),
            $checkoutSession,
            $this->createMock(QuoteRepository::class),
            $this->createMock(ShipmentEstimationInterface::class),
            $this->createLogMock(),
            $this->createMock(StoreManagerInterface::class),
            $idealConfig
        );

        $this->expectException(IdealException::class);
        $quoteCreate->execute($page, 'product=12&qty=1');
    }

    private function createLogMock(): Log
    {
        $log = $this->createMock(Log::class);
        $mail = new \ReflectionProperty(Log::class, 'mail');
        $mail->setAccessible(true);
        $mail->setValue($log, $this->createMock(Mail::class));

        return $log;
    }

    private function paypalConfig(string $active, ?string $buttons): PaypalConfig
    {
        $paypalConfig = $this->createMock(PaypalConfig::class);
        $paypalConfig->method('isPayPalEnabled')->willReturn($active);
        $paypalConfig->method('getExpressButtons')->willReturn($buttons);
        $paypalConfig->method('canShowButtonForPage')->willReturnCallback(
            function ($buttonPage) use ($buttons) {
                return $buttons !== null && in_array($buttonPage, explode(',', $buttons), true);
            }
        );

        return $paypalConfig;
    }
}
