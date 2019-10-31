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
namespace TIG\Buckaroo\Test\Unit\Model\ConfigProvider\Method;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;
use TIG\Buckaroo\Model\ConfigProvider\Method\Transfer;

class TransferTest extends \TIG\Buckaroo\Test\BaseTest
{
    protected $instanceClass = Transfer::class;

    /**
     * Helper function to set the return value fromt the getValue method.
     *
     * @param $value
     *
     * @return \PHPUnit_Framework_MockObject_MockObject
     */
    protected function paymentFeeConfig($value)
    {
        $scopeConfigMock = $this->getFakeMock(ScopeConfigInterface::class)
            ->setMethods(['getValue'])
            ->getMockForAbstractClass();
        $scopeConfigMock->expects($this->once())
            ->method('getValue')
            ->with(Transfer::XPATH_TRANSFER_PAYMENT_FEE, ScopeInterface::SCOPE_STORE)
            ->willReturn($value);

        return $scopeConfigMock;
    }

    /**
     * Test what happens when the payment method is disabled.
     */
    public function testInactive()
    {
        $scopeConfigMock = $this->getFakeMock(ScopeConfigInterface::class)
            ->setMethods(['getValue'])
            ->getMockForAbstractClass();
        $scopeConfigMock->expects($this->once())
            ->method('getValue')
            ->with(Transfer::XPATH_TRANSFER_ACTIVE, ScopeInterface::SCOPE_STORE)
            ->willReturn(false);

        $instance = $this->getInstance(['scopeConfig' => $scopeConfigMock]);
        $result = $instance->getConfig();

        $this->assertEquals([], $result);
    }

    /**
     * Test that the config returns the right values
     */
    public function testGetConfig()
    {
        $sendEmail = '1';

        $scopeConfigMock = $this->getFakeMock(ScopeConfigInterface::class)
            ->setMethods(['getValue'])
            ->getMockForAbstractClass();
        $scopeConfigMock->expects($this->exactly(3))
            ->method('getValue')
            ->withConsecutive(
                [Transfer::XPATH_TRANSFER_ACTIVE, ScopeInterface::SCOPE_STORE],
                [Transfer::XPATH_TRANSFER_SEND_EMAIL, ScopeInterface::SCOPE_STORE],
                [Transfer::XPATH_ALLOWED_CURRENCIES, ScopeInterface::SCOPE_STORE, null]
            )
            ->willReturnOnConsecutiveCalls(true, $sendEmail, 'EUR,USD');

        $instance = $this->getInstance(['scopeConfig' => $scopeConfigMock]);
        $result = $instance->getConfig();

        $this->assertArrayHasKey('payment', $result);
        $this->assertArrayHasKey('buckaroo', $result['payment']);
        $this->assertArrayHasKey('transfer', $result['payment']['buckaroo']);
        $this->assertArrayHasKey('sendEmail', $result['payment']['buckaroo']['transfer']);
        $this->assertEquals($sendEmail, $result['payment']['buckaroo']['transfer']['sendEmail']);
    }

    /**
     * @return array
     */
    public function getSendMailProvider()
    {
        return [
            'Do not send mail' => [
                '0',
                'false'
            ],
            'Send mail' => [
                '1',
                'true'
            ]
        ];
    }

    /**
     * @param $value
     * @param $expected
     *
     * @dataProvider getSendMailProvider
     */
    public function testGetSendMail($value, $expected)
    {
        $scopeConfigMock = $this->getMockBuilder(ScopeConfigInterface::class)->getMock();
        $scopeConfigMock->expects($this->once())
            ->method('getValue')
            ->with(Transfer::XPATH_TRANSFER_SEND_EMAIL, ScopeInterface::SCOPE_STORE)
            ->willReturn($value);

        $instance = $this->getInstance(['scopeConfig' => $scopeConfigMock]);
        $result = $instance->getSendEmail();

        $this->assertEquals($expected, $result);
    }

    /**
     * Test what is returned by the getPaymentFee method with a value of 10
     */
    public function testGetPaymentFee()
    {
        $value = '10';
        $scopeConfigMock = $this->paymentFeeConfig($value);

        $instance = $this->getInstance(['scopeConfig' => $scopeConfigMock]);
        $result = $instance->getPaymentFee();

        $this->assertEquals($value, $result);
    }

    /**
     * Test what is returned by the getPaymentFee when not set
     */
    public function testGetPaymentFeeNull()
    {
        $value = null;
        $scopeConfigMock = $this->paymentFeeConfig($value);

        $instance = $this->getInstance(['scopeConfig' => $scopeConfigMock]);
        $result = $instance->getPaymentFee();

        $this->assertFalse($result);
    }

    /**
     * Test what is returned by the getPaymentFee method when it is negative
     */
    public function testGetPaymentFeeNegative()
    {
        $value = '-10';
        $scopeConfigMock = $this->paymentFeeConfig($value);

        $instance = $this->getInstance(['scopeConfig' => $scopeConfigMock]);
        $result = $instance->getPaymentFee();

        $this->assertEquals($value, $result);
    }

    /**
     * Test what is returned by the getPaymentFee method when the config value is empty
     */
    public function testGetPaymentFeeEmpty()
    {
        $value = '';
        $scopeConfigMock = $this->paymentFeeConfig($value);

        $instance = $this->getInstance(['scopeConfig' => $scopeConfigMock]);
        $result = $instance->getPaymentFee();

        $this->assertFalse($result);
    }

    /**
     * Test if the getActive magic method returns the correct value.
     */
    public function testGetActive()
    {
        $scopeConfigMock = $this->getMockBuilder(ScopeConfigInterface::class)->getMock();
        $scopeConfigMock->expects($this->once())
            ->method('getValue')
            ->with(Transfer::XPATH_TRANSFER_ACTIVE, ScopeInterface::SCOPE_STORE, null)
            ->willReturn('1');

        $instance = $this->getInstance(['scopeConfig' => $scopeConfigMock]);
        $result = $instance->getActive();

        $this->assertEquals(1, $result);
    }
}
