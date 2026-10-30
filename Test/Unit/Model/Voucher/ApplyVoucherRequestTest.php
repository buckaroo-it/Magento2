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

use Buckaroo\Magento2\Gateway\Http\SDKTransferFactory;
use Buckaroo\Magento2\Helper\PaymentGroupTransaction;
use Buckaroo\Magento2\Model\ConfigProvider\Account;
use Buckaroo\Magento2\Model\Voucher\ApplyVoucherRequest;
use Buckaroo\Magento2\Service\Store\PushUrlBuilder;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Request\Http;
use Magento\Framework\Data\Form\FormKey;
use Magento\Framework\UrlInterface;
use Magento\Payment\Gateway\Http\ClientInterface;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Api\Data\CurrencyInterface;
use Magento\Quote\Model\Quote;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\TestCase;

class ApplyVoucherRequestTest extends TestCase
{
    /**
     * The amount is the quote grand total (quote currency), so the currency sent must be the
     * quote currency; and the request names the quote's store so the adapter uses that store's
     * credentials and mode instead of the ambient store's.
     */
    public function testBodyCarriesTheQuoteCurrencyAndStore(): void
    {
        $currency = $this->createMock(CurrencyInterface::class);
        $currency->method('getQuoteCurrencyCode')->willReturn('PLN');
        $currency->method('getBaseCurrencyCode')->willReturn('EUR');

        $quote = $this->getMockBuilder(Quote::class)->disableOriginalConstructor()
            ->onlyMethods(['getCurrency', 'getStoreId', 'getReservedOrderId'])
            ->getMock();
        $quote->method('getCurrency')->willReturn($currency);
        $quote->method('getStoreId')->willReturn('3');
        $quote->method('getReservedOrderId')->willReturn('000000042');
        $quote->setData('grand_total', 430.0);

        $httpRequest = $this->createMock(Http::class);
        $httpRequest->method('getServer')->willReturn('127.0.0.1');

        $request = new ApplyVoucherRequest(
            $this->createMock(ScopeConfigInterface::class),
            $this->createMock(Account::class),
            $this->createMock(UrlInterface::class),
            $this->createMock(FormKey::class),
            $this->createMock(StoreManagerInterface::class),
            $this->createMock(SDKTransferFactory::class),
            $this->createMock(ClientInterface::class),
            $httpRequest,
            $this->createMock(PaymentGroupTransaction::class),
            $this->createMock(CartRepositoryInterface::class),
            $this->createMock(PushUrlBuilder::class)
        );
        $request->setQuote($quote)->setVoucherCode('CODE');

        $getBody = new \ReflectionMethod(ApplyVoucherRequest::class, 'getBody');
        $body = $getBody->invoke($request);

        $this->assertSame('PLN', $body['currency']);
        $this->assertSame(430.0, $body['amountDebit']);
        $this->assertSame(3, $body['orderStoreId']);
    }
}
