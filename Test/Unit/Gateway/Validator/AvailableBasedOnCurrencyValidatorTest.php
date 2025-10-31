<?php

namespace Buckaroo\Magento2\Test\Unit\Gateway\Validator;

use Buckaroo\Magento2\Gateway\Validator\AvailableBasedOnCurrencyValidator;
use Magento\Payment\Gateway\Validator\ResultInterface;
use Magento\Payment\Gateway\Validator\ResultInterfaceFactory;
use Magento\Quote\Model\Currency;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class AvailableBasedOnCurrencyValidatorTest extends TestCase
{
    /**
     * @var ResultInterfaceFactory|MockObject
     */
    private $resultFactoryMock;

    /**
     * @var AvailableBasedOnCurrencyValidator
     */
    private $validator;

    protected function setUp(): void
    {
        $this->resultFactoryMock = $this->createMock(ResultInterfaceFactory::class);

        $this->validator = new AvailableBasedOnCurrencyValidator($this->resultFactoryMock);
    }

    /**
     * @dataProvider dataProviderTestValidate
     *
     * @param mixed $allowedCurrenciesRaw
     * @param mixed $currentCurrency
     * @param mixed $expectedResult
     */
    public function testValidate($allowedCurrenciesRaw, $currentCurrency, $expectedResult)
    {
        $quoteMock = $this->createMock(\Magento\Quote\Model\Quote::class);

        $currencyMock = $this->createMock(\Magento\Quote\Api\Data\CurrencyInterface::class);

        $currencyMock->method('getQuoteCurrencyCode')
            ->willReturn($currentCurrency);

        $quoteMock->method('getCurrency')
            ->willReturn($currencyMock);

        $paymentMethodInstanceMock = $this->getMockBuilder(\Magento\Payment\Model\MethodInterface::class)
            ->getMock();

        $paymentMethodInstanceMock->method('getConfigData')
            ->willReturn($allowedCurrenciesRaw);

        $validationSubject = [
            'paymentMethodInstance' => $paymentMethodInstanceMock,
            'quote' => $quoteMock
        ];

        $resultMock = $this->getMockBuilder(ResultInterface::class)->getMock();

        $this->resultFactoryMock->method('create')
            ->with(['isValid' => $expectedResult, 'failsDescription' => [], 'errorCodes' => []])
            ->willReturn($resultMock);

        $result = $this->validator->validate($validationSubject);

        $this->assertSame($resultMock, $result);
    }

    public static function dataProviderTestValidate(): array
    {
        return [
            ['USD,EUR', 'USD', true],
            ['USD,EUR', 'GBP', false],
            [null, 'USD', true]
        ];
    }
}
