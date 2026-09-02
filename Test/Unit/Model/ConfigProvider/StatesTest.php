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
use Buckaroo\Magento2\Model\ConfigProvider\States;

class StatesTest extends BaseTest
{
    protected $instanceClass = States::class;

    public function testGetConfig()
    {
        $expectedConfig = [
            'order_state_new'       => null,
            'order_state_pending'   => null,
            'order_state_success'   => null,
            'order_state_failed'    => null,
            'order_state_incorrect' => null,
        ];

        $instance = $this->getInstance();
        $result = $instance->getConfig();

        $this->assertEquals($expectedConfig, $result);
    }
}
