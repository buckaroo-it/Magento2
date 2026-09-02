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

use Buckaroo\Magento2\Test\BaseTest;
use Buckaroo\Magento2\Model\ConfigProvider\AllowedCurrencies;

class AllowedCurrenciesTest extends BaseTest
{
    protected $instanceClass = AllowedCurrencies::class;

    public function testSetAllowedCurrencies()
    {
        $randomArray = [rand(1, 1000)];

        $instance = $this->getInstance();
        $result = $instance->setAllowedCurrencies($randomArray);

        $this->assertInstanceOf(AllowedCurrencies::class, $result);
        $this->assertEquals($randomArray, $instance->getAllowedCurrencies());
    }

    public function testGetConfig()
    {
        $instance = $this->getInstance();
        $result = $instance->getConfig();

        $this->assertIsArray($result);
        $this->assertArrayHasKey('allowedCurrencies', $result);
        $this->assertCount(28, $result['allowedCurrencies']);
    }
}
