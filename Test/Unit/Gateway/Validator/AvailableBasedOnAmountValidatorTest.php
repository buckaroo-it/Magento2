<?php

namespace Buckaroo\Magento2\Test\Unit\Gateway\Validator;


use PHPUnit\Framework\Attributes\DataProvider;
use Buckaroo\Magento2\Gateway\Validator\AvailableBasedOnAmountValidator;
use Magento\Payment\Gateway\Validator\ResultInterface;
use Magento\Payment\Gateway\Validator\ResultInterfaceFactory;
use Magento\Payment\Model\MethodInterface;
use PHPUnit\Framework\TestCase;

class AvailableBasedOnAmountValidatorTest extends TestCase
{
    /**
     * @var ResultInterfaceFactory|MockObject
     */
    private $resultFactoryMock;

    /**
     * @var AvailableBasedOnAmountValidator
     */
    private $validator;

    protected function setUp(): void
    {
        $this->resultFactoryMock = $this->createMock(ResultInterfaceFactory::class);

        $this->validator = new AvailableBasedOnAmountValidator(
            $this->resultFactoryMock
        );
    }

    /**
     *
     * @param mixed $maximum
     * @param mixed $minimum
     * @param mixed $grandTotal
     * @param mixed $isValid
     */
    #[DataProvider('availableBasedOnAmountValidatorDataProvider')]
    public function testValidate($maximum, $minimum, $grandTotal, $isValid)
    {
        $paymentMethodInstanceMock = $this->createMock(MethodInterface::class);
        $paymentMethodInstanceMock->expects($this->any())
            ->method('getConfigData')
            ->willReturnMap([
                ['max_amount', 1, $maximum],
                ['min_amount', 1, $minimum]
            ]);

        // Create a more robust Quote mock that doesn't trigger internal factory calls
        $quoteMock = $this->getMockBuilder(\Buckaroo\Magento2\Test\Unit\Stubs\QuoteStub::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getStoreId', 'getShippingAddress', 'getBillingAddress', 'getGrandTotal'])->getMock();
        $quoteMock->method('getGrandTotal')
            ->willReturn($grandTotal);
        $quoteMock->method('getStoreId')
            ->willReturn(1);

        $addressMock = $this->getMockBuilder(\Magento\Quote\Model\Quote\Address::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getCompany'])
            ->getMock();
        $addressMock->method('getCompany')->willReturn(null);
        $quoteMock->method('getShippingAddress')->willReturn($addressMock);
        $quoteMock->method('getBillingAddress')->willReturn($addressMock);

        $validationSubject = [
            'paymentMethodInstance' => $paymentMethodInstanceMock,
            'quote' => $quoteMock
        ];

        $expectedResultObj = $this->createMock(ResultInterface::class);
        $this->resultFactoryMock->method('create')
            ->with(['isValid' => $isValid, 'failsDescription' => [], 'errorCodes' => []])
            ->willReturn($expectedResultObj);

        $result = $this->validator->validate($validationSubject);

        $this->assertSame($expectedResultObj, $result);
    }

    /**
     * Zakelijk op rekening is only offered within the range ABN AMRO accepts
     * (302.50 - 25000), whatever lower minimum or higher maximum is configured.
     */
    #[DataProvider('zakelijkOpRekeningDataProvider')]
    public function testZakelijkOpRekeningStaysWithinTheAbnAmroRange($maximum, $minimum, $grandTotal, $isValid)
    {
        $paymentMethodInstanceMock = $this->createMock(MethodInterface::class);
        $paymentMethodInstanceMock->method('getCode')
            ->willReturn(\Buckaroo\Magento2\Model\ConfigProvider\Method\ZakelijkOpRekening::CODE);
        $paymentMethodInstanceMock->method('getConfigData')
            ->willReturnMap([
                ['max_amount', 1, $maximum],
                ['min_amount', 1, $minimum]
            ]);

        $quoteMock = $this->getMockBuilder(\Buckaroo\Magento2\Test\Unit\Stubs\QuoteStub::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getStoreId', 'getShippingAddress', 'getBillingAddress', 'getGrandTotal'])->getMock();
        $quoteMock->method('getGrandTotal')->willReturn($grandTotal);
        $quoteMock->method('getStoreId')->willReturn(1);

        $addressMock = $this->getMockBuilder(\Magento\Quote\Model\Quote\Address::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getCompany'])
            ->getMock();
        $addressMock->method('getCompany')->willReturn('Testbedrijf BV');
        $quoteMock->method('getShippingAddress')->willReturn($addressMock);
        $quoteMock->method('getBillingAddress')->willReturn($addressMock);

        $expectedResultObj = $this->createMock(ResultInterface::class);
        $this->resultFactoryMock->method('create')
            ->with(['isValid' => $isValid, 'failsDescription' => [], 'errorCodes' => []])
            ->willReturn($expectedResultObj);

        $result = $this->validator->validate([
            'paymentMethodInstance' => $paymentMethodInstanceMock,
            'quote' => $quoteMock
        ]);

        $this->assertSame($expectedResultObj, $result);
    }

    public static function zakelijkOpRekeningDataProvider()
    {
        return [
            'default range, at the minimum' => [25000, 302.5, 302.50, true],
            'default range, below the minimum' => [25000, 302.5, 302.49, false],
            'default range, at the maximum' => [25000, 302.5, 25000.00, true],
            'default range, above the maximum' => [25000, 302.5, 25000.01, false],
            'configured 250 still needs 302.50' => [25000, 250, 299.20, false],
            'configured 30000 still stops at 25000' => [30000, 302.5, 26000, false],
            'nothing configured uses the ABN AMRO range' => [null, null, 300, false],
            'nothing configured, inside the range' => [null, null, 353.60, true],
            'narrower configured range is kept' => [1000, 500, 400, false],
        ];
    }

    public static function availableBasedOnAmountValidatorDataProvider()
    {
        return [
            'valid' => [
                'maximum' => 100,
                'minimum' => 10,
                'grandTotal' => 50,
                'isValid' => true,
            ],
            'minimum invalid' => [
                'maximum' => 100,
                'minimum' => 10,
                'grandTotal' => 5,
                'isValid' => false,
            ],
            'maximum invalid' => [
                'maximum' => 100,
                'minimum' => 10,
                'grandTotal' => 150,
                'isValid' => false,
            ],
            'no minimum or maximum' => [
                'maximum' => null,
                'minimum' => null,
                'grandTotal' => 50,
                'isValid' => true,
            ],
        ];
    }
}
