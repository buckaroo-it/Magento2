<?php

namespace Buckaroo\Magento2\Test\Unit\Gateway\Validator;

use Buckaroo\Magento2\Gateway\Validator\AvailableBasedOnIPValidator;
use Buckaroo\Magento2\Model\ConfigProvider\Account as AccountConfig;
use Buckaroo\Magento2\Model\ConfigProvider\Factory as ConfigProviderMethodFactory;
use Magento\Payment\Gateway\Validator\ResultInterface;
use Magento\Payment\Gateway\Validator\ResultInterfaceFactory;
use Magento\Payment\Model\MethodInterface;
use PHPUnit\Framework\TestCase;

class AvailableBasedOnIPValidatorTest extends TestCase
{
    /**
     * @var ResultInterfaceFactory|\PHPUnit\Framework\MockObject\MockObject
     */
    private $resultFactoryMock;

    /**
     * @var AccountConfig|\PHPUnit\Framework\MockObject\MockObject
     */
    private $accountConfigMock;

    /**
     * @var ConfigProviderMethodFactory|\PHPUnit\Framework\MockObject\MockObject
     */
    private $configProviderMethodFactoryMock;

    /**
     * @var \Magento\Developer\Helper\Data|\PHPUnit\Framework\MockObject\MockObject
     */
    private $developmentHelperMock;

    /**
     * @var AvailableBasedOnIPValidator
     */
    private $validator;

    protected function setUp(): void
    {
        $this->resultFactoryMock = $this->getMockBuilder(ResultInterfaceFactory::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->accountConfigMock = $this->getMockBuilder(AccountConfig::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->configProviderMethodFactoryMock = $this->getMockBuilder(ConfigProviderMethodFactory::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->developmentHelperMock = $this->getMockBuilder(\Magento\Developer\Helper\Data::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->validator = new AvailableBasedOnIPValidator(
            $this->resultFactoryMock,
            $this->accountConfigMock,
            $this->configProviderMethodFactoryMock,
            $this->developmentHelperMock
        );
    }

    /**
     * @dataProvider availableBasedOnIPValidationDataProvider
     */
    public function testValidate($limitByIp, $configLimitByIp, $storeId, $isDevAllowed, $expectedResult)
    {
        $quoteMock = $this->getMockBuilder(\Magento\Quote\Model\Quote::class)
            ->disableOriginalConstructor()
            ->getMock();
        $quoteMock->expects($this->atMost(1))
            ->method('getStoreId')
            ->willReturn($storeId);

        $validationSubject = [
            'paymentMethodInstance' => $this->getMockBuilder(MethodInterface::class)
                ->disableOriginalConstructor()
                ->getMock(),
            'quote' => $quoteMock,
        ];

        $this->accountConfigMock->expects($this->once())
            ->method('getLimitByIp')
            ->willReturn($limitByIp);

        $validationSubject['paymentMethodInstance']->expects($this->atMost(1))
            ->method('getConfigData')
            ->with('limit_by_ip')
            ->willReturn($configLimitByIp);

        $this->developmentHelperMock->expects($this->atMost(1))
            ->method('isDevAllowed')
            ->with($storeId)
            ->willReturn($isDevAllowed);

        $expectedResultObj = $this->createMock(ResultInterface::class);
        $this->resultFactoryMock->expects($this->once())
            ->method('create')
            ->with(['isValid' => $expectedResult, 'failsDescription' => [], 'errorCodes' => []])
            ->willReturn($expectedResultObj);

        $result = $this->validator->validate($validationSubject);
        $this->assertSame($expectedResultObj, $result);
    }

    public function availableBasedOnIPValidationDataProvider()
    {
        return [
            'limitByIp=0, config=0, isDevAllowed=1' => [
                'limitByIp' => false,
                'configLimitByIp' => '0',
                'storeId' => 1,
                'isDevAllowed' => true,
                'expectedResult' => true,
            ],
            'limitByIp=0, config=1, isDevAllowed=1' => [
                'limitByIp' => false,
                'configLimitByIp' => '1',
                'storeId' => 1,
                'isDevAllowed' => true,
                'expectedResult' => true,
            ],
            'limitByIp=1, config=0, isDevAllowed=1' => [
                'limitByIp' => true,
                'configLimitByIp' => '0',
                'storeId' => 1,
                'isDevAllowed' => true,
                'expectedResult' => true,
            ],
            'limitByIp=1, config=1, isDevAllowed=1' => [
                'limitByIp' => true,
                'configLimitByIp' => '1',
                'storeId' => 1,
                'isDevAllowed' => true,
                'expectedResult' => true,
            ],
            'limitByIp=0, config=1, store=0, isDevAllowed=0' => [
                'limitByIp' => false,
                'configLimitByIp' => '1',
                'storeId' => 0,
                'isDevAllowed' => false,
                'expectedResult' => false,
            ],
        ];
    }
}
