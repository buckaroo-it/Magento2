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

namespace Buckaroo\Magento2\Test\Unit\Model\ConfigProvider;

use Buckaroo\Magento2\Model\ConfigProvider\SecondChance;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\Store;

class SecondChanceTest extends \Buckaroo\Magento2\Test\BaseTest
{
    protected $instanceClass = SecondChance::class;

    /** @var ScopeConfigInterface|\PHPUnit\Framework\MockObject\MockObject */
    private $scopeConfig;

    public function setUp(): void
    {
        parent::setUp();
        
        $this->scopeConfig = $this->getFakeMock(ScopeConfigInterface::class)->getMock();
    }

    /**
     * @return array
     */
    public static function isSecondChanceEnabledProvider()
    {
        return [
            'enabled' => [true, true],
            'disabled' => [false, false],
            'null value' => [null, false],
            'string true' => ['1', true],
            'string false' => ['0', false],
        ];
    }

    /**
     * @param mixed $configValue
     * @param bool $expected
     * 
     * @dataProvider isSecondChanceEnabledProvider
     */
    public function testIsSecondChanceEnabled($configValue, $expected)
    {
        $store = $this->getFakeMock(Store::class)->getMock();
        
        $this->scopeConfig->method('getValue')
            ->with(
                SecondChance::XPATH_SECOND_CHANCE_ENABLED,
                ScopeInterface::SCOPE_STORE,
                $store
            )
            ->willReturn($configValue);

        $instance = $this->getInstance(['scopeConfig' => $this->scopeConfig]);
        $result = $instance->isSecondChanceEnabled($store);
        
        $this->assertEquals($expected, $result);
    }

    /**
     * @return array
     */
    public static function isFirstEmailEnabledProvider()
    {
        return [
            'enabled' => [true, true],
            'disabled' => [false, false],
            'null value' => [null, false],
        ];
    }

    /**
     * @param mixed $configValue
     * @param bool $expected
     * 
     * @dataProvider isFirstEmailEnabledProvider
     */
    public function testIsFirstEmailEnabled($configValue, $expected)
    {
        $store = $this->getFakeMock(Store::class)->getMock();
        
        $this->scopeConfig->method('getValue')
            ->with(
                SecondChance::XPATH_SECOND_CHANCE_EMAIL1_ENABLED,
                ScopeInterface::SCOPE_STORE,
                $store
            )
            ->willReturn($configValue);

        $instance = $this->getInstance(['scopeConfig' => $this->scopeConfig]);
        $result = $instance->isFirstEmailEnabled($store);
        
        $this->assertEquals($expected, $result);
    }

    /**
     * @return array
     */
    public static function isSecondEmailEnabledProvider()
    {
        return [
            'enabled' => [true, true],
            'disabled' => [false, false],
            'null value' => [null, false],
        ];
    }

    /**
     * @param mixed $configValue
     * @param bool $expected
     * 
     * @dataProvider isSecondEmailEnabledProvider
     */
    public function testIsSecondEmailEnabled($configValue, $expected)
    {
        $store = $this->getFakeMock(Store::class)->getMock();
        
        $this->scopeConfig->method('getValue')
            ->with(
                SecondChance::XPATH_SECOND_CHANCE_EMAIL2_ENABLED,
                ScopeInterface::SCOPE_STORE,
                $store
            )
            ->willReturn($configValue);

        $instance = $this->getInstance(['scopeConfig' => $this->scopeConfig]);
        $result = $instance->isSecondEmailEnabled($store);
        
        $this->assertEquals($expected, $result);
    }

    /**
     * @return array
     */
    public static function getFirstEmailTemplateProvider()
    {
        return [
            'custom template' => ['custom_template_id', 'custom_template_id'],
            'empty string' => ['', ''],
            'null value' => [null, ''],
        ];
    }

    /**
     * @param mixed $configValue
     * @param string $expected
     * 
     * @dataProvider getFirstEmailTemplateProvider
     */
    public function testGetFirstEmailTemplate($configValue, $expected)
    {
        // $expected parameter is from data provider but not used in this test implementation
        unset($expected);
        $store = $this->getFakeMock(Store::class)->getMock();
        
        $this->scopeConfig->method('getValue')
            ->with(
                SecondChance::XPATH_SECOND_CHANCE_TEMPLATE1,
                ScopeInterface::SCOPE_STORE,
                $store
            )
            ->willReturn($configValue);

        $instance = $this->getInstance(['scopeConfig' => $this->scopeConfig]);
        $result = $instance->getFirstEmailTemplate($store);
        
        $this->assertEquals($configValue ?: 'buckaroo_second_chance_first', $result);
    }

    /**
     * @return array
     */
    public static function getSecondEmailTemplateProvider()
    {
        return [
            'custom template' => ['custom_template_id_2', 'custom_template_id_2'],
            'empty string' => ['', ''],
            'null value' => [null, ''],
        ];
    }

    /**
     * @param mixed $configValue
     * @param string $expected
     * 
     * @dataProvider getSecondEmailTemplateProvider
     */
    public function testGetSecondEmailTemplate($configValue, $expected)
    {
        // $expected parameter is from data provider but not used in this test implementation
        unset($expected);
        $store = $this->getFakeMock(Store::class)->getMock();
        
        $this->scopeConfig->method('getValue')
            ->with(
                SecondChance::XPATH_SECOND_CHANCE_TEMPLATE2,
                ScopeInterface::SCOPE_STORE,
                $store
            )
            ->willReturn($configValue);

        $instance = $this->getInstance(['scopeConfig' => $this->scopeConfig]);
        $result = $instance->getSecondEmailTemplate($store);
        
        $this->assertEquals($configValue ?: 'buckaroo_second_chance_second', $result);
    }

    /**
     * @return array
     */
    public static function getFirstEmailTimingProvider()
    {
        return [
            'one hour' => [1, 1],
            'twenty four hours' => [24, 24],
            'zero' => [0, 0],
            'null value' => [null, 0],
            'string number' => ['6', 6],
        ];
    }

    /**
     * @param mixed $configValue
     * @param int $expected
     * 
     * @dataProvider getFirstEmailTimingProvider
     */
    public function testGetFirstEmailTiming($configValue, $expected)
    {
        $store = $this->getFakeMock(Store::class)->getMock();
        
        $this->scopeConfig->method('getValue')
            ->with(
                SecondChance::XPATH_SECOND_CHANCE_TIMING1,
                ScopeInterface::SCOPE_STORE,
                $store
            )
            ->willReturn($configValue);

        $instance = $this->getInstance(['scopeConfig' => $this->scopeConfig]);
        $result = $instance->getFirstEmailTiming($store);
        
        $this->assertEquals($expected, $result);
    }

    /**
     * @return array
     */
    public static function getSecondEmailTimingProvider()
    {
        return [
            'twenty four hours' => [24, 24],
            'forty eight hours' => [48, 48],
            'zero' => [0, 0],
            'null value' => [null, 0],
            'string number' => ['72', 72],
        ];
    }

    /**
     * @param mixed $configValue
     * @param int $expected
     * 
     * @dataProvider getSecondEmailTimingProvider
     */
    public function testGetSecondEmailTiming($configValue, $expected)
    {
        $store = $this->getFakeMock(Store::class)->getMock();
        
        $this->scopeConfig->method('getValue')
            ->with(
                SecondChance::XPATH_SECOND_CHANCE_TIMING2,
                ScopeInterface::SCOPE_STORE,
                $store
            )
            ->willReturn($configValue);

        $instance = $this->getInstance(['scopeConfig' => $this->scopeConfig]);
        $result = $instance->getSecondEmailTiming($store);
        
        $this->assertEquals($expected, $result);
    }

    /**
     * @return array
     */
    public static function shouldSkipOutOfStockProvider()
    {
        return [
            'skip enabled' => [true, true],
            'skip disabled' => [false, false],
            'null value' => [null, false],
            'string true' => ['1', true],
        ];
    }

    /**
     * @param mixed $configValue
     * @param bool $expected
     * 
     * @dataProvider shouldSkipOutOfStockProvider
     */
    public function testShouldSkipOutOfStock($configValue, $expected)
    {
        $store = $this->getFakeMock(Store::class)->getMock();
        
        $this->scopeConfig->method('getValue')
            ->with(
                SecondChance::XPATH_NO_SEND_OUT_OF_STOCK,
                ScopeInterface::SCOPE_STORE,
                $store
            )
            ->willReturn($configValue);

        $instance = $this->getInstance(['scopeConfig' => $this->scopeConfig]);
        $result = $instance->shouldSkipOutOfStock($store);
        
        $this->assertEquals($expected, $result);
    }

    /**
     * @return array
     */
    public static function getPruneDaysProvider()
    {
        return [
            'thirty days' => [30, 30],
            'ninety days' => [90, 90],
            'zero' => [0, 0],
            'null value' => [null, 0],
            'string number' => ['60', 60],
        ];
    }

    /**
     * @param mixed $configValue
     * @param int $expected
     * 
     * @dataProvider getPruneDaysProvider
     */
    public function testGetPruneDays($configValue, $expected)
    {
        $store = $this->getFakeMock(Store::class)->getMock();
        
        $this->scopeConfig->method('getValue')
            ->with(
                SecondChance::XPATH_PRUNE_DAYS,
                ScopeInterface::SCOPE_STORE,
                $store
            )
            ->willReturn($configValue);

        $instance = $this->getInstance(['scopeConfig' => $this->scopeConfig]);
        $result = $instance->getPruneDays($store);
        
        $this->assertEquals($expected, $result);
    }

    /**
     * @return array
     */
    public static function canSendMultipleEmailsProvider()
    {
        return [
            'allowed' => [true, true],
            'not allowed' => [false, false],
            'null value' => [null, false],
            'string true' => ['1', true],
        ];
    }

    /**
     * @param mixed $configValue
     * @param bool $expected
     * 
     * @dataProvider canSendMultipleEmailsProvider
     */
    public function testCanSendMultipleEmails($configValue, $expected)
    {
        $store = $this->getFakeMock(Store::class)->getMock();
        
        $this->scopeConfig->method('getValue')
            ->with(
                SecondChance::XPATH_MULTIPLE_EMAILS_SEND,
                ScopeInterface::SCOPE_STORE,
                $store
            )
            ->willReturn($configValue);

        $instance = $this->getInstance(['scopeConfig' => $this->scopeConfig]);
        $result = $instance->canSendMultipleEmails($store);
        
        $this->assertEquals($expected, $result);
    }
} 