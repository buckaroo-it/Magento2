<?php

namespace Buckaroo\Magento2\Test\Unit\Gateway\Validator;

use Buckaroo\Magento2\Gateway\Validator\IssuerValidator;
use Buckaroo\Magento2\Model\ConfigProvider\Method\AbstractConfigProvider;
use Buckaroo\Magento2\Model\ConfigProvider\Factory as ConfigProviderMethodFactory;
use Magento\Framework\App\Request\Http as HttpRequest;
use Magento\Payment\Gateway\Data\PaymentDataObjectInterface;
use Magento\Payment\Gateway\Validator\ResultInterface;
use Magento\Payment\Gateway\Validator\ResultInterfaceFactory;
use Magento\Payment\Model\InfoInterface;
use Magento\Payment\Model\MethodInterface;
use PHPUnit\Framework\TestCase;
use Magento\Framework\Serialize\Serializer\Json;

class IssuerValidatorTest extends TestCase
{
    /**
     * @var IssuerValidator
     */
    private IssuerValidator $validator;

    /**
     * @var ResultInterfaceFactory|MockObject
     */
    private $resultFactoryMock;

    /**
     * @var ConfigProviderMethodFactory|MockObject
     */
    private $configProviderFactory;

    protected function setUp(): void
    {
        $this->resultFactoryMock = $this->createMock(ResultInterfaceFactory::class);

        $this->configProviderFactory = $this->createMock(ConfigProviderMethodFactory::class);

        $httpRequest = $this->createMock(HttpRequest::class);

        $jsonSerializer = $this->createMock(Json::class);

        $this->validator = new IssuerValidator(
            $this->resultFactoryMock,
            $this->configProviderFactory,
            $httpRequest,
            $jsonSerializer
        );
    }

    /**
     * @dataProvider issuerValidatorDataProvider
     */
    public function testValidate($chosenIssuer, $paymentMethodCode, $isValid, $failMessage)
    {
        $paymentDataObjectInterface = $this->createMock(PaymentDataObjectInterface::class);
        $infoInterface = $this->getMockBuilder(\Magento\Payment\Model\Info::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getAdditionalInformation', 'getMethodInstance'])
            ->getMock();
        $infoInterface->method('getAdditionalInformation')
            ->willReturnMap([['issuer', $chosenIssuer]]);
        $methodInstanceMock = $this->createMock(\Magento\Payment\Model\MethodInterface::class);
        $methodInstanceMock->method('getCode')->willReturn($paymentMethodCode);
        $infoInterface->method('getMethodInstance')->willReturn($methodInstanceMock);
        $paymentDataObjectInterface->method('getPayment')->willReturn($infoInterface);

        $abstractConfigProvider = $this->createMock(AbstractConfigProvider::class);

        $abstractConfigProvider->expects($this->atMost(1))
            ->method('getIssuers')
            ->willReturn($this->getIssuers());

        $this->configProviderFactory->expects($this->atMost(1))
            ->method('get')
            ->willReturn($abstractConfigProvider);

        $validationSubject = ['payment' => $paymentDataObjectInterface];

        $resultArray = ['isValid' => $isValid, 'failsDescription' => [], 'errorCodes' => []];
        if (!empty($failMessage)) {
            $resultArray['failsDescription'] = [$failMessage];
        }

        $expectedResultObj = $this->createMock(ResultInterface::class);
        $this->resultFactoryMock->method('create')
            ->with($resultArray)
            ->willReturn($expectedResultObj);

        $result = $this->validator->validate($validationSubject);

        $this->assertSame($expectedResultObj, $result);
    }

    public static function issuerValidatorDataProvider(): array
    {
        return [
            'valid' => [
                'chosenIssuer' => 'ABNANL2A',
                'paymentMethodCode' => 'buckaroo_magento2_ideal',
                'isValid' => true,
                'failMessage' => ''
            ],
            'valid skip validation boolean' => [
                'chosenIssuer' => 'INGBNL2A',
                'paymentMethodCode' => 'buckaroo_magento2_ideal',
                'isValid' => true,
                'failMessage' => ''
            ],
            'valid skipValidation' => [
                'chosenIssuer' => 'ABNANL2A',
                'paymentMethodCode' => 'buckaroo_magento2_ideal',
                'isValid' => true,
                'failMessage' => ''
            ],
            'invalid wrong issuer code' => [
                'chosenIssuer' => 'WRONG',
                'paymentMethodCode' => 'buckaroo_magento2_ideal',
                'isValid' => false,
                'failMessage' => __('Please select a issuer from the list')
            ],
            'invalid null issuer code' => [
                'chosenIssuer' => null,
                'paymentMethodCode' => 'buckaroo_magento2_ideal',
                'isValid' => false,
                'failMessage' => __('Please select a issuer from the list')
            ],
        ];
    }

    private function getIssuers(): array
    {
        return  [
            [
                'name' => 'ABN AMRO',
                'code' => 'ABNANL2A',
            ],
            [
                'name' => 'ASN Bank',
                'code' => 'ASNBNL21',
            ],
            [
                'name' => 'Bunq Bank',
                'code' => 'BUNQNL2A',
            ],
            [
                'name' => 'ING',
                'code' => 'INGBNL2A',
            ],
            [
                'name' => 'Knab Bank',
                'code' => 'KNABNL2H',
            ],
            [
                'name' => 'Rabobank',
                'code' => 'RABONL2U',
            ],
            [
                'name' => 'RegioBank',
                'code' => 'RBRBNL21',
            ],
            [
                'name' => 'SNS Bank',
                'code' => 'SNSBNL2A',
            ],
            [
                'name' => 'Triodos Bank',
                'code' => 'TRIONL2U',
            ],
            [
                'name' => 'Van Lanschot',
                'code' => 'FVLBNL22',
            ],
            [
                'name' => 'Revolut',
                'code' => 'REVOLT21',
            ],
        ];
    }
}
