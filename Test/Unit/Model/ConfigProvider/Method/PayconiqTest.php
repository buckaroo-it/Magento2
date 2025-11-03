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
use Magento\Framework\Data\Form\FormKey;
use Magento\Store\Model\ScopeInterface;
use Buckaroo\Magento2\Model\ConfigProvider\Method\Payconiq;
use Buckaroo\Magento2\Test\BaseTest;

class PayconiqTest extends BaseTest
{
    protected $instanceClass = Payconiq::class;

    public function testGetConfig()
    {
        $scopeConfigMock = $this->getMockBuilder(ScopeConfigInterface::class)->getMock();
        $scopeConfigMock->method('getValue')
            ->willReturnCallback(function ($path, $scope = null, $scopeId = null) {
                // Use parameters to avoid PHPMD warnings
                unset($scope, $scopeId);

                if (strpos($path, AbstractConfigProvider::ACTIVE) !== false) {
                    return 1;
                }
                return null;
            });

        $formKeyMock = $this->getFakeMock(FormKey::class)->onlyMethods(['getFormKey'])->getMock();
        $formKeyMock->method('getFormKey')->willReturn('123abc');

        $instance = $this->getInstance(['scopeConfig' => $scopeConfigMock, 'formKey' => $formKeyMock]);
        $result = $instance->getConfig();

        $this->assertIsArray($result);

        $resultPaymentBuckaroo = $result['payment']['buckaroo'];

        $this->assertCount(1, $resultPaymentBuckaroo);
        $this->assertArrayHasKey('buckaroo_magento2_payconiq', $resultPaymentBuckaroo);
        $this->assertArrayHasKey('paymentFeeLabel', $resultPaymentBuckaroo['buckaroo_magento2_payconiq']);
        $this->assertArrayHasKey('allowedCurrencies', $resultPaymentBuckaroo['buckaroo_magento2_payconiq']);
        $this->assertEquals(
            Payconiq::PAYCONIC_REDIRECT_URL . '?form_key=123abc',
            $resultPaymentBuckaroo['buckaroo_magento2_payconiq']['redirecturl']
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
                $this->getPaymentMethodConfigPath(Payconiq::CODE, AbstractConfigProvider::PAYMENT_FEE),
                ScopeInterface::SCOPE_STORE
            )
            ->willReturn($fee);

        $instance = $this->getInstance(['scopeConfig' => $scopeConfigMock]);
        $result = $instance->getPaymentFee();

        $this->assertEquals($expected, $result);
    }
}
