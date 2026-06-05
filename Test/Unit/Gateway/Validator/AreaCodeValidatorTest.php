<?php

namespace Buckaroo\Magento2\Test\Unit\Gateway\Validator;

use Buckaroo\Magento2\Gateway\Validator\AreaCodeValidator;
use Buckaroo\Magento2\Model\ConfigProvider\Method\Factory as MethodConfigProviderFactory;
use Buckaroo\Magento2\Model\ConfigProvider\Method\ConfigProviderInterface;
use Magento\Framework\App\Area;
use Magento\Framework\App\State;
use Magento\Payment\Gateway\Validator\ResultInterface;
use Magento\Payment\Gateway\Validator\ResultInterfaceFactory;
use Magento\Payment\Model\MethodInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class AreaCodeValidatorTest extends TestCase
{
    /** @var ResultInterfaceFactory|MockObject */
    private $resultFactory;

    /** @var State|MockObject */
    private $state;

    /** @var MethodConfigProviderFactory|MockObject */
    private $methodConfigProviderFactory;

    /** @var AreaCodeValidator */
    private $validator;

    protected function setUp(): void
    {
        $this->resultFactory = $this->getMockBuilder(ResultInterfaceFactory::class)
            ->onlyMethods(['create'])
            ->disableOriginalConstructor()
            ->getMock();

        $this->state = $this->createMock(State::class);

        $this->methodConfigProviderFactory = $this->createMock(MethodConfigProviderFactory::class);

        $this->validator = new AreaCodeValidator(
            $this->resultFactory,
            $this->state,
            $this->methodConfigProviderFactory
        );
    }

    /**
     * @dataProvider validateDataProvider
     */
    public function testValidate(
        string $areaCode,
        ?string $availableInBackend,
        bool $hasProvider,
        ?bool $isVisibleForAreaCode,
        bool $expectedResult
    ): void {
        $method = $this->createMock(MethodInterface::class);
        $method->method('getCode')->willReturn('buckaroo_magento2_testmethod');
        $method->method('getConfigData')
            ->with('available_in_backend')
            ->willReturn($availableInBackend);

        $this->state->method('getAreaCode')->willReturn($areaCode);

        $this->methodConfigProviderFactory->method('has')->willReturn($hasProvider);

        if ($hasProvider && $isVisibleForAreaCode !== null) {
            $cp = $this->createMock(ConfigProviderInterface::class);
            $cp->method('isVisibleForAreaCode')->willReturn($isVisibleForAreaCode);
            $this->methodConfigProviderFactory->method('get')->willReturn($cp);
        }

        $resultMock = $this->createMock(ResultInterface::class);
        $this->resultFactory->method('create')
            ->with(['isValid' => $expectedResult, 'failsDescription' => [], 'errorCodes' => []])
            ->willReturn($resultMock);

        $result = $this->validator->validate(['paymentMethodInstance' => $method]);

        $this->assertSame($resultMock, $result);
    }

    public static function validateDataProvider(): array
    {
        return [
            'admin, available_in_backend=0 → blocked' => [
                Area::AREA_ADMINHTML, '0', false, null, false,
            ],
            'admin, available_in_backend=1 → allowed' => [
                Area::AREA_ADMINHTML, '1', false, null, true,
            ],
            'admin, no available_in_backend config → allowed' => [
                Area::AREA_ADMINHTML, null, false, null, true,
            ],
            'frontend, available_in_backend=0 → allowed (flag only applies to admin)' => [
                Area::AREA_FRONTEND, '0', false, null, true,
            ],
            'frontend, no provider → allowed' => [
                Area::AREA_FRONTEND, null, false, null, true,
            ],
            'frontend, provider returns visible=true → allowed' => [
                Area::AREA_FRONTEND, null, true, true, true,
            ],
            'frontend, provider returns visible=false → blocked' => [
                Area::AREA_FRONTEND, null, true, false, false,
            ],
            'admin, provider returns visible=true → allowed' => [
                Area::AREA_ADMINHTML, null, true, true, true,
            ],
            'admin, provider returns visible=false → blocked' => [
                Area::AREA_ADMINHTML, null, true, false, false,
            ],
        ];
    }

    public function testAreaCodeExceptionAllowsPayment(): void
    {
        $method = $this->createMock(MethodInterface::class);
        $this->state->method('getAreaCode')->willThrowException(new \Exception('Area not set'));

        $resultMock = $this->createMock(ResultInterface::class);
        $this->resultFactory->method('create')
            ->with(['isValid' => true, 'failsDescription' => [], 'errorCodes' => []])
            ->willReturn($resultMock);

        $result = $this->validator->validate(['paymentMethodInstance' => $method]);

        $this->assertSame($resultMock, $result);
    }
}
