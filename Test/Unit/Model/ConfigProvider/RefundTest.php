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
namespace TIG\Buckaroo\Test\Unit\Model\ConfigProvider;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;
use TIG\Buckaroo\Model\ConfigProvider\Refund;

class RefundTest extends \TIG\Buckaroo\Test\BaseTest
{
    protected $instanceClass = Refund::class;

    /**
     * Test the getConfig method.
     */
    public function testGetConfig()
    {
        $scopeConfigMock = $this->getFakeMock(ScopeConfigInterface::class)
            ->setMethods(['getValue'])
            ->getMockForAbstractClass();
        $scopeConfigMock->expects($this->exactly(2))
            ->method('getValue')
            ->withConsecutive(
                [Refund::XPATH_REFUND_ENABLED, ScopeInterface::SCOPE_STORE, null],
                [Refund::XPATH_REFUND_ALLOW_PUSH, ScopeInterface::SCOPE_STORE, null]
            )
            ->willReturn(false);

        $instance = $this->getInstance(['scopeConfig' => $scopeConfigMock]);
        $result = $instance->getConfig();

        $this->assertFalse($result['enabled']);
        $this->assertFalse($result['allow_push']);
    }

    /**
     * Test the getConfig method.
     */
    public function testGetConfigWithStoreId()
    {
        $scopeConfigMock = $this->getFakeMock(ScopeConfigInterface::class)
            ->setMethods(['getValue'])
            ->getMockForAbstractClass();
        $scopeConfigMock->expects($this->exactly(2))
            ->method('getValue')
            ->withConsecutive(
                [Refund::XPATH_REFUND_ENABLED, ScopeInterface::SCOPE_STORE, 1],
                [Refund::XPATH_REFUND_ALLOW_PUSH, ScopeInterface::SCOPE_STORE, 1]
            )
            ->willReturn(false);

        $instance = $this->getInstance(['scopeConfig' => $scopeConfigMock]);
        $result = $instance->getConfig(1);

        $this->assertFalse($result['enabled']);
        $this->assertFalse($result['allow_push']);
    }
}
