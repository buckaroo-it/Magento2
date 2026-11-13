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

namespace Buckaroo\Magento2\Test\Unit\Model\Validator;

use Buckaroo\Magento2\Test\BaseTest;
use Buckaroo\Magento2\Model\Validator\RedirectProcess;

class RedirectProcessTest extends BaseTest
{
    protected $instanceClass = RedirectProcess::class;

    public function testValidate()
    {
        $instance = $this->getInstance();
        $result = $instance->validate(null);
        $this->assertTrue($result);
    }
}
