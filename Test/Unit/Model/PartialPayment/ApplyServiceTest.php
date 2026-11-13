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

namespace Buckaroo\Magento2\Test\Unit\Model\PartialPayment;

use Buckaroo\Magento2\Gateway\Http\SDKTransferFactory;
use Buckaroo\Magento2\Helper\PaymentGroupTransaction;
use Buckaroo\Magento2\Logging\BuckarooLoggerInterface;
use Buckaroo\Magento2\Model\ConfigProvider\Factory as ConfigProviderFactory;
use Buckaroo\Magento2\Model\ConfigProvider\Method\Voucher as VoucherConfig;
use Buckaroo\Magento2\Model\Giftcard\Api\ApiException;
use Buckaroo\Magento2\Model\Giftcard\Response\Giftcard as GiftcardResponse;
use Buckaroo\Magento2\Model\GroupTransaction;
use Buckaroo\Magento2\Model\Idin\VerificationGuard;
use Buckaroo\Magento2\Model\PartialPayment\ApplyService;
use Buckaroo\Magento2\Model\PartialPayment\Instrument\Voucher;
use Buckaroo\Magento2\Model\PartialPayment\PartialPaymentRejectedException;
use Buckaroo\Magento2\Model\PartialPayment\PartialPaymentUnavailableException;
use Buckaroo\Magento2\Model\PartialPayment\RequestBodyBuilder;
use Buckaroo\Magento2\Service\Giftcard\AttemptLimit;
use Buckaroo\Transaction\Response\TransactionResponse;
use Magento\Payment\Gateway\Http\ClientException;
use Magento\Payment\Gateway\Http\ClientInterface;
use Magento\Payment\Gateway\Http\TransferInterface;
use Magento\Payment\Helper\Data as PaymentHelper;
use Magento\Payment\Model\MethodInterface;
use Magento\Quote\Model\Quote;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class ApplyServiceTest extends TestCase
{
    /** @var ClientInterface&MockObject */
    private $client;

    /** @var GiftcardResponse&MockObject */
    private $gatewayResponse;

    /** @var PaymentGroupTransaction&MockObject */
    private $groupTransaction;

    /** @var AttemptLimit&MockObject */
    private $attemptLimit;

    /** @var MethodInterface&MockObject */
    private $method;

    /** @var VoucherConfig&MockObject */
    private $voucherConfig;

    /** @var RequestBodyBuilder&MockObject */
    private $requestBodyBuilder;

    /** @var ApplyService */
    private $service;

    protected function setUp(): void
    {
        $this->client = $this->createMock(ClientInterface::class);
        $this->gatewayResponse = $this->createMock(GiftcardResponse::class);
        $this->groupTransaction = $this->createMock(PaymentGroupTransaction::class);
        $this->attemptLimit = $this->createMock(AttemptLimit::class);

        $this->method = $this->createMock(MethodInterface::class);
        $this->method->method('isAvailable')->willReturn(true);
        $paymentHelper = $this->createMock(PaymentHelper::class);
        $paymentHelper->method('getMethodInstance')->with(VoucherConfig::CODE)->willReturn($this->method);

        $this->voucherConfig = $this->createMock(VoucherConfig::class);
        $this->voucherConfig->method('getAllowedCurrencies')->willReturn(['EUR']);
        $configProviderFactory = $this->createMock(ConfigProviderFactory::class);
        $configProviderFactory->method('get')->with(VoucherConfig::CODE)->willReturn($this->voucherConfig);

        $this->requestBodyBuilder = $this->createMock(RequestBodyBuilder::class);
        $this->requestBodyBuilder->method('getCurrency')->willReturn('EUR');
        $this->requestBodyBuilder->method('build')->willReturn(['payment_method' => 'buckaroovoucher']);

        $transferFactory = $this->createMock(SDKTransferFactory::class);
        $transferFactory->method('create')->willReturn($this->createMock(TransferInterface::class));

        $this->service = new ApplyService(
            $this->requestBodyBuilder,
            $transferFactory,
            $this->client,
            $this->gatewayResponse,
            $this->groupTransaction,
            $this->attemptLimit,
            $paymentHelper,
            $configProviderFactory,
            $this->createMock(VerificationGuard::class),
            $this->createMock(BuckarooLoggerInterface::class)
        );
    }

    public function testEmptyCodeIsRejectedWithoutCallingTheGateway(): void
    {
        $this->client->expects($this->never())->method('placeRequest');

        $this->expectException(PartialPaymentRejectedException::class);
        $this->expectExceptionMessage('Please enter a voucher code.');

        $this->service->apply($this->createQuote(), new Voucher('  '));
    }

    public function testInactiveCartIsRejected(): void
    {
        $quote = $this->createQuote();
        $quote->setIsActive(false);
        $this->client->expects($this->never())->method('placeRequest');

        $this->expectException(PartialPaymentRejectedException::class);
        $this->expectExceptionMessage("The cart isn't active.");

        $this->service->apply($quote, new Voucher('CODE'));
    }

    public function testUnavailableMethodIsRejected(): void
    {
        $method = $this->createMock(MethodInterface::class);
        $method->method('isAvailable')->willReturn(false);
        $paymentHelper = $this->createMock(PaymentHelper::class);
        $paymentHelper->method('getMethodInstance')->willReturn($method);
        $service = $this->serviceWith(['paymentHelper' => $paymentHelper]);

        $this->client->expects($this->never())->method('placeRequest');
        $this->expectException(PartialPaymentRejectedException::class);
        $this->expectExceptionMessage('This payment method is not available.');

        $service->apply($this->createQuote(), new Voucher('CODE'));
    }

    public function testQuoteCurrencyTheMethodDoesNotAcceptIsRejected(): void
    {
        $requestBodyBuilder = $this->createMock(RequestBodyBuilder::class);
        $requestBodyBuilder->method('getCurrency')->willReturn('USD');
        $service = $this->serviceWith(['requestBodyBuilder' => $requestBodyBuilder]);

        $this->client->expects($this->never())->method('placeRequest');
        $this->expectException(PartialPaymentRejectedException::class);
        $this->expectExceptionMessage('does not support the selected currency');

        $service->apply($this->createQuote(), new Voucher('CODE'));
    }

    public function testTooManyFailedAttemptsAreRejected(): void
    {
        $this->attemptLimit->method('assertWithinLimit')->willThrowException(new ApiException('limit'));
        $this->client->expects($this->never())->method('placeRequest');

        $this->expectException(PartialPaymentRejectedException::class);
        $this->expectExceptionMessage('Too many attempts. Please try again later.');

        $this->service->apply($this->createQuote(), new Voucher('CODE'));
    }

    public function testTransportFailureIsReportedAsUnavailable(): void
    {
        $this->client->method('placeRequest')->willThrowException(new ClientException(__('timeout')));
        $this->attemptLimit->expects($this->never())->method('registerFailedAttempt');

        $this->expectException(PartialPaymentUnavailableException::class);

        $this->service->apply($this->createQuote(), new Voucher('CODE'));
    }

    public function testResponseWithoutTransactionIsReportedAsUnavailable(): void
    {
        $this->client->method('placeRequest')->willReturn(['object' => []]);
        $this->gatewayResponse->expects($this->never())->method('set');

        $this->expectException(PartialPaymentUnavailableException::class);

        $this->service->apply($this->createQuote(), new Voucher('CODE'));
    }

    /**
     * The gateway accepted (and charged) the voucher, then storing the outcome failed: the
     * shopper must not be told to check the code, the outcome is unknown to the shop
     */
    public function testAFailureAfterTheGatewayAcceptedIsReportedAsUnavailable(): void
    {
        $this->client->method('placeRequest')->willReturn(['object' => $this->createResponse(true, [], '10.00')]);
        $this->gatewayResponse->method('set')->willThrowException(new \RuntimeException('db down'));
        $this->attemptLimit->expects($this->never())->method('registerFailedAttempt');

        $this->expectException(PartialPaymentUnavailableException::class);

        $this->service->apply($this->createQuote(), new Voucher('CODE'));
    }

    public function testGatewayRejectionShowsTheFullReasonAndCountsTheAttempt(): void
    {
        $quote = $this->createQuote();
        $this->client->method('placeRequest')->willReturn(['object' => $this->createResponse(false)]);
        $this->gatewayResponse->method('getErrorMessage')
            ->willReturn('Voucher action failed:Voucher expired: valid until 2026-01-01');
        $this->attemptLimit->expects($this->once())->method('registerFailedAttempt')->with($quote);

        $this->expectException(PartialPaymentRejectedException::class);
        $this->expectExceptionMessage('Voucher expired: valid until 2026-01-01');

        $this->service->apply($quote, new Voucher('CODE'));
    }

    public function testGatewayRejectionWithoutReasonShowsTheFallback(): void
    {
        $this->client->method('placeRequest')->willReturn(['object' => $this->createResponse(false)]);
        $this->gatewayResponse->method('getErrorMessage')->willReturn('');

        $this->expectException(PartialPaymentRejectedException::class);
        $this->expectExceptionMessage('The voucher could not be redeemed. Please check the code and try again.');

        $this->service->apply($this->createQuote(), new Voucher('CODE'));
    }

    public function testPartialPaymentReturnsTheRemainderFromTheGateway(): void
    {
        $response = $this->createResponse(true, [
            'RequiredAction' => ['PayRemainderDetails' => ['RemainderAmount' => 24.0, 'Currency' => 'EUR']],
        ], '10.00');
        $this->client->method('placeRequest')->willReturn(['object' => $response]);
        $this->groupTransaction->method('getAlreadyPaid')->with('000000042')->willReturn(10.0);
        $transaction = $this->createMock(GroupTransaction::class);
        $this->groupTransaction->method('getByTransactionIdWithName')->with('KEY')->willReturn($transaction);

        $result = $this->service->apply($this->createQuote(), new Voucher('CODE'));

        $this->assertSame(10.0, $result->getAmountDebit());
        $this->assertSame(24.0, $result->getRemainderAmount());
        $this->assertSame(10.0, $result->getAlreadyPaid());
        $this->assertSame('EUR', $result->getCurrency());
        $this->assertSame($transaction, $result->getTransaction());
        $this->assertFalse($result->isFullyPaid());
    }

    public function testFullPaymentWithoutRemainderDetailsIsFullyPaid(): void
    {
        $this->client->method('placeRequest')->willReturn(['object' => $this->createResponse(true, [], '24.00')]);
        $this->groupTransaction->method('getAlreadyPaid')->willReturn(34.0);
        $this->groupTransaction->method('getByTransactionIdWithName')
            ->willReturn($this->createMock(GroupTransaction::class));

        $result = $this->service->apply($this->createQuote(), new Voucher('CODE'));

        $this->assertSame(0.0, $result->getRemainderAmount());
        $this->assertTrue($result->isFullyPaid());
    }

    private function createQuote(): Quote
    {
        $quote = $this->getMockBuilder(Quote::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getStoreId', 'save'])
            ->getMock();
        $quote->method('getStoreId')->willReturn(1);
        $quote->setId(5);
        $quote->setIsActive(true);
        $quote->setItemsCount(1);
        $quote->setGrandTotal(34.0);
        $quote->setReservedOrderId('000000042');

        return $quote;
    }

    private function createResponse(bool $success, array $data = [], string $amount = '0'): TransactionResponse
    {
        $response = $this->createMock(TransactionResponse::class);
        $response->method('isSuccess')->willReturn($success);
        $response->method('data')->willReturn($data);
        $response->method('getAmount')->willReturn($amount);
        $response->method('getTransactionKey')->willReturn('KEY');

        return $response;
    }

    /**
     * @param array<string, object> $overrides
     */
    private function serviceWith(array $overrides): ApplyService
    {
        $paymentHelper = $this->createMock(PaymentHelper::class);
        $paymentHelper->method('getMethodInstance')->willReturn($this->method);
        $configProviderFactory = $this->createMock(ConfigProviderFactory::class);
        $configProviderFactory->method('get')->willReturn($this->voucherConfig);
        $transferFactory = $this->createMock(SDKTransferFactory::class);
        $transferFactory->method('create')->willReturn($this->createMock(TransferInterface::class));

        return new ApplyService(
            $overrides['requestBodyBuilder'] ?? $this->requestBodyBuilder,
            $transferFactory,
            $this->client,
            $this->gatewayResponse,
            $this->groupTransaction,
            $this->attemptLimit,
            $overrides['paymentHelper'] ?? $paymentHelper,
            $configProviderFactory,
            $this->createMock(VerificationGuard::class),
            $this->createMock(BuckarooLoggerInterface::class)
        );
    }
}
