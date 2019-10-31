<?php
/**
 * NOTICE OF LICENSE
 *
 * This source file is subject to the MIT License
 * It is available through the world-wide-web at this URL:
 * https://tldrlegal.com/license/mit-license
 * If you are unable to obtain it through the world-wide-web, please send an email
 * to support@buckaroo.nl so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade this module to newer
 * versions in the future. If you wish to customize this module for your
 * needs please contact support@buckaroo.nl for more information.
 *
 * @copyright Copyright (c) Buckaroo B.V.
 * @license   https://tldrlegal.com/license/mit-license
 */
namespace TIG\Buckaroo\Test\Unit\Model\Method;

use Magento\Framework\DataObject;
use Magento\Framework\ObjectManagerInterface;
use Magento\Payment\Model\InfoInterface;
use Magento\Quote\Model\Quote\Payment as QuotePayment;
use Magento\Sales\Model\Order\Payment;
use TIG\Buckaroo\Model\ConfigProvider\Method\Emandate as EmandateConfig;
use TIG\Buckaroo\Model\Method\Emandate;
use TIG\Buckaroo\Test\BaseTest;

class EmandateTest extends BaseTest
{
    protected $instanceClass = Emandate::class;

    /**
     * @return array
     */
    public function assignDataProvider()
    {
        return [
            'no data' => [
                [],
                [
                    'buckaroo_skip_validation' => null,
                ]
            ],
            'with skip validation data' => [
                [
                    'additional_data' => [
                        'buckaroo_skip_validation' => '1',
                    ]
                ],
                [
                    'buckaroo_skip_validation' => '1'
                ]
            ],
            'with form data' => [
                [
                    'additional_data' => [
                        'issuer' => 'INGBNL2A'
                    ]
                ],
                [
                    'buckaroo_skip_validation' => null,
                    'issuer' => 'INGBNL2A'
                ]
            ],
        ];
    }

    /**
     * @param $data
     * @param $expected
     *
     * @dataProvider assignDataProvider
     * @throws \Exception
     */
    public function testAssignData($data, $expected)
    {
        $dataObject = $this->getObject(DataObject::class);
        $dataObject->addData($data);

        $instance = $this->getInstance();

        $infoInstanceMock = $this->getFakeMock(QuotePayment::class)->setMethods(null)->getMock();
        $instance->setData('info_instance', $infoInstanceMock);

        $result = $instance->assignData($dataObject);
        $this->assertInstanceOf(Emandate::class, $result);

        $tst = $infoInstanceMock->getAdditionalInformation();
        $this->assertEquals($expected, $tst);
    }

    public function testGetCaptureTransactionBuilder()
    {
        $infoInstanceMock = $this->getFakeMock(Payment::class)->getMock();
        $instance = $this->getInstance();

        $result = $instance->getCaptureTransactionBuilder($infoInstanceMock);
        $this->assertFalse($result);
    }

    public function testGetAuthorizeTransactionBuilder()
    {
        $infoInstanceMock = $this->getFakeMock(Payment::class)->getMock();
        $instance = $this->getInstance();

        $result = $instance->getAuthorizeTransactionBuilder($infoInstanceMock);
        $this->assertFalse($result);
    }

    public function testGetRefundTransactionBuilder()
    {
        $infoInstanceMock = $this->getFakeMock(Payment::class)->getMock();
        $instance = $this->getInstance();

        $result = $instance->getRefundTransactionBuilder($infoInstanceMock);
        $this->assertFalse($result);
    }

    public function testGetVoidTransactionBuilder()
    {
        $infoInstanceMock = $this->getFakeMock(Payment::class)->getMock();
        $instance = $this->getInstance();

        $result = $instance->getVoidTransactionBuilder($infoInstanceMock);
        $this->assertTrue($result);
    }

    public function testValidate()
    {
        $paymentInfoMock = $this->getFakeMock(InfoInterface::class)
            ->setMethods(['getQuote', 'getBillingAddress', 'getCountryId', 'getAdditionalInformation'])
            ->getMockForAbstractClass();
        $paymentInfoMock->expects($this->once())->method('getQuote')->willReturnSelf();
        $paymentInfoMock->expects($this->once())->method('getBillingAddress')->willReturnSelf();
        $paymentInfoMock->expects($this->once())->method('getCountryId')->willReturn(4);
        $paymentInfoMock->expects($this->exactly(2))->method('getAdditionalInformation')
            ->withConsecutive(['buckaroo_skip_validation'], ['issuer'])
            ->willReturnOnConsecutiveCalls(false, 'NLRABO');

        $emandateConfigMock = $this->getFakeMock(EmandateConfig::class)->setMethods(['getIssuers'])->getMock();
        $emandateConfigMock->expects($this->once())->method('getIssuers')->willReturn([['code' => 'NLRABO']]);

        $instance = $this->getInstance(['emandateConfig' => $emandateConfigMock]);
        $instance->setData('info_instance', $paymentInfoMock);

        $result = $instance->validate();
        $this->assertInstanceOf(Emandate::class, $result);
    }
}
