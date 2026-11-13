<?php

/**
 * Buckaroo Magento 2 payment module (https://www.buckaroo.eu/)
 *
 * Copyright (c) Buckaroo B.V.
 * See LICENSE for license details.
 *
 * Support: support@buckaroo.nl
 *
 * @copyright Copyright (c) Buckaroo B.V.
 * @license   MIT
 */

namespace Buckaroo\Magento2\Test\Unit\Model\ConfigProvider\Method;


use PHPUnit\Framework\Attributes\DataProvider;
use Buckaroo\Magento2\Model\ConfigProvider\Method\AbstractConfigProvider;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;
use Buckaroo\Magento2\Model\ConfigProvider\Method\Transfer;

class TransferTest extends \Buckaroo\Magento2\Test\BaseTest
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
            ->getMock();
        $scopeConfigMock->method('getValue')
            ->willReturnCallback(function ($path, $scope = null, $scopeId = null) use ($value) {
                // Use parameters to avoid PHPMD warnings
                unset($scope, $scopeId);

                if (strpos($path, 'payment_fee') !== false) {
                    return $value;
                }
                return null;
            });

        return $scopeConfigMock;
    }

    /**
     * Test what happens when the payment method is disabled.
     */
    public function testInactive()
    {
        $scopeConfigMock = $this->getFakeMock(ScopeConfigInterface::class)
            ->getMock();
        $scopeConfigMock->method('getValue')
            ->with(
                $this->getPaymentMethodConfigPath(Transfer::CODE, AbstractConfigProvider::ACTIVE),
                ScopeInterface::SCOPE_STORE
            )
            ->willReturn(false);

        $instance = $this->getInstance(['scopeConfig' => $scopeConfigMock]);
        $result = $instance->getConfig();

        $this->assertEquals([], $result);
    }

    /**
     * The checkout notice follows the payment instruction email setting, not the order confirmation email.
     */
    #[DataProvider('getSendMailProvider')]
    public function testGetConfigSendEmailFollowsPaymentInstructionEmail($value, $expected)
    {
        $scopeConfigMock = $this->getFakeMock(ScopeConfigInterface::class)
            ->getMock();
        $scopeConfigMock->method('getValue')->willReturnCallback(function ($path = null) use ($value) {
            if (strpos($path, '/active') !== false) {
                return '1';
            }
            if ($path === 'payment/buckaroo_magento2_transfer/payment_instruction_email') {
                return $value;
            }
            if ($path === 'payment/buckaroo_magento2_transfer/order_email') {
                return $value === '1' ? '0' : '1';
            }
            return null;
        });

        $instance = $this->getInstance(['scopeConfig' => $scopeConfigMock]);
        $result = $instance->getConfig();

        $this->assertArrayHasKey('payment', $result);
        $this->assertArrayHasKey('buckaroo', $result['payment']);
        $this->assertArrayHasKey('buckaroo_magento2_transfer', $result['payment']['buckaroo']);
        $this->assertArrayHasKey('sendEmail', $result['payment']['buckaroo']['buckaroo_magento2_transfer']);
        $this->assertSame(
            $expected === 'true',
            $result['payment']['buckaroo']['buckaroo_magento2_transfer']['sendEmail']
        );
    }

    /**
     * @param $value
     * @param $expected
     */
    #[DataProvider('getSendMailProvider')]
    public function testHasPaymentInstructionEmail($value, $expected)
    {
        $scopeConfigMock = $this->getMockBuilder(ScopeConfigInterface::class)->getMock();
        $scopeConfigMock->method('getValue')
            ->with(
                $this->getPaymentMethodConfigPath(Transfer::CODE, Transfer::XPATH_TRANSFER_PAYMENT_INSTRUCTION_EMAIL),
                ScopeInterface::SCOPE_STORE
            )
            ->willReturn($value);

        $instance = $this->getInstance(['scopeConfig' => $scopeConfigMock]);

        $this->assertSame($expected === 'true', $instance->hasPaymentInstructionEmail());
    }

    /**
     * @return array
     */
    public static function getSendMailProvider()
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
     */
    #[DataProvider('getSendMailProvider')]
    public function testHasSendMail($value, $expected)
    {
        $scopeConfigMock = $this->getMockBuilder(ScopeConfigInterface::class)->getMock();
        $scopeConfigMock->method('getValue')
            ->with(
                $this->getPaymentMethodConfigPath(Transfer::CODE, AbstractConfigProvider::ORDER_EMAIL),
                ScopeInterface::SCOPE_STORE
            )
            ->willReturn($value);

        $instance = $this->getInstance(['scopeConfig' => $scopeConfigMock]);
        $result = $instance->hasOrderEmail();

        // Fix assertion to match actual return types: boolean instead of string
        if ($expected === 'false') {
            $this->assertFalse($result);
        } else {
            $this->assertEquals($expected, $result);
        }
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

        $this->assertEquals(0, $result);
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

        $this->assertEquals(0, $result);
    }

    /**
     * Test if the getActive magic method returns the correct value.
     */
    public function testGetActive()
    {
        $scopeConfigMock = $this->getMockBuilder(ScopeConfigInterface::class)->getMock();
        $scopeConfigMock->method('getValue')
            ->with(
                $this->getPaymentMethodConfigPath(Transfer::CODE, AbstractConfigProvider::ACTIVE),
                ScopeInterface::SCOPE_STORE,
                null
            )
            ->willReturn('1');

        $instance = $this->getInstance(['scopeConfig' => $scopeConfigMock]);
        $result = $instance->getActive();

        $this->assertEquals(1, $result);
    }
}
