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
        $this->resultFactoryMock = $this->getMockBuilder(ResultInterfaceFactory::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->configProviderFactory = $this->getMockBuilder(ConfigProviderMethodFactory::class)
            ->disableOriginalConstructor()
            ->getMock();

        $httpRequest = $this->getMockBuilder(HttpRequest::class)
            ->disableOriginalConstructor()
            ->getMock();

        $jsonSerializer = $this->getMockBuilder(Json::class)
            ->disableOriginalConstructor()
            ->getMock();

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
    public function testValidate($skipValidation, $chosenIssuer, $paymentMethodCode, $isValid, $failMessage)
    {
        $paymentDataObjectInterface = $this->getMockBuilder(PaymentDataObjectInterface::class)
            ->disableOriginalConstructor()
            ->getMock();
        $infoInterface = $this->getMockBuilder(InfoInterface::class)
            ->disableOriginalConstructor()
            ->getMock();
        $infoInterface->expects($this->atMost(2))
            ->method('getAdditionalInformation')
            ->willReturnMap([
                ['buckaroo_skip_validation', $skipValidation],
                ['issuer', $chosenIssuer]
            ]);
        $paymentDataObjectInterface->expects($this->once())
            ->method('getPayment')
            ->willReturn($infoInterface);

        $paymentMethodInstanceMock = $this->getMockBuilder(MethodInterface::class)
            ->disableOriginalConstructor()
            ->getMock();

        $paymentMethodInstanceMock->expects($this->atMost(1))
            ->method('getCode')
            ->willReturn($paymentMethodCode);

        $infoInterface->expects($this->atMost(1))
            ->method('getMethodInstance')
            ->willReturn($paymentMethodInstanceMock);

        $abstractConfigProvider = $this->getMockBuilder(AbstractConfigProvider::class)
            ->disableOriginalConstructor()
            ->getMock();

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
        $this->resultFactoryMock->expects($this->once())
            ->method('create')
            ->with($resultArray)
            ->willReturn($expectedResultObj);

        $result = $this->validator->validate($validationSubject);

        $this->assertSame($expectedResultObj, $result);
    }

    public function issuerValidatorDataProvider(): array
    {
        return [
            'valid' => [
                'skipValidation' => 0,
                'chosenIssuer' => 'ABNANL2A',
                'paymentMethodCode' => 'buckaroo_magento2_ideal',
                'isValid' => true,
                'failMessage' => ''
            ],
            'valid skip validation boolean' => [
                'skipValidation' => null,
                'chosenIssuer' => 'INGBNL2A',
                'paymentMethodCode' => 'buckaroo_magento2_ideal',
                'isValid' => true,
                'failMessage' => ''
            ],
            'valid skipValidation' => [
                'skipValidation' => 1,
                'chosenIssuer' => 'ABNANL2A',
                'paymentMethodCode' => 'buckaroo_magento2_ideal',
                'isValid' => true,
                'failMessage' => ''
            ],
            'invalid wrong issuer code' => [
                'skipValidation' => 0,
                'chosenIssuer' => 'WRONG',
                'paymentMethodCode' => 'buckaroo_magento2_ideal',
                'isValid' => false,
                'failMessage' => __('Please select a issuer from the list')
            ],
            'invalid null issuer code' => [
                'skipValidation' => null,
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
