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

namespace Buckaroo\Magento2\Test\Unit\Helper;

use Buckaroo\Magento2\Helper\Data;
use Buckaroo\Magento2\Test\BaseTest;
use PHPUnit\Framework\Attributes\DataProvider;

class DataTest extends BaseTest
{
    protected $instanceClass = Data::class;

    /**
     * Buckaroo status codes as documented by Buckaroo, plus the plugin's own order-failed code.
     */
    public static function statusCodeProvider(): array
    {
        return [
            'success'               => ['BUCKAROO_MAGENTO2_STATUSCODE_SUCCESS', 190],
            'failed'                => ['BUCKAROO_MAGENTO2_STATUSCODE_FAILED', 490],
            'validation failure'    => ['BUCKAROO_MAGENTO2_STATUSCODE_VALIDATION_FAILURE', 491],
            'technical error'       => ['BUCKAROO_MAGENTO2_STATUSCODE_TECHNICAL_ERROR', 492],
            'rejected'              => ['BUCKAROO_MAGENTO2_STATUSCODE_REJECTED', 690],
            'waiting on user input' => ['BUCKAROO_MAGENTO2_STATUSCODE_WAITING_ON_USER_INPUT', 790],
            'pending processing'    => ['BUCKAROO_MAGENTO2_STATUSCODE_PENDING_PROCESSING', 791],
            'waiting on consumer'   => ['BUCKAROO_MAGENTO2_STATUSCODE_WAITING_ON_CONSUMER', 792],
            'payment on hold'       => ['BUCKAROO_MAGENTO2_STATUSCODE_PAYMENT_ON_HOLD', 793],
            'pending approval'      => ['BUCKAROO_MAGENTO2_STATUSCODE_PENDING_APPROVAL', 794],
            'cancelled by user'     => ['BUCKAROO_MAGENTO2_STATUSCODE_CANCELLED_BY_USER', 890],
            'cancelled by merchant' => ['BUCKAROO_MAGENTO2_STATUSCODE_CANCELLED_BY_MERCHANT', 891],
            'order failed'          => ['BUCKAROO_MAGENTO2_ORDER_FAILED', 11014],
        ];
    }

    #[DataProvider('statusCodeProvider')]
    public function testGetStatusCode(string $name, int $code)
    {
        $this->assertSame($code, $this->getInstance()->getStatusCode($name));
    }

    #[DataProvider('statusCodeProvider')]
    public function testGetStatusByValue(string $name, int $code)
    {
        $instance = $this->getInstance();

        $this->assertSame($name, $instance->getStatusByValue($code));
        // Push requests carry the code as a string.
        $this->assertSame($name, $instance->getStatusByValue((string)$code));
    }

    public function testUnknownStatusesAreNull()
    {
        $instance = $this->getInstance();

        $this->assertNull($instance->getStatusCode(''));
        $this->assertNull($instance->getStatusCode('BUCKAROO_MAGENTO2_STATUSCODE_UNKNOWN'));
        $this->assertNull($instance->getStatusByValue(''));
        $this->assertNull($instance->getStatusByValue(999));
    }

    public function testGetStatusCodesListsExactlyTheKnownCodes()
    {
        $expected = [];
        foreach (self::statusCodeProvider() as [$name, $code]) {
            $expected[$name] = $code;
        }

        $this->assertSame($expected, $this->getInstance()->getStatusCodes());
    }
}
