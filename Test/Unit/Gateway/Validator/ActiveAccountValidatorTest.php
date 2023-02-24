<?php

namespace Buckaroo\Magento2\Test\Unit\Gateway\Validator;

use Buckaroo\Magento2\Gateway\Validator\ActiveAccountValidator;
use Buckaroo\Magento2\Model\ConfigProvider\Account;
use Buckaroo\Magento2\Model\ConfigProvider\Factory as ConfigProviderFactory;
use Magento\Payment\Gateway\Validator\ResultInterface;
use Magento\Payment\Gateway\Validator\ResultInterfaceFactory;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class ActiveAccountValidatorTest extends TestCase
{
    /**
     * @var ResultInterfaceFactory|MockObject
     */
    private $resultFactory;

    /**
     * @var ConfigProviderFactory|MockObject
     */
    private $configProviderFactory;

    /**
     *
     * @var ActiveAccountValidator
     */
    private $validator;

    protected function setUp(): void
    {
        $this->resultFactory = $this->createMock(ResultInterfaceFactory::class);
        $this->configProviderFactory = $this->createMock(ConfigProviderFactory::class);

        $this->validator = new ActiveAccountValidator(
            $this->resultFactory,
            $this->configProviderFactory
        );
    }

    /**
     * @dataProvider accountActiveProvider
     */
    public function testValidate($accountActive, bool $isValid)
    {
        $accountConfig = $this->createMock(Account::class);
        $accountConfig->expects($this->once())
            ->method('getActive')
            ->willReturn($accountActive);

        $this->configProviderFactory->expects($this->once())
            ->method('get')
            ->with('account')
            ->willReturn($accountConfig);

        $expectedResult = $this->createMock(ResultInterface::class);
        $this->resultFactory->expects($this->once())
            ->method('create')
            ->with(['isValid' => $isValid, 'failsDescription' => [], 'errorCodes' => []])
            ->willReturn($expectedResult);

        $result = $this->validator->validate([]);
        $this->assertSame($expectedResult, $result);
    }

    public function accountActiveProvider(): array
    {
        return [
            'account_active' => [true, true],
            'account_inactive' => [false, false],
            'account_missing' => [null, false]
        ];
    }
}
