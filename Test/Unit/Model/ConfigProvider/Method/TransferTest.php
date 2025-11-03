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

namespace Buckaroo\Magento2\Test\Unit\Model\ConfigProvider\Method;

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
            ->onlyMethods(['getValue'])
            ->getMockForAbstractClass();
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
            ->onlyMethods(['getValue'])
            ->getMockForAbstractClass();
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
     * Test that the config returns the right values
     */
    public function testGetConfig()
    {
        $sendEmail = '1';

        $scopeConfigMock = $this->getFakeMock(ScopeConfigInterface::class)
            ->onlyMethods(['getValue'])
            ->getMockForAbstractClass();
        $scopeConfigMock->method('getValue')->willReturnCallback(function ($path = null) {
            // Return active = 1 for the payment method to ensure it's enabled
            if (strpos($path, '/active') !== false) {
                return '1';
            }
            // Return sendEmail value for order_email configuration (correct path)
            if (strpos($path, 'order_email') !== false) {
                return '1';
            }
            return null;
        });

        $instance = $this->getInstance(['scopeConfig' => $scopeConfigMock]);
        $result = $instance->getConfig();

        $this->assertArrayHasKey('payment', $result);
        $this->assertArrayHasKey('buckaroo', $result['payment']);
        $this->assertArrayHasKey('buckaroo_magento2_transfer', $result['payment']['buckaroo']);
        $this->assertArrayHasKey('sendEmail', $result['payment']['buckaroo']['buckaroo_magento2_transfer']);
        $this->assertEquals($sendEmail, $result['payment']['buckaroo']['buckaroo_magento2_transfer']['sendEmail']);
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
     * @dataProvider getSendMailProvider
     */
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
