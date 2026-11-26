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
namespace Buckaroo\Magento2\Test\Unit\Model\Voucher;

use Buckaroo\Magento2\Gateway\Http\SDKTransferFactory;
use Buckaroo\Magento2\Helper\PaymentGroupTransaction;
use Buckaroo\Magento2\Model\ConfigProvider\Account;
use Buckaroo\Magento2\Model\Giftcard\Request\GiftcardException;
use Buckaroo\Magento2\Model\Idin\VerificationGuard;
use Buckaroo\Magento2\Model\Voucher\ApplyVoucherRequest;
use Buckaroo\Magento2\Service\Store\PushUrlBuilder;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Request\Http;
use Magento\Framework\Data\Form\FormKey;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\TestFramework\Unit\Helper\ObjectManager;
use Magento\Framework\UrlInterface;
use Magento\Payment\Gateway\Http\ClientInterface;
use Magento\Payment\Gateway\Http\TransferInterface;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Api\Data\CurrencyInterface;
use Magento\Quote\Model\Quote;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\TestCase;

/**
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */

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

        $request = (new ObjectManager($this))->getObject(
            ApplyVoucherRequest::class,
            ['httpRequest' => $httpRequest]
        );
        $request->setQuote($quote)->setVoucherCode('CODE');

        $getBody = new \ReflectionMethod(ApplyVoucherRequest::class, 'getBody');
        $body = $getBody->invoke($request);

        $this->assertSame('PLN', $body['currency']);
        $this->assertSame(430.0, $body['amountDebit']);
        $this->assertSame(3, $body['orderStoreId']);
    }
    public function testSendPostsTheFullVoucherRequest(): void
    {
        $httpRequest = $this->createMock(Http::class);
        $httpRequest->method('getServer')->willReturnCallback(
            fn($name) => ['HTTP_X_FORWARDED_FOR' => '2001:db8::1', 'REMOTE_ADDR' => '10.0.0.1'][$name] ?? false
        );

        $transfer = $this->createMock(TransferInterface::class);
        $transferFactory = $this->createMock(SDKTransferFactory::class);
        $transferFactory->expects($this->once())->method('create')->with([
            'payment_method'  => 'buckaroovoucher',
            'currency'        => 'PLN',
            'amountDebit'     => 430.0,
            'invoice'         => '000000042',
            'order'           => '000000042',
            'returnURL'       => 'https://shop.test/buckaroo/redirect/process/?form_key=FK123',
            'returnURLCancel' => 'https://shop.test/buckaroo/redirect/process/?form_key=FK123',
            'returnURLError'  => 'https://shop.test/buckaroo/redirect/process/?form_key=FK123',
            'returnURLReject' => 'https://shop.test/buckaroo/redirect/process/?form_key=FK123',
            'pushURL'         => 'https://shop.test/rest/V1/buckaroo/push',
            'clientIP'        => ['address' => '2001:db8::1', 'type' => '1'],
            'vouchercode'     => 'VOUCHER1',
            'originalTransactionKey' => 'ORIGKEY',
            'orderStoreId'    => 3,
        ])->willReturn($transfer);

        $response = new \stdClass();
        $client = $this->createMock(ClientInterface::class);
        $client->expects($this->once())->method('placeRequest')->with($transfer)
            ->willReturn(['object' => $response]);

        $request = $this->createRequest($httpRequest, $transferFactory, $client, 'X-Forwarded-For', 'ORIGKEY');
        $request->setQuote($this->createQuote())->setVoucherCode('  VOUCHER1 ');

        $this->assertSame($response, $request->send());
    }

    public function testSendIsRefusedWhenTheQuoteFailsIdinVerification(): void
    {
        $httpRequest = $this->createMock(Http::class);
        $transferFactory = $this->createMock(SDKTransferFactory::class);
        $transferFactory->expects($this->never())->method('create');
        $client = $this->createMock(ClientInterface::class);
        $client->expects($this->never())->method('placeRequest');

        $quote = $this->createQuote();
        $guard = $this->createMock(VerificationGuard::class);
        $guard->expects($this->once())->method('assertVerified')->with($quote)
            ->willThrowException(new LocalizedException(__('Age verification required')));

        $request = $this->createRequest($httpRequest, $transferFactory, $client, '', null, $guard);
        $request->setQuote($quote)->setVoucherCode('VOUCHER1');

        $this->expectException(GiftcardException::class);
        $this->expectExceptionMessage('Age verification required');
        $this->expectExceptionCode(0);
        $request->send();
    }

    /**
     * Without an ip header setting the remote address is used, and no original key is sent for a first voucher.
     */
    public function testBodyUsesRemoteAddressAndOmitsMissingOriginalKey(): void
    {
        $httpRequest = $this->createMock(Http::class);
        $httpRequest->method('getServer')->willReturnCallback(
            fn($name) => ['HTTP_X_FORWARDED_FOR' => '2001:db8::1', 'REMOTE_ADDR' => '192.0.2.7'][$name] ?? false
        );

        $request = $this->createRequest($httpRequest, null, null, '', null);
        $request->setQuote($this->createQuote())->setVoucherCode('VOUCHER1');

        $body = (new \ReflectionMethod(ApplyVoucherRequest::class, 'getBody'))->invoke($request);

        $this->assertSame(['address' => '192.0.2.7', 'type' => '0'], $body['clientIP']);
        $this->assertArrayNotHasKey('originalTransactionKey', $body);
    }

    public function testUnknownClientAddressIsSentAsUnknown(): void
    {
        $httpRequest = $this->createMock(Http::class);
        $httpRequest->method('getServer')->willReturn(false);

        $request = $this->createRequest($httpRequest, null, null, '', null);
        $request->setQuote($this->createQuote())->setVoucherCode('VOUCHER1');

        $body = (new \ReflectionMethod(ApplyVoucherRequest::class, 'getBody'))->invoke($request);

        $this->assertSame(['address' => 'unknown', 'type' => '0'], $body['clientIP']);
        $this->assertSame('000000042', $request->getIncrementId());
    }

    /**
     * @param Http                     $httpRequest
     * @param SDKTransferFactory|null  $transferFactory
     * @param ClientInterface|null     $client
     * @param string                   $ipHeader
     * @param string|null              $originalKey
     * @param VerificationGuard|null   $guard
     * @return ApplyVoucherRequest
     */
    private function createRequest(
        Http $httpRequest,
        ?SDKTransferFactory $transferFactory,
        ?ClientInterface $client,
        string $ipHeader,
        ?string $originalKey,
        ?VerificationGuard $guard = null
    ): ApplyVoucherRequest {
        $account = $this->createMock(Account::class);
        $account->method('getIpHeader')->with(3)->willReturn($ipHeader);

        $urlBuilder = $this->createMock(UrlInterface::class);
        $urlBuilder->method('getRouteUrl')
            ->with('buckaroo/redirect/process', ['_scope' => 3])
            ->willReturn('https://shop.test/buckaroo/redirect/process/');

        $formKey = $this->createMock(FormKey::class);
        $formKey->method('getFormKey')->willReturn('FK123');

        $groupTransaction = $this->createMock(PaymentGroupTransaction::class);
        $groupTransaction->method('getGroupTransactionOriginalTransactionKey')
            ->with('000000042')
            ->willReturn($originalKey);

        $pushUrlBuilder = $this->createMock(PushUrlBuilder::class);
        $pushUrlBuilder->method('getPushUrl')->with(3)->willReturn('https://shop.test/rest/V1/buckaroo/push');

        return new ApplyVoucherRequest(
            $this->createMock(ScopeConfigInterface::class),
            $account,
            $urlBuilder,
            $formKey,
            $this->createMock(StoreManagerInterface::class),
            $transferFactory ?? $this->createMock(SDKTransferFactory::class),
            $client ?? $this->createMock(ClientInterface::class),
            $httpRequest,
            $groupTransaction,
            $this->createMock(CartRepositoryInterface::class),
            $pushUrlBuilder,
            $guard ?? $this->createMock(VerificationGuard::class)
        );
    }

    /**
     * @return Quote
     */
    private function createQuote(): Quote
    {
        $currency = $this->createMock(CurrencyInterface::class);
        $currency->method('getQuoteCurrencyCode')->willReturn('PLN');

        $quote = $this->getMockBuilder(Quote::class)->disableOriginalConstructor()
            ->onlyMethods(['getCurrency', 'getStoreId', 'getReservedOrderId'])
            ->getMock();
        $quote->method('getCurrency')->willReturn($currency);
        $quote->method('getStoreId')->willReturn(3);
        $quote->method('getReservedOrderId')->willReturn('000000042');
        $quote->setData('grand_total', 430.0);

        return $quote;
    }
}
