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
namespace TIG\Buckaroo\Test\Unit\Block\Config\Form\Field;

use Magento\Backend\Block\Context;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Data\Form\Element\AbstractElement;
use Magento\Store\Model\ScopeInterface;
use TIG\Buckaroo\Block\Config\Form\Field\Fieldset;
use TIG\Buckaroo\Test\BaseTest;

class EditTest extends BaseTest
{
    protected $instanceClass = Fieldset::class;

    /**
     * @return array
     */
    public function getFrontendClassProvider()
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
        $elementMock = $this->getFakeMock(AbstractElement::class)->getMockForAbstractClass();
        $requestMock = $this->getFakeMock(RequestInterface::class)->getMockForAbstractClass();

        $scopeConfigMock = $this->getFakeMock(ScopeConfigInterface::class)->getMockForAbstractClass();
        $scopeConfigMock->expects($this->once())->method('getValue')->willReturn($configValue);

        $contextMock = $this->getFakeMock(Context::class)->setMethods(['getScopeConfig', 'getRequest'])->getMock();
        $contextMock->expects($this->once())->method('getScopeConfig')->willReturn($scopeConfigMock);
        $contextMock->expects($this->once())->method('getRequest')->willReturn($requestMock);

        $instance = $this->getInstance(['context' => $contextMock]);
        $result = $this->invokeArgs('_getFrontendClass', [$elementMock], $instance);
        $this->assertContains($expected, $result);
    }

    public function testGetElementValue()
    {
        $elementGroupArray = [
            'children' => [
                'active' => [
                    'config_path' => 'tig_buckaroo/config/path'
                ]
            ]
        ];
        $elementMock = $this->getFakeMock(AbstractElement::class)->setMethods(['getData'])->getMockForAbstractClass();
        $elementMock->expects($this->once())->method('getData')->with('group')->willReturn($elementGroupArray);

        $requestMock = $this->getFakeMock(RequestInterface::class)->getMockForAbstractClass();

        $scopeConfigMock = $this->getFakeMock(ScopeConfigInterface::class)->getMockForAbstractClass();
        $scopeConfigMock->expects($this->once())
            ->method('getValue')
            ->with('tig_buckaroo/config/path', ScopeConfigInterface::SCOPE_TYPE_DEFAULT, null)
            ->willReturn('1');

        $contextMock = $this->getFakeMock(Context::class)->setMethods(['getScopeConfig', 'getRequest'])->getMock();
        $contextMock->expects($this->once())->method('getScopeConfig')->willReturn($scopeConfigMock);
        $contextMock->expects($this->once())->method('getRequest')->willReturn($requestMock);

        $instance = $this->getInstance(['context' => $contextMock]);
        $result = $this->invokeArgs('getElementValue', [$elementMock], $instance);
        $this->assertEquals('1', $result);
    }

    /**
     * @return array
     */
    public function getScopeValueProvider()
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
        $requestMock->expects($this->exactly(2))
            ->method('getParam')
            ->withConsecutive(['store'], ['website'])
            ->willReturnOnConsecutiveCalls($store, $website);

        $contextMock = $this->getFakeMock(Context::class)->setMethods(['getRequest'])->getMock();
        $contextMock->expects($this->once())->method('getRequest')->willReturn($requestMock);

        $instance = $this->getInstance(['context' => $contextMock]);
        $result = $this->invoke('getScopeValue', $instance);
        $this->assertEquals($expected, $result);
    }
}
