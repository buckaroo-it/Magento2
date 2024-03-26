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

        $instance = $this->getInstance(['streetFormatter' => $streetFormatter, 'phoneFormatter' => $phoneFormatter]);
        $result = $instance->format($addressMock);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('street', $result);
        $this->assertArrayHasKey('telephone', $result);
        $this->assertCount(3, $result['street']);
        $this->assertCount(4, $result['telephone']);
    }
}
