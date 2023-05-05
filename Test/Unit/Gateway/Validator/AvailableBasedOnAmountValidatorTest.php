<?php

namespace Buckaroo\Magento2\Test\Unit\Gateway\Validator;

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
        $this->resultFactoryMock = $this->getMockBuilder(ResultInterfaceFactory::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->validator = new AvailableBasedOnAmountValidator(
            $this->resultFactoryMock
        );
    }

    /**
     * @dataProvider availableBasedOnAmountValidatorDataProvider
     */
    public function testValidate($maximum, $minimum, $grandTotal, $isValid)
    {
        $paymentMethodInstanceMock = $this->getMockBuilder(MethodInterface::class)
            ->disableOriginalConstructor()
            ->getMock();
        $paymentMethodInstanceMock->expects($this->any())
            ->method('getConfigData')
            ->willReturnMap([
                ['max_amount', 1, $maximum],
                ['min_amount', 1, $minimum]
            ]);

        $quoteMock = $this->getMockBuilder(\Magento\Quote\Model\Quote::class)
            ->disableOriginalConstructor()
            ->addMethods(['getGrandTotal'])
            ->onlyMethods(['getStoreId'])
            ->getMock();
        $quoteMock->expects($this->once())
            ->method('getGrandTotal')
            ->willReturn($grandTotal);
        $quoteMock->expects($this->once())
            ->method('getStoreId')
            ->willReturn(1);

        $validationSubject = [
            'paymentMethodInstance' => $paymentMethodInstanceMock,
            'quote' => $quoteMock
        ];

        $expectedResultObj = $this->createMock(ResultInterface::class);
        $this->resultFactoryMock->expects($this->once())
            ->method('create')
            ->with(['isValid' => $isValid, 'failsDescription' => [], 'errorCodes' => []])
            ->willReturn($expectedResultObj);

        $result = $this->validator->validate($validationSubject);

        $this->assertSame($expectedResultObj, $result);
    }



    public function availableBasedOnAmountValidatorDataProvider()
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
