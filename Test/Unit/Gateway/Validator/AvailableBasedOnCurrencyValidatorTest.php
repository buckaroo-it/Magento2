<?php

namespace Buckaroo\Magento2\Test\Unit\Gateway\Validator;

use Buckaroo\Magento2\Gateway\Validator\AvailableBasedOnCurrencyValidator;
use Buckaroo\Magento2\Service\TransactionCurrencyResolver;
use Magento\Payment\Gateway\Validator\ResultInterface;
use Magento\Payment\Gateway\Validator\ResultInterfaceFactory;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class AvailableBasedOnCurrencyValidatorTest extends TestCase
{
    /**
     * @var ResultInterfaceFactory|MockObject
     */
    private $resultFactoryMock;

    /**
     * @var TransactionCurrencyResolver|MockObject
     */
    private $transactionCurrencyResolverMock;

    /**
     * @var AvailableBasedOnCurrencyValidator
     */
    private $validator;

    protected function setUp(): void
    {
        $this->resultFactoryMock = $this->createMock(ResultInterfaceFactory::class);
        $this->transactionCurrencyResolverMock = $this->createMock(TransactionCurrencyResolver::class);

        $this->validator = new AvailableBasedOnCurrencyValidator(
            $this->resultFactoryMock,
            $this->transactionCurrencyResolverMock
        );
    }

    /**
     * @dataProvider dataProviderTestValidate
     *
     * @param string $quoteCurrency
     * @param bool   $currencyAllowed
     * @param bool   $expectedResult
     */
    public function testValidate(
        string $quoteCurrency,
        bool $currencyAllowed,
        bool $expectedResult
    ) {
        $quoteMock = $this->createMock(\Magento\Quote\Model\Quote::class);

        $currencyMock = $this->createMock(\Magento\Quote\Api\Data\CurrencyInterface::class);

        $currencyMock->method('getQuoteCurrencyCode')
            ->willReturn($quoteCurrency);

        $quoteMock->method('getCurrency')
            ->willReturn($currencyMock);

        $paymentMethodInstanceMock = $this->getMockBuilder(\Magento\Payment\Model\MethodInterface::class)
            ->getMock();

        $this->transactionCurrencyResolverMock->method('isCurrencyAllowed')
            ->with($quoteCurrency, $paymentMethodInstanceMock)
            ->willReturn($currencyAllowed);

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
            'quote currency supported by method'     => ['PLN', true, true],
            'quote currency not supported by method' => ['CZK', false, false],
        ];
    }
}
