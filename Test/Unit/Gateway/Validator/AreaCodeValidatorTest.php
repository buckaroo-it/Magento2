<?php

namespace Buckaroo\Magento2\Test\Unit\Gateway\Validator;

use Buckaroo\Magento2\Gateway\Validator\AreaCodeValidator;
use Magento\Framework\App\Area;
use Magento\Framework\App\State;
use Magento\Payment\Gateway\Validator\ResultInterface;
use Magento\Payment\Gateway\Validator\ResultInterfaceFactory;
use Magento\Payment\Model\MethodInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class AreaCodeValidatorTest extends TestCase
{
    /**
     * @var ResultInterfaceFactory|MockObject
     */
    private $resultFactory;

    /**
     * @var State|MockObject
     */
    private $state;

    /**
     * @var AreaCodeValidator
     */
    private $validator;

    protected function setUp(): void
    {
        $this->resultFactory = $this->getMockBuilder(ResultInterfaceFactory::class)
            ->onlyMethods(['create'])
            ->disableOriginalConstructor()
            ->getMock();

        $this->state = $this->getMockBuilder(State::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->validator = new AreaCodeValidator(
            $this->resultFactory,
            $this->state
        );
    }

    /**
     * @dataProvider validateDataProvider
     */
    public function testValidate($areaCode, $configData, $expectedResult)
    {
        $validationSubject = [
            'paymentMethodInstance' => $this->getMockBuilder(MethodInterface::class)
                ->disableOriginalConstructor()
                ->getMock()
        ];

        $this->state->expects($this->once())
            ->method('getAreaCode')
            ->willReturn($areaCode);

        $validationSubject['paymentMethodInstance']->expects($this->once())
            ->method('getConfigData')
            ->with('available_in_backend')
            ->willReturn($configData);

        $resultMock = $this->getMockBuilder(ResultInterface::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->resultFactory->expects($this->once())
            ->method('create')
            ->with(['isValid' => $expectedResult, 'failsDescription' => [], 'errorCodes' => []])
            ->willReturn($resultMock);

        $result = $this->validator->validate($validationSubject);

        $this->assertSame($resultMock, $result);
    }

    public function validateDataProvider()
    {
        return [
            'backend with config data 0' => [
                'areaCode' => Area::AREA_ADMINHTML,
                'configData' => '0',
                'expectedResult' => false
            ],
            'backend with config data 1' => [
                'areaCode' => Area::AREA_ADMINHTML,
                'configData' => '1',
                'expectedResult' => true
            ],
            'backend without config data' => [
                'areaCode' => Area::AREA_ADMINHTML,
                'configData' => null,
                'expectedResult' => true
            ],
            'front end with config data 0' => [
                'areaCode' => Area::AREA_FRONTEND,
                'configData' => '0',
                'expectedResult' => true
            ],
            'front end with config data 1' => [
                'areaCode' => Area::AREA_FRONTEND,
                'configData' => '1',
                'expectedResult' => true
            ],
            'front end without config data' => [
                'areaCode' => Area::AREA_FRONTEND,
                'configData' => null,
                'expectedResult' => true
            ],
        ];
    }
}
