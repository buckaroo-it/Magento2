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

namespace Buckaroo\Magento2\Test\Unit\Gateway\Validator;

use Buckaroo\Magento2\Gateway\Validator\PosPaymentValidator;
use Buckaroo\Magento2\Logging\BuckarooLoggerInterface;
use Buckaroo\Magento2\Model\ConfigProvider\Method\Pospayment;
use Magento\Framework\HTTP\Header;
use Magento\Framework\Stdlib\CookieManagerInterface;
use Magento\Payment\Gateway\Data\PaymentDataObjectInterface;
use Magento\Payment\Gateway\Validator\ResultInterface;
use Magento\Payment\Gateway\Validator\ResultInterfaceFactory;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Payment as QuotePayment;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class PosPaymentValidatorTest extends TestCase
{
    private const STORE_ID = 3;

    /** @var CookieManagerInterface|MockObject */
    private $cookieManager;

    /** @var Header|MockObject */
    private $header;

    /** @var Pospayment|MockObject */
    private $pospaymentConfig;

    /** @var array[] arguments passed to the result factory, one entry per validate() call */
    private array $results = [];

    private PosPaymentValidator $validator;

    protected function setUp(): void
    {
        $resultFactory = $this->getMockBuilder(ResultInterfaceFactory::class)
            ->onlyMethods(['create'])
            ->disableOriginalConstructor()
            ->getMock();
        $resultFactory->method('create')->willReturnCallback(function (array $data) {
            $this->results[] = $data;
            return $this->createMock(ResultInterface::class);
        });

        $this->cookieManager = $this->createMock(CookieManagerInterface::class);
        $this->header = $this->createMock(Header::class);
        $this->pospaymentConfig = $this->createMock(Pospayment::class);

        $this->validator = new PosPaymentValidator(
            $resultFactory,
            $this->cookieManager,
            $this->createMock(BuckarooLoggerInterface::class),
            $this->header,
            $this->pospaymentConfig
        );
    }

    public static function userAgentProvider(): array
    {
        return [
            'no restriction configured' => ['', 'Mozilla/5.0', true],
            'matching user agent'       => ['POS-Terminal/1.0', 'POS-Terminal/1.0', true],
            'other user agent'          => ['POS-Terminal/1.0', 'Mozilla/5.0', false],
        ];
    }

    #[DataProvider('userAgentProvider')]
    public function testUserAgentIsCheckedAgainstTheQuoteStoreSetting(
        string $configuredUserAgent,
        string $requestUserAgent,
        bool $expectedValid
    ): void {
        $this->cookieManager->method('getCookie')->willReturn('terminal-1');
        $this->header->method('getHttpUserAgent')->willReturn($requestUserAgent);
        $this->pospaymentConfig->expects($this->once())
            ->method('getUserAgent')
            ->with(self::STORE_ID)
            ->willReturn($configuredUserAgent);

        $this->validator->validate(['payment' => $this->createPaymentDataObject()]);

        $this->assertSame($expectedValid, $this->results[0]['isValid']);
    }

    public function testQuoteSubjectFromTheMethodListUsesTheQuoteStore(): void
    {
        $this->cookieManager->method('getCookie')->willReturn(null);
        $this->pospaymentConfig->expects($this->once())
            ->method('getUserAgent')
            ->with(self::STORE_ID)
            ->willReturn('');

        $quote = $this->createMock(Quote::class);
        $quote->method('getStoreId')->willReturn(self::STORE_ID);

        $this->validator->validate([
            'paymentMethodInstance' => $this->createMock(\Magento\Payment\Model\MethodInterface::class),
            'quote'                 => $quote,
        ]);

        $this->assertFalse($this->results[0]['isValid']);
    }

    public function testMissingTerminalIdIsInvalid(): void
    {
        $this->cookieManager->method('getCookie')->willReturn(null);
        $this->pospaymentConfig->method('getUserAgent')->willReturn('');

        $this->validator->validate(['payment' => $this->createPaymentDataObject()]);

        $this->assertFalse($this->results[0]['isValid']);
        $this->assertCount(1, $this->results[0]['failsDescription']);
    }

    public function testMissingPaymentIsInvalid(): void
    {
        $this->validator->validate([]);

        $this->assertFalse($this->results[0]['isValid']);
    }

    public function testErrorsDoNotCarryOverBetweenCalls(): void
    {
        $this->cookieManager->method('getCookie')->willReturnOnConsecutiveCalls(null, 'terminal-1');
        $this->pospaymentConfig->method('getUserAgent')->willReturn('');
        $paymentDO = $this->createPaymentDataObject();

        $this->validator->validate(['payment' => $paymentDO]);
        $this->validator->validate(['payment' => $paymentDO]);

        $this->assertFalse($this->results[0]['isValid']);
        $this->assertTrue($this->results[1]['isValid']);
        $this->assertSame([], $this->results[1]['failsDescription']);
    }

    private function createPaymentDataObject(): PaymentDataObjectInterface
    {
        $quote = $this->createMock(Quote::class);
        $quote->method('getStoreId')->willReturn(self::STORE_ID);

        $payment = $this->createMock(QuotePayment::class);
        $payment->method('getQuote')->willReturn($quote);

        $paymentDO = $this->createMock(PaymentDataObjectInterface::class);
        $paymentDO->method('getPayment')->willReturn($payment);

        return $paymentDO;
    }
}
