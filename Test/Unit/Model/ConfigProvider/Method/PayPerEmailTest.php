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
use Buckaroo\Magento2\Model\ConfigProvider\Method\PayPerEmail;
use Buckaroo\Magento2\Test\BaseTest;

class PayPerEmailTest extends BaseTest
{
    protected $instanceClass = PayPerEmail::class;

    public function testIsInactive()
    {
        $scopeConfigMock = $this->getMockBuilder(ScopeConfigInterface::class)->getMock();
        $scopeConfigMock->method('getValue')
            ->with(
                $this->getPaymentMethodConfigPath(PayPerEmail::CODE, AbstractConfigProvider::ACTIVE)
            )
            ->willReturn(0);

        $instance = $this->getInstance(['scopeConfig' => $scopeConfigMock]);
        $result = $instance->getConfig();

        $this->assertEquals([], $result);
    }

    public function testGetConfig()
    {
        $scopeConfigMock = $this->getMockBuilder(ScopeConfigInterface::class)->getMock();
        $scopeConfigMock->method('getValue')
            ->willReturnCallback(function($path, $scope = null, $scopeId = null) {
                if (strpos($path, AbstractConfigProvider::ACTIVE) !== false) {
                    return 1;
                }
                return null;
            });

        $instance = $this->getInstance(['scopeConfig' => $scopeConfigMock]);
        $result = $instance->getConfig();

        $this->assertIsArray($result);

        $resultPaymentBuckaroo = $result['payment']['buckaroo'];

        $this->assertArrayHasKey('buckaroo_magento2_payperemail', $resultPaymentBuckaroo);
        $this->assertArrayHasKey('genderList', $resultPaymentBuckaroo['buckaroo_magento2_payperemail']);
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
                $this->getPaymentMethodConfigPath(PayPerEmail::CODE, AbstractConfigProvider::PAYMENT_FEE),
                ScopeInterface::SCOPE_STORE
            )
            ->willReturn($fee);

        $instance = $this->getInstance(['scopeConfig' => $scopeConfigMock]);
        $result = $instance->getPaymentFee();

        $this->assertEquals($expected, $result);
    }

    /**
     * @return array
     */
    public static function getSendMailProvider()
    {
        return [
            'yes selected' => [
                '1',
                true
            ],
            'no selected' => [
                '0',
                false
            ],
        ];
    }

    /**
     * @param $option
     * @param $expected
     *
     * @dataProvider getSendMailProvider
     */
    public function testHasSendMail($option, $expected)
    {
        $scopeConfigMock = $this->getMockBuilder(ScopeConfigInterface::class)->getMock();
        $scopeConfigMock->method('getValue')
            ->with('payment/buckaroo_magento2_payperemail/send_mail', ScopeInterface::SCOPE_STORE)
            ->willReturn($option);

        $instance = $this->getInstance(['scopeConfig' => $scopeConfigMock]);
        $result = $instance->hasSendMail();

        $this->assertEquals($expected, $result);
    }

    /**
     *
     */
    public static function isVisibleForAreaCodeProvider()
    {
        return [
            "visibleFrontBack is null" => [null, null, false],
            "visibleFrontBack is frontend areacode adminhtml" => ['frontend', 'adminhtml', false],
            "visibleFrontBack is frontend areacode frontend"  => ['frontend', 'frontend', true],
            "visibleFrontBack is backend areacode adminhtml"  => ['backend', 'adminhtml', true],
            "visibleFrontBack is backend areacode frontend"   => ['backend', 'frontend', false],
            "visibleFrontBack is both areacode adminhtml"     => ['both', 'adminhtml', true],
            "visibleFrontBack is both areacode frontend"      => ['both', 'frontend', true],
        ];
    }

    /**
     * @param $visibleFrontBack
     * @param $areaCode
     * @param $expected
     *
     * @dataProvider isVisibleForAreaCodeProvider
     */
    public function testIsVisibleForAreaCode($visibleFrontBack, $areaCode, $expected)
    {
        $scopeConfigMock = $this->getMockBuilder(ScopeConfigInterface::class)->getMock();
        $scopeConfigMock->method('getValue')
            ->with('payment/buckaroo_magento2_payperemail/visible_front_back', ScopeInterface::SCOPE_STORE)
            ->willReturn($visibleFrontBack);

        $instance = $this->getInstance(['scopeConfig' => $scopeConfigMock]);

        $result = $instance->isVisibleForAreaCode($areaCode);

        $this->assertEquals($expected, $result);
    }
}
