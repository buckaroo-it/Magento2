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
        $this->resultFactoryMock = $this->getMockBuilder(ResultInterfaceFactory::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->validator = new AvailableBasedOnCurrencyValidator($this->resultFactoryMock);
    }

    /**
     * @dataProvider dataProviderTestValidate
     */
    public function testValidate($allowedCurrenciesRaw, $currentCurrency, $expectedResult)
    {
        $quoteMock = $this->getMockBuilder(\Magento\Quote\Model\Quote::class)
            ->disableOriginalConstructor()
            ->getMock();

        $currencyMock = $this->getMockBuilder(\Magento\Quote\Api\Data\CurrencyInterface::class)
            ->disableOriginalConstructor()
            ->getMock();

        $currencyMock->expects($this->once())
            ->method('getQuoteCurrencyCode')
            ->willReturn($currentCurrency);

        $quoteMock->expects($this->once())
            ->method('getCurrency')
            ->willReturn($currencyMock);

        $paymentMethodInstanceMock = $this->getMockBuilder(\Magento\Payment\Model\MethodInterface::class)
            ->getMock();

        $paymentMethodInstanceMock->expects($this->once())
            ->method('getConfigData')
            ->willReturn($allowedCurrenciesRaw);

        $validationSubject = [
            'paymentMethodInstance' => $paymentMethodInstanceMock,
            'quote' => $quoteMock
        ];

        $resultMock = $this->getMockBuilder(ResultInterface::class)->getMock();

        $this->resultFactoryMock->expects($this->once())
            ->method('create')
            ->with(['isValid' => $expectedResult, 'failsDescription' => [], 'errorCodes' => []])
            ->willReturn($resultMock);

        $result = $this->validator->validate($validationSubject);

        $this->assertSame($resultMock, $result);
    }

    public function dataProviderTestValidate(): array
    {
        return [
            ['USD,EUR', 'USD', true],
            ['USD,EUR', 'GBP', false],
            [null, 'USD', true]
        ];
    }
}
