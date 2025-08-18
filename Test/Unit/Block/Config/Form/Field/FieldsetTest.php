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

namespace Buckaroo\Magento2\Test\Unit\Block\Config\Form\Field;


use Magento\Backend\Block\Context;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Data\Form\Element\AbstractElement;
use Magento\Store\Model\ScopeInterface;
use Buckaroo\Magento2\Block\Config\Form\Field\Fieldset;
use Buckaroo\Magento2\Test\BaseTest;

class FieldsetTest extends BaseTest
{
    protected $instanceClass = Fieldset::class;

    /**
     * @return array
     */
    public static function getFrontendClassProvider()
    {
        return [
            'inactive' => [
                '0',
                'payment_method_inactive'
            ],
            'test' => [
                '1',
                'payment_method_active payment_method_test'
            ],
            'live' => [
                '2',
                'payment_method_active payment_method_live'
            ],
        ];
    }

    /**
     * @param $configValue
     * @param $expected
     *
     * @dataProvider getFrontendClassProvider
     */
    public function testGetFrontendClass($configValue, $expected)
    {
        $methodId = 'test_method';
        $elementMock = $this->getFakeMock(AbstractElement::class)
            ->onlyMethods(['getData'])
            ->getMockForAbstractClass();
        $elementMock->expects($this->atLeastOnce())->method('getData')->with('group')->willReturn([
            'id' => $methodId,
            'children' => [
                'active' => [
                    'config_path' => 'buckaroo_magento2/config/path'
                ]
            ]
        ]);

        $requestMock = $this->getFakeMock(RequestInterface::class)->getMockForAbstractClass();

        $scopeConfigMock = $this->getFakeMock(ScopeConfigInterface::class)->getMockForAbstractClass();
        $scopeConfigMock->method('getValue')->willReturn($configValue);

        $contextMock = $this->getFakeMock(Context::class)->onlyMethods(['getScopeConfig', 'getRequest'])->getMock();
        $contextMock->method('getScopeConfig')->willReturn($scopeConfigMock);
        $contextMock->method('getRequest')->willReturn($requestMock);

        $instance = $this->getInstance(['context' => $contextMock]);
        $result = $this->invokeArgs('_getFrontendClass', [$elementMock], $instance);
        $this->assertStringContainsString($expected, $result);
    }

    public function testGetElementValue()
    {
        $elementGroupArray = [
            'children' => [
                'active' => [
                    'config_path' => 'buckaroo_magento2/config/path'
                ]
            ]
        ];
        $elementMock = $this->getFakeMock(AbstractElement::class)->onlyMethods(['getData'])->getMockForAbstractClass();
        $elementMock->method('getData')->with('group')->willReturn($elementGroupArray);

        $requestMock = $this->getFakeMock(RequestInterface::class)->getMockForAbstractClass();

        $scopeConfigMock = $this->getFakeMock(ScopeConfigInterface::class)->getMockForAbstractClass();
        $scopeConfigMock->method('getValue')
            ->with('buckaroo_magento2/config/path', ScopeConfigInterface::SCOPE_TYPE_DEFAULT, null)
            ->willReturn('1');

        $contextMock = $this->getFakeMock(Context::class)->onlyMethods(['getScopeConfig', 'getRequest'])->getMock();
        $contextMock->method('getScopeConfig')->willReturn($scopeConfigMock);
        $contextMock->method('getRequest')->willReturn($requestMock);

        $instance = $this->getInstance(['context' => $contextMock]);
        $result = $this->invokeArgs('getElementValue', [$elementMock], $instance);
        $this->assertEquals('1', $result);
    }

    /**
     * @return array
     */
    public static function getScopeValueProvider()
    {
        return [
            'on default config' => [
                null,
                null,
                ['scope' => ScopeConfigInterface::SCOPE_TYPE_DEFAULT, 'scopevalue' => null]
            ],
            'on website' => [
                null,
                1,
                ['scope' => ScopeInterface::SCOPE_WEBSITE, 'scopevalue' => 1]
            ],
            'on store view' => [
                2,
                null,
                ['scope' => ScopeInterface::SCOPE_STORE, 'scopevalue' => 2]
            ]
        ];
    }

    /**
     * @param $store
     * @param $website
     * @param $expected
     *
     * @dataProvider getScopeValueProvider
     */
    public function testGetScopeValue($store, $website, $expected)
    {
        $requestMock = $this->getFakeMock(RequestInterface::class)->getMockForAbstractClass();
        $requestMock->method('getParam')
            ->willReturnCallback(function($arg1, $arg2 = null) {
                    static $callCount = 0;
                    $callCount++;
                    // TODO: Implement proper argument checking based on call count
                    // Original withConsecutive args: ['store'], ['website']
                    return null;
                })
            ->willReturnOnConsecutiveCalls($store, $website);

        $contextMock = $this->getFakeMock(Context::class)->onlyMethods(['getRequest'])->getMock();
        $contextMock->method('getRequest')->willReturn($requestMock);

        $instance = $this->getInstance(['context' => $contextMock]);
        $result = $this->invoke('getScopeValue', $instance);
        $this->assertEquals($expected, $result);
    }
}
