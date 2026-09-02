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

namespace Buckaroo\Magento2\Test\Unit\Service\Formatter;

use Buckaroo\Magento2\Service\Formatter\AddressFormatter;
use Buckaroo\Magento2\Test\BaseTest;

class AddressFormatterTest extends BaseTest
{
    protected $instanceClass = AddressFormatter::class;

    public function testFormat()
    {
        $streetFormatter = $this->getObject(\Buckaroo\Magento2\Service\Formatter\Address\StreetFormatter::class);
        $phoneFormatter = $this->getObject(\Buckaroo\Magento2\Service\Formatter\Address\PhoneFormatter::class);

        $addressMock = $this->getFakeMock(\Magento\Sales\Api\Data\OrderAddressInterface::class)->getMock();
        $addressMock->method('getCountryId')->willReturn('NL');
        $addressMock->method('getStreet')->willReturn(['Street', '1', 'A']);
        $addressMock->method('getTelephone')->willReturn('1234567890');

        $instance = $this->getInstance(['streetFormatter' => $streetFormatter, 'phoneFormatter' => $phoneFormatter]);
        $result = $instance->format($addressMock);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('street', $result);
        $this->assertArrayHasKey('telephone', $result);
        $this->assertCount(3, $result['street']);
        $this->assertCount(4, $result['telephone']);
    }
}
