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

namespace Buckaroo\Magento2\Test\Unit\Model\ConfigProvider;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;
use Buckaroo\Magento2\Model\ConfigProvider\Refund;

class RefundTest extends \Buckaroo\Magento2\Test\BaseTest
{
    protected $instanceClass = Refund::class;

    /**
     * Test the getConfig method.
     */
    public function testGetConfig()
    {
        $scopeConfigMock = $this->getFakeMock(ScopeConfigInterface::class)
            ->getMock();
        $scopeConfigMock->method('getValue')->willReturn(null)
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
            ->getMock();
        $scopeConfigMock->method('getValue')->willReturn(null)
            ->willReturn(false);

        $instance = $this->getInstance(['scopeConfig' => $scopeConfigMock]);
        $result = $instance->getConfig(1);

        $this->assertFalse($result['enabled']);
        $this->assertFalse($result['allow_push']);
    }
}
