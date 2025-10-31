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
use Buckaroo\Magento2\Model\ConfigProvider\Method\CapayableIn3;
use Buckaroo\Magento2\Test\BaseTest;

class CapayableIn3Test extends BaseTest
{
    protected $instanceClass = CapayableIn3::class;

    public function testIsInactive()
    {
        $scopeConfigMock = $this->getMockBuilder(ScopeConfigInterface::class)->getMock();
        $scopeConfigMock->method('getValue')
            ->with(
                $this->getPaymentMethodConfigPath(CapayableIn3::CODE, AbstractConfigProvider::ACTIVE)
            )
            ->willReturn(0);

        $instance = $this->getInstance(['scopeConfig' => $scopeConfigMock]);
        $result = $instance->getConfig();

        $this->assertEquals([], $result);
    }

    public function testGetConfig(): void
    {
        $scopeConfigMock = $this->createMock(ScopeConfigInterface::class);

        // PHPUnit 10: use a value map instead of withConsecutive()
        $valueMap = [
            [
                $this->getPaymentMethodConfigPath(CapayableIn3::CODE, AbstractConfigProvider::ACTIVE),
                ScopeInterface::SCOPE_STORE,
                null,
                true
            ],
            [
                $this->getPaymentMethodConfigPath(
                    CapayableIn3::CODE,
                    AbstractConfigProvider::ALLOWED_CURRENCIES
                ),
                ScopeInterface::SCOPE_STORE,
                null,
                'EUR'
            ],
        ];

        $scopeConfigMock->method('getValue')
            ->willReturnMap($valueMap);

        $instance = $this->getInstance(['scopeConfig' => $scopeConfigMock]);
        $result = $instance->getConfig();

        $this->assertArrayHasKey('payment', $result);
        $this->assertArrayHasKey('buckaroo', $result['payment']);
        $this->assertArrayHasKey(CapayableIn3::CODE, $result['payment']['buckaroo']);
        $this->assertArrayHasKey(
            'allowedCurrencies',
            $result['payment']['buckaroo'][CapayableIn3::CODE]
        );
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
                $this->getPaymentMethodConfigPath(CapayableIn3::CODE, AbstractConfigProvider::PAYMENT_FEE),
                ScopeInterface::SCOPE_STORE
            )->willReturn($fee);

        $instance = $this->getInstance(['scopeConfig' => $scopeConfigMock]);
        $result = $instance->getPaymentFee();

        $this->assertEquals($expected, $result);
    }
}
