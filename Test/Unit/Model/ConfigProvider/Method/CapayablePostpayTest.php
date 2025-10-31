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
use Buckaroo\Magento2\Model\ConfigProvider\Method\CapayableIn3;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;
use Buckaroo\Magento2\Model\ConfigProvider\Method\CapayablePostpay;
use Buckaroo\Magento2\Test\BaseTest;

class CapayablePostpayTest extends BaseTest
{
    protected $instanceClass = CapayablePostpay::class;

    public function testIsInactive()
    {
        $scopeConfigMock = $this->getMockBuilder(ScopeConfigInterface::class)->getMock();
        $scopeConfigMock->method('getValue')
            ->with(
                $this->getPaymentMethodConfigPath(CapayablePostpay::CODE, AbstractConfigProvider::ACTIVE)
            )
            ->willReturn(0);

        $instance = $this->getInstance(['scopeConfig' => $scopeConfigMock]);
        $result = $instance->getConfig();

        $this->assertEquals([], $result);
    }

    public function testGetConfig()
    {
        $scopeConfigMock = $this->getFakeMock(ScopeConfigInterface::class)
            ->onlyMethods(['getValue'])
            ->getMockForAbstractClass();
        $scopeConfigMock->method('getValue')
            ->willReturnCallback(function($path, $scope = null, $scopeId = null) {
                // Use parameters to avoid PHPMD warnings
                unset($scope, $scopeId);

                // Check the actual path being requested and return appropriate values
                if (strpos($path, 'active') !== false) {
                    return true;
                } elseif (strpos($path, 'allowed_currencies') !== false) {
                    return 'EUR';
                } elseif (strpos($path, 'payment_fee_label') !== false) {
                    return 'Capayable Postpay Fee';
                } elseif (strpos($path, 'order_email') !== false) {
                    return '1';
                } else {
                    return null; // Default return for any other config paths
                }
            });

        $instance = $this->getInstance(['scopeConfig' => $scopeConfigMock]);
        $result = $instance->getConfig();

        $this->assertArrayHasKey('payment', $result);
        $this->assertArrayHasKey('buckaroo', $result['payment']);
        $this->assertArrayHasKey('buckaroo_magento2_capayablepostpay', $result['payment']['buckaroo']);
        $this->assertArrayHasKey('allowedCurrencies', $result['payment']['buckaroo']['buckaroo_magento2_capayablepostpay']);
    }

    /**
     * @return array
     */
    public static function getPaymentFeeProvider()
    {
        return [
            'null value' => [
                null,
                0
            ],
            'empty value' => [
                '',
                0
            ],
            'no fee' => [
                0.00,
                0
            ],
            'with fee' => [
                1.23,
                1.23
            ],
        ];
    }

    /**
     * @param $fee
     * @param $expected
     *
     * @dataProvider getPaymentFeeProvider
     */
    public function testGetPaymentFee($fee, $expected)
    {
        $scopeConfigMock = $this->getMockBuilder(ScopeConfigInterface::class)->getMock();
        $scopeConfigMock->method('getValue')
            ->with(
                $this->getPaymentMethodConfigPath(CapayablePostpay::CODE, AbstractConfigProvider::PAYMENT_FEE),
                ScopeInterface::SCOPE_STORE
            )
            ->willReturn($fee);

        $instance = $this->getInstance(['scopeConfig' => $scopeConfigMock]);
        $result = $instance->getPaymentFee();

        $this->assertEquals($expected, $result);
    }
}
